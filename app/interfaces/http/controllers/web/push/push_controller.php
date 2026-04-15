<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Push;

use PDO;
use App\Domains\Intelligence\PushService;

class PushController
{
    private PushService $push;

    public function __construct(private PDO $pdo)
    {
        $this->push = new PushService($pdo);
    }

    private function json(bool $ok, string $msg, array $data = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $data));
        exit;
    }

    // GET /push/vapid-public-key
    public function vapid_key(array $vars): void
    {
        $this->json(true, 'ok', ['key' => $this->push->get_vapid_public()]);
    }

    // POST /push/subscribe
    public function subscribe(array $vars): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body['endpoint']) || empty($body['keys']['p256dh']) || empty($body['keys']['auth'])) {
            $this->json(false, 'Thiếu thông tin subscription');
            return;
        }
        $this->push->save_subscription(
            $body['endpoint'],
            $body['keys']['p256dh'],
            $body['keys']['auth'],
            $body['label'] ?? null
        );
        $this->json(true, 'Đã đăng ký nhận thông báo');
    }

    // POST /push/unsubscribe
    public function unsubscribe(array $vars): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body['endpoint'])) {
            $this->json(false, 'Thiếu endpoint'); return;
        }
        $this->pdo->prepare("UPDATE push_subscriptions SET active=0 WHERE endpoint=:e")
            ->execute([':e' => $body['endpoint']]);
        $this->json(true, 'Đã hủy đăng ký');
    }
    // POST /push/acknowledge
    public function acknowledge(array $vars): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = $body['id'] ?? null;
        $type = $body['type'] ?? null;

        if (!$id && !$type) {
            $this->json(false, 'Thiếu id hoặc type');
            return;
        }

        if ($id) {
            // Acknowledge 1 notification cụ thể + update device tương ứng
            $stmt = $this->pdo->prepare("
                SELECT id, body FROM push_notifications_log
                WHERE id = :id AND type = 'DEVICE_OFFLINE' AND acknowledged_at IS NULL
            ");
            $stmt->execute([':id' => $id]);
            $notif = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$notif) {
                $this->json(false, 'Không tìm thấy hoặc đã xác nhận');
                return;
            }

            $this->pdo->prepare("UPDATE push_notifications_log SET acknowledged_at = NOW() WHERE id = :id")
                ->execute([':id' => $id]);

            // Trích device_code từ body: "Tên (DEVICE_CODE) offline ..."
            if (preg_match('/\(([A-Za-z0-9_-]+)\)\s+offline/', $notif['body'], $m)) {
                $this->pdo->prepare("
                    UPDATE devices SET last_offline_alert_at = NOW()
                    WHERE device_code = :code AND is_online = 0
                ")->execute([':code' => $m[1]]);
            }

            $this->json(true, 'Đã xác nhận', ['updated' => 1]);
        } else {
            // Fallback: acknowledge theo type (từ sw.js push notification action)
            $stmt = $this->pdo->prepare("
                UPDATE push_notifications_log
                SET acknowledged_at = NOW()
                WHERE type = :type AND acknowledged_at IS NULL
            ");
            $stmt->execute([':type' => $type]);

            if ($type === 'DEVICE_OFFLINE') {
                $this->pdo->exec("
                    UPDATE devices SET last_offline_alert_at = NOW()
                    WHERE is_online = 0 AND alert_offline = 1
                ");
            }

            $this->json(true, 'Đã xác nhận', ['updated' => $stmt->rowCount()]);
        }
    }

    // POST /push/test
    public function test_push(array $vars): void
    {
        // Gửi test notification trực tiếp
        $this->push->send_all(
            'TEST',
            '✅ CFarm Test',
            'Thông báo hoạt động bình thường! ' . date('H:i d/m/Y'),
            null,
            '/'
        );

        $this->json(true, "Đã gửi thông báo test");
    }
}