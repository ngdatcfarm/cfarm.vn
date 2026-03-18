<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use PDO;

/**
 * IoT Firmware Controller
 */
class FirmwareController
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
     * GET /settings/iot/firmwares
     */
    public function index(array $vars): void
    {
        // Get device types
        $device_types = $this->pdo->query("
            SELECT dt.*, 
                   (SELECT COUNT(*) FROM device_firmwares WHERE device_type_id = dt.id AND is_active = 1) as firmware_count
            FROM device_types dt
            ORDER BY dt.name
        ")->fetchAll(PDO::FETCH_OBJ);

        // Get firmwares with device type info
        $firmwares = $this->pdo->query("
            SELECT f.*, dt.name as type_name
            FROM device_firmwares f
            JOIN device_types dt ON dt.id = f.device_type_id
            ORDER BY dt.name, f.version DESC
        ")->fetchAll(PDO::FETCH_OBJ);

        require view_path('iot/firmwares.php');
    }

    /**
     * POST /settings/iot/firmware/store
     */
    public function store(array $vars): void
    {
        $name = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $device_type_id = (int)($_POST['device_type_id'] ?? 0);
        $code = $_POST['code'] ?? '';
        $is_latest = isset($_POST['is_latest']) ? 1 : 0;

        if (!$name || !$version || !$device_type_id || !$code) {
            header('Location: /settings/iot/firmwares?error=missing_fields');
            exit;
        }

        // If setting as latest, unset others for this device type
        if ($is_latest) {
            $this->pdo->prepare("UPDATE device_firmwares SET is_latest = 0 WHERE device_type_id = ?")
                ->execute([$device_type_id]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO device_firmwares (name, version, description, device_type_id, code, is_active, is_latest, created_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
        ");
        $stmt->execute([$name, $version, $description, $device_type_id, $code, $is_latest]);

        header('Location: /settings/iot/firmwares?saved=1');
        exit;
    }

    /**
     * POST /settings/iot/firmware/{id}/update
     */
    public function update(array $vars): void
    {
        $id = (int)$vars['id'];
        
        $name = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $code = $_POST['code'] ?? '';
        $is_latest = isset($_POST['is_latest']) ? 1 : 0;

        if (!$name || !$version || !$code) {
            header('Location: /settings/iot/firmwares?error=missing_fields');
            exit;
        }

        // Get current device_type_id
        $stmt = $this->pdo->prepare("SELECT device_type_id FROM device_firmwares WHERE id = ?");
        $stmt->execute([$id]);
        $current_type = $stmt->fetchColumn();

        // If changing to latest, unset others
        if ($is_latest && $current_type) {
            $this->pdo->prepare("UPDATE device_firmwares SET is_latest = 0 WHERE device_type_id = ? AND id != ?")
                ->execute([$current_type, $id]);
        }

        $stmt = $this->pdo->prepare("
            UPDATE device_firmwares 
            SET name = ?, version = ?, description = ?, code = ?, is_latest = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $version, $description, $code, $is_latest, $id]);

        header('Location: /settings/iot/firmwares?saved=1');
        exit;
    }

    /**
     * POST /settings/iot/firmware/{id}/delete
     */
    public function delete(array $vars): void
    {
        $id = (int)$vars['id'];
        
        // Get device_type_id before delete
        $stmt = $this->pdo->prepare("SELECT device_type_id FROM device_firmwares WHERE id = ?");
        $stmt->execute([$id]);
        $device_type_id = $stmt->fetchColumn();

        $this->pdo->prepare("DELETE FROM device_firmwares WHERE id = ?")->execute([$id]);

        // If deleted was latest, set another as latest
        if ($device_type_id) {
            $stmt = $this->pdo->prepare("
                UPDATE device_firmwares SET is_latest = 1 
                WHERE device_type_id = ? AND is_active = 1 
                LIMIT 1
            ");
            $stmt->execute([$device_type_id]);
        }

        header('Location: /settings/iot/firmwares');
        exit;
    }

    /**
     * POST /settings/iot/firmware/{id}/toggle
     */
    public function toggle(array $vars): void
    {
        $id = (int)$vars['id'];
        
        $stmt = $this->pdo->prepare("UPDATE device_firmwares SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);

        $this->json(['ok' => true]);
    }

    /**
     * GET /settings/iot/firmware/{id}/edit
     */
    public function edit(array $vars): void
    {
        $id = (int)$vars['id'];
        
        $stmt = $this->pdo->prepare("SELECT * FROM device_firmwares WHERE id = ?");
        $stmt->execute([$id]);
        $firmware = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$firmware) {
            http_response_code(404);
            echo 'Firmware not found';
            exit;
        }

        $device_types = $this->pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll(PDO::FETCH_OBJ);

        require view_path('iot/firmware_edit.php');
    }

    /**
     * GET /api/firmware/{device_type_id}/latest - For OTA
     */
    public function ota_check(array $vars): void
    {
        $device_type_id = (int)$vars['device_type'];
        
        $stmt = $this->pdo->prepare("
            SELECT id, name, version, description 
            FROM device_firmwares 
            WHERE device_type_id = ? AND is_active = 1 AND is_latest = 1 
            LIMIT 1
        ");
        $stmt->execute([$device_type_id]);
        $firmware = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($firmware) {
            $this->json([
                'ok' => true,
                'version' => $firmware['version'],
                'name' => $firmware['name'],
                'description' => $firmware['description'],
                'download_url' => '/api/firmware/download/' . $firmware['id']
            ]);
        } else {
            $this->json(['ok' => false, 'message' => 'No firmware found'], 404);
        }
    }

    /**
     * GET /api/firmware/download/{id} - Download firmware
     */
    public function ota_download(array $vars): void
    {
        $id = (int)$vars['id'];
        
        $stmt = $this->pdo->prepare("SELECT code, name, version FROM device_firmwares WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $firmware = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$firmware) {
            http_response_code(404);
            echo 'Firmware not found';
            exit;
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $firmware['name'] . '_' . $firmware['version'] . '.ino"');
        echo $firmware['code'];
        exit;
    }
}
