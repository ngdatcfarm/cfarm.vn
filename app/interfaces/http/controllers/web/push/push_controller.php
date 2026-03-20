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
        $type = $body['type'] ?? null;

        if (!$type) {
            $this->json(false, 'Thiếu type');
            return;
        }

        // Acknowledge tất cả notifications chưa ack của type này
        $stmt = $this->pdo->prepare("
            UPDATE push_notifications_log
            SET acknowledged_at = NOW()
            WHERE type = :type AND acknowledged_at IS NULL
        ");
        $stmt->execute([':type' => $type]);

        $this->json(true, 'Đã xác nhận', ['updated' => $stmt->rowCount()]);
    }

    // POST /push/test
    public function test_push(array $vars): void
    {
        $alert = new \App\Domains\Intelligence\AlertService($this->pdo);

        $cycles = $this->pdo->query("
            SELECT c.*, b.name AS barn_name
            FROM cycles c JOIN barns b ON c.barn_id = b.id
            WHERE c.status = 'active'
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $sent = 0;
        foreach ($cycles as $c) {
            $alerts = $alert->get_alerts((int)$c['id']);
            foreach ($alerts as $a) {
                if ($a['severity'] === 'info') continue;
                $this->push->send_all(
                    $a['code'],
                    '🚨 ' . $c['barn_name'] . ' · ' . $c['code'],
                    $a['message'] . ' — ' . $a['detail'],
                    (int)$c['id'],
                    '/cycles/' . $c['id']
                );
                $sent++;
            }
        }

        // Gửi test notification nếu không có cảnh báo nào
        if ($sent === 0) {
            $this->push->send_all(
                'TEST',
                '✅ CFarm Test',
                'Thông báo hoạt động bình thường! ' . date('H:i d/m/Y'),
                null,
                '/'
            );
            $sent = 1;
        }

        $this->json(true, "Đã gửi $sent thông báo");
    }
}