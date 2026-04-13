<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use PDO;

/**
 * BatControlController - Serves the bat control page (/iot/control/{barn_id})
 *
 * Loads bats data (synced from local), ESP32 devices, and recent bat_logs.
 */
class BatControlController
{
    public function __construct(private PDO $pdo) {}

    /**
     * GET /iot/control/{barn_id} - Bat control page
     */
    public function control_page(array $vars): void
    {
        $barn_id = $vars['barn_id'];

        // Load barn
        $stmt = $this->pdo->prepare("SELECT * FROM barns WHERE id = ?");
        $stmt->execute([$barn_id]);
        $barn = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$barn) {
            http_response_code(404);
            echo 'Barn not found';
            exit;
        }

        // Load bats for this barn (synced from local)
        $stmt = $this->pdo->prepare("SELECT * FROM bats WHERE barn_id = ? ORDER BY id");
        $stmt->execute([$barn_id]);
        $bats_rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Load devices (ESP32 relay controllers) - all relay types including relay_4ch, relay_8ch, mixed
        $stmt = $this->pdo->query("
            SELECT d.* FROM devices d
            LEFT JOIN device_types dt ON dt.id = d.device_type_id
            WHERE d.barn_id = :barn_id
            AND (dt.code IN ('relay_4ch', 'relay_8ch', 'mixed')
                 OR dt.device_class IN ('relay', 'esp32', 'esp32_relay')
                 OR d.device_type_id IS NULL)
            ORDER BY d.name
        ");
        $stmt->execute([':barn_id' => $barn_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Determine selected ESP32 device (first bat with device_id, or user selection)
        $esp_device_id = null;
        foreach ($bats_rows as $bat) {
            if ($bat->device_id) {
                $esp_device_id = $bat->device_id;
                break;
            }
        }

        // Build $bats array matching local's bats.js structure
        $bats = [];
        $bat_icons = [
            'left_top' => '↖️', 'left_bottom' => '↙️',
            'right_top' => '↗️', 'right_bottom' => '↘️',
        ];
        foreach ($bats_rows as $row) {
            $code = $row->code ?? 'unknown';
            $bats[] = [
                'id' => (int)$row->id,
                'barn_id' => $row->barn_id,
                'code' => $code,
                'name' => $row->name ?? $code,
                'icon' => $bat_icons[$code] ?? '🪟',
                'device_id' => $row->device_id ? (int)$row->device_id : null,
                'up_channel' => (int)($row->up_relay_channel ?? 0),
                'down_channel' => (int)($row->down_relay_channel ?? 0),
                'position' => $row->position ?? 'stopped',
                'moving_state' => $row->position ?? 'stopped',
                'auto_enabled' => (bool)($row->auto_enabled ?? false),
                'timeout_seconds' => (int)($row->timeout_seconds ?? 210),
            ];
        }

        // Load recent bat_logs (last 20)
        $stmt = $this->pdo->prepare("
            SELECT bl.*, b.name as bat_name, b.code as bat_code
            FROM bat_logs bl
            JOIN bats b ON b.id = bl.bat_id
            WHERE b.barn_id = ?
            ORDER BY bl.started_at DESC
            LIMIT 20
        ");
        $stmt->execute([$barn_id]);
        $logs_rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $logs = [];
        foreach ($logs_rows as $log) {
            $logs[] = [
                'time' => $log->started_at ? date('H:i d/m', strtotime($log->started_at)) : '-',
                'bat_name' => $log->bat_name ?? $log->bat_code,
                'action' => $log->action,
                'duration' => $log->duration_seconds !== null ? $log->duration_seconds . 's' : '-',
            ];
        }

        require view_path('iot/control.php');
    }
}
