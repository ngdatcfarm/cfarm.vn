<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use App\Domains\IoT\Services\MqttService;
use PDO;

/**
 * IoT Curtain Controller - Cloud version
 *
 * Cloud DB schema differs from local:
 * - up_channel/down_channel are INT (direct channel numbers), not FK
 * - No device_channels JOIN needed
 * - No moving_state tracking in DB (ESP32 handles it)
 * - current_position is INT (0-100)
 */
class CurtainController
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
     * GET /iot/control - Điều khiển tất cả các chuồng
     */
    public function control_all(array $vars): void
    {
        $barns = $this->pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(PDO::FETCH_OBJ);

        $all_bats = [];
        foreach ($barns as $barn) {
            $stmt = $this->pdo->prepare("
                SELECT b.*, d.is_online, d.device_code
                FROM bats b
                LEFT JOIN devices d ON d.id = b.device_id
                WHERE b.barn_id = :barn_id
                ORDER BY b.id
            ");
            $stmt->execute([':barn_id' => $barn->id]);
            $bats = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($bats as $bat) {
                $bat->real_position = ($bat->position ?? 'stopped') === 'stopped' ? 0 : 50;
                $bat->moving_state = $bat->position ?? 'stopped';
            }

            $all_bats[$barn->id] = $bats;
        }

        require view_path('iot/control_all.php');
    }

    /**
     * GET /iot/control/{barn_id} - Điều khiển theo chuồng
     */
    public function control_page(array $vars): void
    {
        $barn_id = $vars['barn_id']; // String (varchar) on cloud

        $barn = $this->pdo->prepare("SELECT * FROM barns WHERE id = ?");
        $barn->execute([$barn_id]);
        $barn = $barn->fetch(PDO::FETCH_OBJ);

        if (!$barn) {
            http_response_code(404);
            echo 'Barn not found';
            exit;
        }

        $stmt = $this->pdo->prepare("
            SELECT cc.*, d.mqtt_topic, d.is_online, d.device_code
            FROM curtain_configs cc
            JOIN devices d ON d.id = cc.device_id
            WHERE cc.barn_id = :barn_id
            ORDER BY cc.id
        ");
        $stmt->execute([':barn_id' => $barn_id]);
        $curtains = $stmt->fetchAll(PDO::FETCH_OBJ);

        foreach ($curtains as $cur) {
            $cur->real_position = (int)($cur->current_position ?? 0);
            $cur->moving_state = 'idle';
        }

        require view_path('iot/control.php');
    }

    /**
     * POST /iot/curtain/{id}/move - Di chuyển bạt đến vị trí %
     */
    public function curtain_move(array $vars): void
    {
        $id = (int)$vars['id'];
        $target_pct = max(0, min(100, (int)($_POST['target_pct'] ?? 0)));

        $c = $this->getCurtainFull($id);
        if (!$c) {
            $this->json(['ok' => false, 'message' => 'Curtain not found'], 404);
        }

        try {
            $currentPos = (int)($c['current_position'] ?? 0);

            // Nếu đã ở vị trí
            $diff = $target_pct - $currentPos;
            if ($diff == 0) {
                $this->json(['ok' => true, 'message' => 'Already at position', 'position' => $currentPos, 'duration' => 0]);
            }

            // Tính hướng và thời gian
            if ($diff > 0) {
                // Mở (position tăng)
                $duration = abs($diff) / 100 * (float)($c['full_down_seconds'] ?? 60);
                $channel = (int)$c['down_channel'];
                $dir = 'down';
            } else {
                // Đóng (position giảm)
                $duration = abs($diff) / 100 * (float)($c['full_up_seconds'] ?? 60);
                $channel = (int)$c['up_channel'];
                $dir = 'up';
            }

            // Gửi MQTT via cloud
            $deviceCode = $c['device_code'];
            $sent = $this->mqtt->sendCurtainPositionCloud($deviceCode, $target_pct);

            if (!$sent) {
                $this->json(['ok' => false, 'message' => 'Không gửi được lệnh MQTT']);
            }

            // Cập nhật vị trí hiện tại (optimistic)
            $this->pdo->prepare("
                UPDATE curtain_configs
                SET current_position = :pos
                WHERE id = :id
            ")->execute([':pos' => $target_pct, ':id' => $id]);

            $this->json([
                'ok'        => true,
                'position'  => $currentPos,
                'target'    => $target_pct,
                'direction' => $dir,
                'duration'  => round($duration, 1),
            ]);
        } catch (\Throwable $e) {
            error_log("[CurtainController] curtain_move error: " . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /iot/curtain/{id}/stop - Dừng bạt
     */
    public function curtain_stop(array $vars): void
    {
        $id = (int)$vars['id'];

        $c = $this->getCurtainFull($id);
        if (!$c) {
            $this->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        try {
            // Gửi lệnh dừng relay cho cả up và down channel
            $deviceCode = $c['device_code'];
            $this->mqtt->sendRelayOffCloud($deviceCode, (int)$c['up_channel']);
            $this->mqtt->sendRelayOffCloud($deviceCode, (int)$c['down_channel']);

            $this->json(['ok' => true, 'message' => 'Stopped']);
        } catch (\Throwable $e) {
            error_log("[CurtainController] curtain_stop error: " . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /iot/curtain/{id}/status - Lấy trạng thái bạt
     */
    public function curtain_status(array $vars): void
    {
        $id = (int)$vars['id'];

        $c = $this->getCurtainFull($id);
        if (!$c) {
            $this->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        $this->json([
            'ok' => true,
            'position' => (int)($c['current_position'] ?? 0),
            'moving_state' => 'idle',
        ]);
    }

    /**
     * Lấy curtain config đầy đủ
     */
    private function getCurtainFull(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cc.*, d.mqtt_topic, d.is_online, d.device_code
            FROM curtain_configs cc
            JOIN devices d ON d.id = cc.device_id
            WHERE cc.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
