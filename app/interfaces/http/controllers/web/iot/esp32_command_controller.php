<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use PDO;

/**
 * ESP32 Command Controller - Queue commands for ESP32 devices via Cloud MQTT
 *
 * Flow: Cloud App → pending_commands table → Python MQTT Publisher → Cloud MQTT → ESP32
 */
class Esp32CommandController
{
    public function __construct(private PDO $pdo) {}

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * POST /api/esp32/{device_code}/command
     * Send command to ESP32 device via MQTT
     */
    public function command(array $vars): void
    {
        $deviceCode = $vars['device_code'];
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['action'])) {
            $this->json(['ok' => false, 'message' => 'Missing action'], 400);
        }

        // Verify device exists
        $stmt = $this->pdo->prepare("SELECT id, device_code FROM devices WHERE device_code = ?");
        $stmt->execute([$deviceCode]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            $this->json(['ok' => false, 'message' => 'Device not found'], 404);
        }

        // Build command JSON
        $commandJson = json_encode($data);

        // Insert into pending_commands
        $stmt = $this->pdo->prepare("
            INSERT INTO pending_commands (device_code, command_json, status, priority)
            VALUES (:device_code, :cmd_json, 'pending', :priority)
        ");
        $stmt->execute([
            ':device_code' => $deviceCode,
            ':cmd_json' => $commandJson,
            ':priority' => $data['priority'] ?? 0,
        ]);

        $this->json([
            'ok' => true,
            'message' => 'Command queued',
            'device_code' => $deviceCode,
            'command_id' => $this->pdo->lastInsertId(),
            'action' => $data['action'],
        ]);
    }

    /**
     * POST /api/esp32/{device_code}/relay
     * Convenience method for relay control
     */
    public function relay(array $vars): void
    {
        $deviceCode = $vars['device_code'];
        $data = json_decode(file_get_contents('php://input'), true);

        $channel = $data['channel'] ?? null;
        $state = $data['state'] ?? 'off';

        if (!$channel) {
            $this->json(['ok' => false, 'message' => 'Missing channel'], 400);
        }

        $this->command($vars); // Reuse command method with relay action
    }

    /**
     * GET /api/esp32/{device_code}/status
     * Get device status from last heartbeat (via synced data)
     */
    public function status(array $vars): void
    {
        $deviceCode = $vars['device_code'];

        // Get latest heartbeat data from device_states or similar
        $stmt = $this->pdo->prepare("
            SELECT ds.*, d.name as device_name
            FROM device_states ds
            JOIN devices d ON d.id = ds.device_id
            WHERE d.device_code = ?
            ORDER BY ds.recorded_at DESC
            LIMIT 1
        ");
        $stmt->execute([$deviceCode]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$state) {
            // Try to get from devices table directly
            $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE device_code = ?");
            $stmt->execute([$deviceCode]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) {
                $this->json(['ok' => false, 'message' => 'Device not found'], 404);
            }
            $this->json([
                'ok' => true,
                'device' => $device,
                'online' => false,
            ]);
            return;
        }

        $this->json([
            'ok' => true,
            'device_code' => $deviceCode,
            'device_name' => $state['device_name'],
            'online' => true,
            'last_heartbeat' => $state['recorded_at'],
        ]);
    }

    /**
     * GET /api/esp32/{device_code}/commands
     * Poll pending commands for device (for ESP32 HTTP fallback)
     */
    public function poll_commands(array $vars): void
    {
        $deviceCode = $vars['device_code'];

        // Get pending commands for this device
        $stmt = $this->pdo->prepare("
            SELECT id, command_json, created_at
            FROM pending_commands
            WHERE device_code = ? AND status = 'pending'
            ORDER BY priority DESC, created_at ASC
            LIMIT 10
        ");
        $stmt->execute([$deviceCode]);
        $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark as sent
        if ($commands) {
            $ids = array_column($commands, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                UPDATE pending_commands
                SET status = 'sent', sent_at = NOW()
                WHERE id IN ({$placeholders})
            ");
            $stmt->execute($ids);
        }

        $this->json([
            'ok' => true,
            'device_code' => $deviceCode,
            'commands' => $commands,
            'count' => count($commands),
        ]);
    }
}