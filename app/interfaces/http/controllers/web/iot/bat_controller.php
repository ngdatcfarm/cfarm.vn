<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use App\Domains\IoT\Services\MqttService;
use PDO;

/**
 * Cloud Bat Controller - reads synced bats data, sends commands to local via sync
 *
 * Cloud DB has `bats` table synced from local (master). Commands go back via
 * POST /api/sync/command to local's execute_remote_command().
 */
class BatController
{
    private MqttService $mqtt;

    public function __construct(private PDO $pdo)
    {
        $this->mqtt = new MqttService();
    }

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * GET /iot/bat/{id} - Get bat status
     */
    public function status(array $vars): void
    {
        $id = (int)$vars['id'];
        $bat = $this->getBat($id);
        if (!$bat) {
            $this->json(['ok' => false, 'message' => 'Not found'], 404);
        }
        $this->json([
            'ok' => true,
            'bat' => [
                'id' => (int)$bat['id'],
                'barn_id' => $bat['barn_id'],
                'code' => $bat['code'],
                'name' => $bat['name'],
                'up_relay_channel' => (int)$bat['up_relay_channel'],
                'down_relay_channel' => (int)$bat['down_relay_channel'],
                'device_id' => $bat['device_id'] ? (int)$bat['device_id'] : null,
                'position' => $bat['position'],
                'auto_enabled' => (bool)$bat['auto_enabled'],
                'timeout_seconds' => (int)$bat['timeout_seconds'],
            ],
            'moving_state' => $bat['position'],
        ]);
    }

    /**
     * POST /iot/bat/{id}/up - Move bat up
     */
    public function move_up(array $vars): void
    {
        $this->sendCommand((int)$vars['id'], 'up');
    }

    /**
     * POST /iot/bat/{id}/down - Move bat down
     */
    public function move_down(array $vars): void
    {
        $this->sendCommand((int)$vars['id'], 'down');
    }

    /**
     * POST /iot/bat/{id}/stop - Stop bat
     */
    public function stop(array $vars): void
    {
        $this->sendCommand((int)$vars['id'], 'stop');
    }

    /**
     * Send command to local via sync endpoint
     */
    private function sendCommand(int $batId, string $action): void
    {
        $bat = $this->getBat($batId);
        if (!$bat) {
            $this->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        if (!$bat['device_id']) {
            $this->json(['ok' => false, 'message' => 'Bat chưa gắn thiết bị'], 400);
        }

        // Get local sync URL from settings
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE name = 'local_sync_url' LIMIT 1");
        $stmt->execute();
        $localUrl = $stmt->fetchColumn() ?: 'http://localhost:8080';

        $payload = [
            'type' => 'bat',
            'bat_id' => $batId,
            'action' => $action,
            'device_id' => (int)$bat['device_id'],
        ];

        try {
            $ch = curl_init($localUrl . '/api/sync/command');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->json(['ok' => false, 'message' => 'Local server unreachable'], 502);
            }

            $data = json_decode($resp, true);
            if (!($data['ok'] ?? false)) {
                $this->json(['ok' => false, 'message' => $data['message'] ?? 'Command failed'], 400);
            }

            $this->json([
                'ok' => true,
                'message' => 'Đã gửi lệnh ' . $action,
                'bat_id' => $batId,
                'action' => $action,
                'timeout' => (int)$bat['timeout_seconds'],
            ]);
        } catch (\Throwable $e) {
            error_log("[BatController] sendCommand error: " . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    private function getBat(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bats WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
