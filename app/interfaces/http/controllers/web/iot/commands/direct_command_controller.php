<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT\Commands;

use App\Domains\IoT\Services\MqttService;
use PDO;

/**
 * DirectCommandController - Gửi lệnh IoT trực tiếp qua Cloud MQTT
 *
 * Cho phép gửi command đến ESP32 qua cloud MQTT broker (cfarm.vn prefix)
 * mà không cần qua local server.
 *
 * Được sử dụng khi:
 * 1. Local server offline
 * 2. User điều khiển từ xa qua cloud
 *
 * ESP32 cần hỗ trợ dual-subscribe:
 * - cfarm/{code}/cmd (local MQTT)
 * - cfarm.vn/{code}/cmd (cloud MQTT)
 */
class DirectCommandController
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
     * GET /api/iot/direct/devices - Danh sách device có mqtt_topic
     * Để client biết device nào có thể nhận direct command
     */
    public function devices(array $vars): void
    {
        $devices = $this->pdo->query("
            SELECT d.id, d.device_code, d.mqtt_topic, d.name, d.is_online,
                   dt.name as type_name
            FROM devices d
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            ORDER BY d.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->json([
            'ok' => true,
            'devices' => $devices,
            'cloud_prefix' => MqttService::CLOUD_PREFIX,
        ]);
    }

    /**
     * POST /api/iot/direct/relay - Gửi lệnh relay trực tiếp qua cloud MQTT
     *
     * Body: { "device_code": "barn1", "channel": 1, "state": "on" }
     */
    public function relay(array $vars): void
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $deviceCode = $body['device_code'] ?? null;
        $channel = (int)($body['channel'] ?? 0);
        $state = $body['state'] ?? null;

        if (!$deviceCode || !$channel || !$state) {
            $this->json(['ok' => false, 'message' => 'Missing device_code, channel or state'], 400);
        }

        if (!in_array($state, ['on', 'off'])) {
            $this->json(['ok' => false, 'message' => 'State must be on or off'], 400);
        }

        // Get device to verify it exists
        $stmt = $this->pdo->prepare("SELECT id, mqtt_topic FROM devices WHERE device_code = ?");
        $stmt->execute([$deviceCode]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            $this->json(['ok' => false, 'message' => 'Device not found'], 404);
        }

        // Send via cloud MQTT
        $sent = $this->mqtt->sendRelayCommandCloud($deviceCode, $channel, $state);

        if (!$sent) {
            $this->json(['ok' => false, 'message' => 'Failed to send MQTT command'], 500);
        }

        // Log command
        $this->logCommand($device['id'], 'relay', [
            'action' => 'relay',
            'channel' => $channel,
            'state' => $state,
            'via' => 'cloud_mqtt_direct',
        ]);

        $this->json([
            'ok' => true,
            'message' => "Relay {$state} sent to {$deviceCode} ch{$channel}",
            'device_code' => $deviceCode,
            'channel' => $channel,
            'state' => $state,
            'topic' => MqttService::CLOUD_PREFIX . "/{$deviceCode}/cmd",
        ]);
    }

    /**
     * POST /api/iot/direct/relay-timed - Gửi lệnh relay có duration qua cloud MQTT
     *
     * Body: { "device_code": "barn1", "channel": 1, "duration_seconds": 60 }
     */
    public function relayTimed(array $vars): void
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $deviceCode = $body['device_code'] ?? null;
        $channel = (int)($body['channel'] ?? 0);
        $duration = (int)($body['duration_seconds'] ?? 0);

        if (!$deviceCode || !$channel || !$duration) {
            $this->json(['ok' => false, 'message' => 'Missing device_code, channel or duration_seconds'], 400);
        }

        // Get device
        $stmt = $this->pdo->prepare("SELECT id, mqtt_topic FROM devices WHERE device_code = ?");
        $stmt->execute([$deviceCode]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            $this->json(['ok' => false, 'message' => 'Device not found'], 404);
        }

        // Send via cloud MQTT with duration
        $sent = $this->mqtt->sendRelayOnWithDurationCloud($deviceCode, $channel, $duration);

        if (!$sent) {
            $this->json(['ok' => false, 'message' => 'Failed to send MQTT command'], 500);
        }

        // Log command
        $this->logCommand($device['id'], 'relay_timed', [
            'action' => 'relay',
            'channel' => $channel,
            'state' => 'on',
            'duration' => $duration,
            'via' => 'cloud_mqtt_direct',
        ]);

        $this->json([
            'ok' => true,
            'message' => "Timed relay ({$duration}s) sent to {$deviceCode} ch{$channel}",
            'device_code' => $deviceCode,
            'channel' => $channel,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * POST /api/iot/direct/curtain - Gửi lệnh curtain qua cloud MQTT
     *
     * Body: { "device_code": "barn1", "position": 50 }
     */
    public function curtain(array $vars): void
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $deviceCode = $body['device_code'] ?? null;
        $position = (int)($body['position'] ?? -1);

        if (!$deviceCode || $position < 0 || $position > 100) {
            $this->json(['ok' => false, 'message' => 'Missing device_code or position (0-100)'], 400);
        }

        // Get device
        $stmt = $this->pdo->prepare("SELECT id, mqtt_topic FROM devices WHERE device_code = ?");
        $stmt->execute([$deviceCode]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            $this->json(['ok' => false, 'message' => 'Device not found'], 404);
        }

        // Send curtain position via cloud MQTT
        $sent = $this->mqtt->sendCurtainPositionCloud($deviceCode, $position);

        if (!$sent) {
            $this->json(['ok' => false, 'message' => 'Failed to send MQTT command'], 500);
        }

        // Log command
        $this->logCommand($device['id'], 'curtain', [
            'action' => 'set_position',
            'to' => $position,
            'via' => 'cloud_mqtt_direct',
        ]);

        $this->json([
            'ok' => true,
            'message' => "Curtain position {$position}% sent to {$deviceCode}",
            'device_code' => $deviceCode,
            'position' => $position,
        ]);
    }

    /**
     * POST /api/iot/direct/ping - Ping device qua cloud MQTT
     *
     * Body: { "device_code": "barn1" }
     */
    public function ping(array $vars): void
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $deviceCode = $body['device_code'] ?? null;

        if (!$deviceCode) {
            $this->json(['ok' => false, 'message' => 'Missing device_code'], 400);
        }

        // Get device
        $stmt = $this->pdo->prepare("SELECT id FROM devices WHERE device_code = ?");
        $stmt->execute([$deviceCode]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            $this->json(['ok' => false, 'message' => 'Device not found'], 404);
        }

        // Send ping via cloud MQTT
        $sent = $this->mqtt->sendPingCloud($deviceCode);

        if (!$sent) {
            $this->json(['ok' => false, 'message' => 'Failed to send MQTT ping'], 500);
        }

        $this->logCommand($device['id'], 'ping', [
            'action' => 'ping',
            'via' => 'cloud_mqtt_direct',
        ]);

        $this->json([
            'ok' => true,
            'message' => "Ping sent to {$deviceCode}",
            'device_code' => $deviceCode,
        ]);
    }

    /**
     * Log command to device_commands table
     */
    private function logCommand(int $deviceId, string $commandType, array $payload): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO device_commands
                    (device_id, command_type, payload, source, status, sent_at)
                VALUES (:did, :type, :payload, 'cloud_direct', 'sent', NOW())
            ")->execute([
                ':did' => $deviceId,
                ':type' => $commandType,
                ':payload' => json_encode($payload),
            ]);
        } catch (\Throwable $e) {
            error_log("[DirectCommand] Log failed: " . $e->getMessage());
        }
    }
}
