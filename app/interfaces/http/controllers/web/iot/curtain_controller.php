<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\IoT;

use App\Domains\IoT\Services\MqttService;
use PDO;

/**
 * IoT Curtain Controller - Điều khiển bạt
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
     * Tính vị trí thực tế của bạt
     */
    private function calculateRealPosition(object $c): int
    {
        if ($c->moving_state === 'idle' || !$c->moving_started_at) {
            return (int)$c->current_position_pct;
        }

        $elapsed = time() - strtotime($c->moving_started_at);
        $duration = (float)$c->moving_duration_seconds;

        if ($duration <= 0) return (int)$c->current_position_pct;

        $ratio = min(1.0, $elapsed / $duration);
        $from = (int)$c->current_position_pct;
        $to = (int)$c->moving_target_pct;
        $diff = $to - $from;

        return max(0, min(100, $from + (int)round($diff * $ratio)));
    }

    /**
     * Lấy curtain config đầy đủ
     */
    private function getCurtainFull(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cc.*,
                   d.mqtt_topic,
                   uc.channel_number as up_channel,
                   dc.channel_number as down_channel,
                   uc.gpio_pin as up_gpio,
                   dc.gpio_pin as down_gpio,
                   d.is_online
            FROM curtain_configs cc
            JOIN devices d ON d.id = cc.device_id
            LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
            LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
            WHERE cc.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Dừng bạt đang chạy
     */
    private function stopCurtain(array $c): int
    {
        $realPos = $this->calculateRealPosition((object)$c);

        // Gửi lệnh dừng
        if ($c['moving_state'] !== 'idle') {
            $channel = $c['moving_state'] === 'moving_up' ? $c['up_channel'] : $c['down_channel'];
            $this->mqtt->sendRelayOff($c['mqtt_topic'], (int)$channel);
        }

        // Cập nhật DB
        $this->pdo->prepare("
            UPDATE curtain_configs
            SET current_position_pct = :pos,
                moving_state = 'idle',
                moving_target_pct = NULL,
                moving_started_at = NULL,
                moving_duration_seconds = NULL
            WHERE id = :id
        ")->execute([':pos' => $realPos, ':id' => $c['id']]);

        return $realPos;
    }

    /**
     * GET /iot/control - Điều khiển tất cả các chuồng
     */
    public function control_all(array $vars): void
    {
        $barns = $this->pdo->query("SELECT * FROM barns ORDER BY number")->fetchAll(PDO::FETCH_OBJ);

        $all_curtains = [];
        foreach ($barns as $barn) {
            $stmt = $this->pdo->prepare("
                SELECT cc.*,
                       d.mqtt_topic, d.is_online,
                       uc.channel_number as up_channel,
                       dc.channel_number as down_channel
                FROM curtain_configs cc
                JOIN devices d ON d.id = cc.device_id
                LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
                LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
                WHERE cc.barn_id = :barn_id
                ORDER BY cc.id
            ");
            $stmt->execute([':barn_id' => $barn->id]);
            $curtains = $stmt->fetchAll(PDO::FETCH_OBJ);
            
            foreach ($curtains as $cur) {
                $cur->real_position = $this->calculateRealPosition($cur);
                $cur->barn_name = $barn->name;
            }
            
            $all_curtains[$barn->id] = $curtains;
        }

        require view_path('iot/control_all.php');
    }

    /**
     * GET /iot/control/{barn_id} - Điều khiển theo chuồng
     */
    public function control_page(array $vars): void
    {
        $barn_id = (int)$vars['barn_id'];
        
        $barn = $this->pdo->prepare("SELECT * FROM barns WHERE id = ?");
        $barn->execute([$barn_id]);
        $barn = $barn->fetch(PDO::FETCH_OBJ);
        
        if (!$barn) {
            http_response_code(404);
            echo 'Barn not found';
            exit;
        }

        $stmt = $this->pdo->prepare("
            SELECT cc.*,
                   d.mqtt_topic, d.is_online,
                   uc.channel_number as up_channel,
                   dc.channel_number as down_channel
            FROM curtain_configs cc
            JOIN devices d ON d.id = cc.device_id
            LEFT JOIN device_channels uc ON uc.id = cc.up_channel_id
            LEFT JOIN device_channels dc ON dc.id = cc.down_channel_id
            WHERE cc.barn_id = :barn_id
            ORDER BY cc.id
        ");
        $stmt->execute([':barn_id' => $barn_id]);
        $curtains = $stmt->fetchAll(PDO::FETCH_OBJ);

        foreach ($curtains as $cur) {
            $cur->real_position = $this->calculateRealPosition($cur);
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

        // Dừng bạt nếu đang chạy
        $currentPos = $this->stopCurtain($c);

        // Nếu đã ở vị trí
        $diff = $target_pct - $currentPos;
        if ($diff == 0) {
            $this->json(['ok' => true, 'message' => 'Already at position', 'position' => $currentPos, 'duration' => 0]);
        }

        // Tính hướng và thời gian
        if ($diff > 0) {
            // Mở (position tăng)
            $duration = abs($diff) / 100 * (float)$c['full_down_seconds'];
            $channel = (int)$c['down_channel'];
            $dir = 'down';
            $mstate = 'moving_down';
        } else {
            // Đóng (position giảm)
            $duration = abs($diff) / 100 * (float)$c['full_up_seconds'];
            $channel = (int)$c['up_channel'];
            $dir = 'up';
            $mstate = 'moving_up';
        }

        // Gửi MQTT
        $sent = $this->mqtt->sendRelayOnWithDuration($c['mqtt_topic'], $channel, (int)$duration);
        
        if (!$sent) {
            $this->json(['ok' => false, 'message' => 'MQTT send failed']);
        }

        // Lưu trạng thái
        $this->pdo->prepare("
            UPDATE curtain_configs
            SET moving_state = :mstate,
                moving_target_pct = :target,
                moving_started_at = NOW(),
                moving_duration_seconds = :dur
            WHERE id = :id
        ")->execute([
            ':mstate' => $mstate,
            ':target' => $target_pct,
            ':dur'    => round($duration, 1),
            ':id'     => $id,
        ]);

        // Log command
        $cycle_id = $this->getActiveCycleId((int)$c['barn_id']);
        $this->pdo->prepare("
            INSERT INTO device_commands (device_id, channel_id, command_type, payload, source, status, sent_at, barn_id, cycle_id)
            VALUES (:did, :chid, 'set_position', :payload, 'manual', 'sent', NOW(), :barn_id, :cycle_id)
        ")->execute([
            ':did'     => $c['device_id'],
            ':chid'    => $channel,
            ':payload' => json_encode(['from' => $currentPos, 'to' => $target_pct, 'duration' => round($duration, 1)]),
            ':barn_id' => $c['barn_id'],
            ':cycle_id' => $cycle_id,
        ]);

        $this->json([
            'ok'        => true,
            'position'  => $currentPos,
            'target'    => $target_pct,
            'direction' => $dir,
            'duration'  => round($duration, 1),
        ]);
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

        $realPos = $this->stopCurtain($c);

        $this->json(['ok' => true, 'message' => 'Stopped', 'position' => $realPos]);
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

        $realPos = $this->calculateRealPosition((object)$c);

        $this->json([
            'ok' => true,
            'position' => $realPos,
            'moving_state' => $c['moving_state'],
            'target_pct' => $c['moving_target_pct'],
        ]);
    }

    private function getActiveCycleId(int $barn_id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM cycles WHERE barn_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$barn_id]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
