<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use PDO;

/**
 * Cloud Bat Controller - reads synced bats data, sends commands to local via sync
 */
class BatController
{
    public function __construct(private PDO $pdo) {}

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

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

    public function move_up(array $vars): void { $this->sendCommand((int)$vars['id'], 'up'); }
    public function move_down(array $vars): void { $this->sendCommand((int)$vars['id'], 'down'); }
    public function stop(array $vars): void { $this->sendCommand((int)$vars['id'], 'stop'); }

    private function sendCommand(int $batId, string $action): void
    {
        $bat = $this->getBat($batId);
        if (!$bat) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }
        if (!$bat['device_id']) { $this->json(['ok' => false, 'message' => 'Bat chưa gắn thiết bị'], 400); }

        // Get device_code from devices table
        $stmt = $this->pdo->prepare("SELECT device_code FROM devices WHERE id = ?");
        $stmt->execute([$bat['device_id']]);
        $deviceCode = $stmt->fetchColumn();
        if (!$deviceCode) { $this->json(['ok' => false, 'message' => 'Device not found'], 404); }

        // Build MQTT command based on action
        $commands = [];
        if ($action === 'up') {
            $channel = (int)$bat['up_relay_channel'];
            $commands[] = ['channel' => $channel, 'state' => 'on'];
        } elseif ($action === 'down') {
            $channel = (int)$bat['down_relay_channel'];
            $commands[] = ['channel' => $channel, 'state' => 'on'];
        } elseif ($action === 'stop') {
            // Stop = turn OFF both channels
            $commands[] = ['channel' => (int)$bat['up_relay_channel'], 'state' => 'off'];
            $commands[] = ['channel' => (int)$bat['down_relay_channel'], 'state' => 'off'];
        }

        // Insert commands into pending_commands table
        $stmt = $this->pdo->prepare("
            INSERT INTO pending_commands (device_code, command_json, status, priority)
            VALUES (:device_code, :cmd_json, 'pending', 10)
        ");

        foreach ($commands as $cmd) {
            $cmdJson = json_encode(['action' => 'relay'] + $cmd);
            $stmt->execute([
                ':device_code' => $deviceCode,
                ':cmd_json' => $cmdJson,
            ]);
        }

        $this->json([
            'ok' => true,
            'message' => 'Đã gửi lệnh ' . $action,
            'bat_id' => $batId,
            'action' => $action,
            'device_code' => $deviceCode,
            'commands_queued' => count($commands),
            'timeout' => (int)$bat['timeout_seconds'],
        ]);
    }

    private function getBat(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bats WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}