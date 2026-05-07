<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Care;

use PDO;

/**
 * CareProxyController - Proxy care operations from cloud to local server.
 *
 * User writes care on cloud UI → cloud proxies to local via HTTP
 * → local writes to PostgreSQL → local syncs back to cloud via sync_queue
 *
 * Local connection: Cloudflare Tunnel (https://alternate-hrs-governor-surfaces.trycloudflare.com)
 * Local auth: Bearer token from sync_config.local_token
 */
class CareProxyController
{
    private const LOCAL_IP = 'alternate-hrs-governor-surfaces.trycloudflare.com';
    private const LOCAL_PORT = 443;

    public function __construct(private PDO $pdo) {}

    // ── Helper ──────────────────────────────────────

    private function json(bool $ok, array $data = [], int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    }

    private function get_json_body(): array
    {
        $body = file_get_contents('php://input');
        return json_decode($body ?: '{}', true) ?: [];
    }

    private function get_local_token(): string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM sync_config WHERE `key` = 'local_token'");
        $stmt->execute();
        return $stmt->fetch()['value'] ?? '';
    }

    /**
     * Proxy an HTTP request to local server.
     *
     * @param string $method GET|POST
     * @param string $careType feed|death|medication|weight|sale|water
     * @param string|null $cycleId null for POST (no cycle_id in URL), int for GET
     * @param array $body POST body data
     * @return array ['ok' => bool, 'code' => int, 'data' => array, 'message' => string]
     */
    private function proxy_to_local(string $method, string $careType, ?int $cycleId = null, array $body = []): array
    {
        $token = $this->get_local_token();

        if ($method === 'GET' && $cycleId !== null) {
            $url = "https://" . self::LOCAL_IP . ":" . self::LOCAL_PORT . "/api/farm/care/{$careType}/{$cycleId}";
        } else {
            $url = "https://" . self::LOCAL_IP . ":" . self::LOCAL_PORT . "/api/farm/care/{$careType}";
        }

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer {$token}",
        ];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS    => $method !== 'GET' ? json_encode($body) : null,
            CURLOPT_HTTPHEADER    => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'code' => 503, 'message' => "Local offline: {$error}", 'data' => []];
        }

        $data = json_decode($resp, true) ?: [];

        // Local returns {ok: true, feed/death/...: {...record}} on success
        // Local returns HTTP 4xx with {detail: "..."} on error
        if ($http_code >= 200 && $http_code < 300) {
            return ['ok' => true, 'code' => $http_code, 'data' => $data, 'message' => 'OK'];
        }

        $msg = $data['detail'] ?? "HTTP {$http_code}";
        return ['ok' => false, 'code' => $http_code, 'message' => $msg, 'data' => $data];
    }

    // ── GET /api/cloud/care/cycles ───────────────────

    public function get_cycles(array $vars): void
    {
        $token = $this->get_local_token();
        $url = "https://" . self::LOCAL_IP . ":" . self::LOCAL_PORT . "/api/farm/cycles";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER    => ["Authorization: Bearer {$token}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($resp, true) ?: [];
            $this->json(true, ['cycles' => $data]);
        } else {
            $this->json(false, ['cycles' => [], 'offline' => true], 200);
        }
    }

    // ── GET /api/cloud/care/barns ────────────────────

    public function get_barns(array $vars): void
    {
        $token = $this->get_local_token();
        $url = "https://" . self::LOCAL_IP . ":" . self::LOCAL_PORT . "/api/farm/barns?active_only=false";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER    => ["Authorization: Bearer {$token}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($resp, true) ?: [];
            $this->json(true, ['barns' => $data]);
        } else {
            $this->json(false, ['barns' => [], 'offline' => true], 200);
        }
    }

    // ── GET /api/cloud/care/{type}/{cycle_id} ────────

    public function get(array $vars): void
    {
        $type = $vars['type'] ?? '';
        $cycle_id = isset($vars['cycle_id']) ? (int)$vars['cycle_id'] : 0;

        $allowed = ['feed', 'death', 'medication', 'weight', 'sale', 'water'];
        if (!in_array($type, $allowed) || $cycle_id <= 0) {
            $this->json(false, ['message' => 'Invalid type or cycle_id'], 400);
            return;
        }

        $result = $this->proxy_to_local('GET', $type, $cycle_id);
        $this->json($result['ok'], [
            'records' => $result['data'] ?? [],
            'offline' => !$result['ok'],
        ], $result['ok'] ? 200 : 502);
    }

    // ── POST /api/cloud/care/feed ────────────────────

    public function feed(array $vars): void
    {
        $body = $this->get_json_body();
        $result = $this->proxy_to_local('POST', 'feed', null, $body);
        $this->json($result['ok'], [
            'record' => $result['data'] ?? [],
            'offline' => !$result['ok'],
        ], $result['ok'] ? 201 : ($result['code'] ?: 502));
    }

    // ── POST /api/cloud/care/death ───────────────────

    public function death(array $vars): void
    {
        $body = $this->get_json_body();
        $result = $this->proxy_to_local('POST', 'death', null, $body);
        $this->json($result['ok'], [
            'record' => $result['data'] ?? [],
            'offline' => !$result['ok'],
        ], $result['ok'] ? 201 : ($result['code'] ?: 502));
    }

    // ── POST /api/cloud/care/medication ───────────────

    public function medication(array $vars): void
    {
        $body = $this->get_json_body();
        $result = $this->proxy_to_local('POST', 'medication', null, $body);
        $this->json($result['ok'], [
            'record' => $result['data'] ?? [],
            'offline' => !$result['ok'],
        ], $result['ok'] ? 201 : ($result['code'] ?: 502));
    }

    // ── POST /api/cloud/care/weight ───────────────────

    public function weight(array $vars): void
    {
        $body = $this->get_json_body();
        $result = $this->proxy_to_local('POST', 'weight', null, $body);
        $this->json($result['ok'], [
            'record' => $result['data'] ?? [],
            'offline' => !$result['ok'],
        ], $result['ok'] ? 201 : ($result['code'] ?: 502));
    }

    // ── POST /api/cloud/care/sale ─────────────────────

    public function sale(array $vars): void
    {
        $body = $this->get_json_body();
        $result = $this->proxy_to_local('POST', 'sale', null, $body);
        $this->json($result['ok'], [
            'record' => $result['data'] ?? [],
            'offline' => !$result['ok'],
        ], $result['ok'] ? 201 : ($result['code'] ?: 502));
    }

    // ── POST /api/cloud/care/water ────────────────────

    public function water(array $vars): void
    {
        $body = $this->get_json_body();
        $result = $this->proxy_to_local('POST', 'water', null, $body);
        $this->json($result['ok'], [
            'record' => $result['data'] ?? [],
            'offline' => !$result['ok'],
        ], $result['ok'] ? 201 : ($result['code'] ?: 502));
    }
}