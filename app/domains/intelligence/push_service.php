<?php
declare(strict_types=1);
namespace App\Domains\Intelligence;

use PDO;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushService
{
    private string $vapid_public;
    private string $vapid_private;
    private string $vapid_subject;

    public function __construct(private PDO $pdo)
    {
        $cfg = require dirname(__DIR__, 2) . '/config.php';
        $this->vapid_public  = $cfg['vapid_public'];
        $this->vapid_private = $cfg['vapid_private'];
        $this->vapid_subject = $cfg['vapid_subject'];
    }

    // ----------------------------------------------------------------
    // Gửi thông báo đến tất cả subscriptions active
    // ----------------------------------------------------------------
    public function send_all(string $type, string $title, string $body, ?int $cycle_id = null, string $url = '/'): void
    {
        $subs = $this->get_active_subscriptions();
        if (empty($subs)) return;

        $webpush = new WebPush([
            'VAPID' => [
                'subject'    => $this->vapid_subject,
                'publicKey'  => $this->vapid_public,
                'privateKey' => $this->vapid_private,
            ]
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
        ]);

        foreach ($subs as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys'     => [
                    'p256dh' => $sub['p256dh'],
                    'auth'   => $sub['auth'],
                ],
            ]);
            $webpush->queueNotification($subscription, $payload);
        }

        $sent = $failed = 0;
        foreach ($webpush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                // Xóa subscription không hợp lệ
                if ($report->isSubscriptionExpired()) {
                    $this->deactivate_subscription($report->getRequest()->getUri()->__toString());
                }
            }
        }

        // Log
        $this->pdo->prepare("
            INSERT INTO push_notifications_log (type, title, body, cycle_id, sent_count, failed_count)
            VALUES (:type, :title, :body, :cycle_id, :sent, :failed)
        ")->execute([
            ':type'     => $type,
            ':title'    => $title,
            ':body'     => $body,
            ':cycle_id' => $cycle_id,
            ':sent'     => $sent,
            ':failed'   => $failed,
        ]);
    }

    // ----------------------------------------------------------------
    // Lưu subscription mới từ browser
    // ----------------------------------------------------------------
    public function save_subscription(string $endpoint, string $p256dh, string $auth, ?string $label = null): void
    {
        $this->pdo->prepare("
            INSERT INTO push_subscriptions (endpoint, p256dh, auth, label, active)
            VALUES (:endpoint, :p256dh, :auth, :label, 1)
            ON DUPLICATE KEY UPDATE
                p256dh = VALUES(p256dh),
                auth   = VALUES(auth),
                active = 1,
                last_used_at = NOW()
        ")->execute([
            ':endpoint' => $endpoint,
            ':p256dh'   => $p256dh,
            ':auth'     => $auth,
            ':label'    => $label,
        ]);
    }

    public function get_vapid_public(): string
    {
        return $this->vapid_public;
    }

    private function get_active_subscriptions(): array
    {
        return $this->pdo->query("SELECT * FROM push_subscriptions WHERE active=1")->fetchAll();
    }

    private function deactivate_subscription(string $endpoint): void
    {
        $this->pdo->prepare("UPDATE push_subscriptions SET active=0 WHERE endpoint=:e")
            ->execute([':e' => $endpoint]);
    }

    private function env(string $key, string $default = ''): string
    {
        $env_file = dirname(__DIR__, 4) . '/.env';
        if (file_exists($env_file)) {
            foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $_ENV[trim($k)] = trim($v);
            }
        }
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
