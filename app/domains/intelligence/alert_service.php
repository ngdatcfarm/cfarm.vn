<?php
declare(strict_types=1);
namespace App\Domains\Intelligence;

use PDO;

class AlertService
{
    public function __construct(private PDO $pdo) {}

    /**
     * Trả về danh sách cảnh báo cho một cycle
     * Mỗi alert: [type, code, message, detail, severity]
     * severity: 'danger' | 'warning' | 'info'
     */
    public function get_alerts(int $cycle_id): array
    {
        $alerts = [];

        $cycle = $this->pdo->prepare("SELECT * FROM cycles WHERE id=:id");
        $cycle->execute([':id' => $cycle_id]);
        $cycle = $cycle->fetch();
        if (!$cycle) return [];

        $day_age = (int)((strtotime('today') - strtotime($cycle['start_date'])) / 86400) + 1;

        $alerts = array_merge(
            $alerts,
            $this->check_feed_drop($cycle_id, $cycle, $day_age),
            $this->check_missing_feed($cycle_id, $day_age),
            $this->check_death_spike($cycle_id, $cycle, $day_age),
            $this->check_no_weigh($cycle_id, $day_age),
            $this->check_vaccine_remind($cycle_id)
        );

        // Sắp xếp theo severity
        $order = ['danger' => 0, 'orange' => 1, 'warning' => 2, 'info' => 3];
        usort($alerts, fn($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);

        return $alerts;
    }

    // ----------------------------------------------------------------
    // Feed drop: feed/con hôm nay giảm >30% so TB 3 ngày trước
    // ----------------------------------------------------------------
    private function check_feed_drop(int $cycle_id, array $cycle, int $day_age): array
    {
        if ($day_age < 4) return [];

        $today = date('Y-m-d');

        // Feed hôm nay
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(kg_actual), 0) FROM care_feeds
            WHERE cycle_id=:c AND DATE(recorded_at)=:d
        ");
        $stmt->execute([':c' => $cycle_id, ':d' => $today]);
        $feed_today = (float)$stmt->fetchColumn();

        if ($feed_today == 0) return []; // sẽ bị check bởi missing_feed

        // TB feed 3 ngày trước
        $stmt2 = $this->pdo->prepare("
            SELECT AVG(daily_feed) FROM (
                SELECT DATE(recorded_at) as d, SUM(kg_actual) as daily_feed
                FROM care_feeds
                WHERE cycle_id=:c
                  AND DATE(recorded_at) BETWEEN :from AND :to
                GROUP BY DATE(recorded_at)
            ) t
        ");
        $from = date('Y-m-d', strtotime('-4 days'));
        $to   = date('Y-m-d', strtotime('-1 day'));
        $stmt2->execute([':c' => $cycle_id, ':from' => $from, ':to' => $to]);
        $avg_3d = (float)$stmt2->fetchColumn();

        if ($avg_3d <= 0) return [];

        $drop_pct = ($avg_3d - $feed_today) / $avg_3d * 100;
        if ($drop_pct >= 30) {
            return [[
                'code'     => 'FEED_DROP',
                'severity' => $drop_pct >= 50 ? 'danger' : 'warning',
                'message'  => 'Lượng cám giảm đột ngột',
                'detail'   => 'Hôm nay ' . number_format($feed_today, 1) . 'kg, TB 3 ngày trước ' . number_format($avg_3d, 1) . 'kg (giảm ' . number_format($drop_pct, 0) . '%)',
            ]];
        }
        return [];
    }

    // ----------------------------------------------------------------
    // Missing feed: chưa ghi chép cho ăn hôm nay (sau 10h sáng)
    // ----------------------------------------------------------------
    private function check_missing_feed(int $cycle_id, int $day_age): array
    {
        if ($day_age < 2) return [];
        if ((int)date('H') < 10) return []; // trước 10h chưa tính

        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM care_feeds
            WHERE cycle_id=:c AND DATE(recorded_at)=:d
        ");
        $stmt->execute([':c' => $cycle_id, ':d' => $today]);
        if ((int)$stmt->fetchColumn() === 0) {
            return [[
                'code'     => 'MISSING_FEED',
                'severity' => 'warning',
                'message'  => 'Chưa ghi chép cho ăn hôm nay',
                'detail'   => 'Đã qua ' . date('H:i') . ' mà chưa có bản ghi cho ăn nào',
            ]];
        }
        return [];
    }

    // ----------------------------------------------------------------
    // Death spike: số chết hôm nay > 2× TB 7 ngày trước
    // ----------------------------------------------------------------
    private function check_death_spike(int $cycle_id, array $cycle, int $day_age): array
    {
        if ($day_age < 3) return [];

        $today = date('Y-m-d');

        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) FROM care_deaths
            WHERE cycle_id=:c AND DATE(recorded_at)=:d
        ");
        $stmt->execute([':c' => $cycle_id, ':d' => $today]);
        $dead_today = (int)$stmt->fetchColumn();

        if ($dead_today === 0) return [];

        // TB 7 ngày
        $stmt2 = $this->pdo->prepare("
            SELECT AVG(daily_dead) FROM (
                SELECT DATE(recorded_at) as d, SUM(quantity) as daily_dead
                FROM care_deaths
                WHERE cycle_id=:c
                  AND DATE(recorded_at) BETWEEN :from AND :to
                GROUP BY DATE(recorded_at)
            ) t
        ");
        $from = date('Y-m-d', strtotime('-8 days'));
        $to   = date('Y-m-d', strtotime('-1 day'));
        $stmt2->execute([':c' => $cycle_id, ':from' => $from, ':to' => $to]);
        $avg_7d = (float)$stmt2->fetchColumn();

        // Kiểm tra tỷ lệ hao hụt tích lũy
        $stmt3 = $this->pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM care_deaths WHERE cycle_id=:c");
        $stmt3->execute([':c' => $cycle_id]);
        $total_dead  = (int)$stmt3->fetchColumn();
        $death_rate  = $cycle['initial_quantity'] > 0 ? $total_dead / $cycle['initial_quantity'] * 100 : 0;

        $alerts = [];

        if ($avg_7d > 0 && $dead_today >= $avg_7d * 2) {
            $alerts[] = [
                'code'     => 'DEATH_SPIKE',
                'severity' => 'danger',
                'message'  => 'Hao hụt tăng đột biến',
                'detail'   => 'Hôm nay ' . $dead_today . ' con, TB 7 ngày ' . number_format($avg_7d, 1) . ' con/ngày',
            ];
        }

        if ($death_rate > 5) {
            $alerts[] = [
                'code'     => 'HIGH_DEATH_RATE',
                'severity' => $death_rate > 10 ? 'danger' : 'warning',
                'message'  => 'Tỷ lệ hao hụt vượt ngưỡng',
                'detail'   => number_format($death_rate, 1) . '% tổng đàn (' . $total_dead . '/' . $cycle['initial_quantity'] . ' con)',
            ];
        }

        return $alerts;
    }

    // ----------------------------------------------------------------
    // Vaccine remind: sắp đến ngày tiêm
    // ----------------------------------------------------------------
    private function check_vaccine_remind(int $cycle_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM vaccine_schedules
            WHERE cycle_id = :c
              AND done = 0
              AND skipped = 0
              AND scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL remind_days DAY)
            ORDER BY scheduled_date ASC
        ");
        $stmt->execute([':c' => $cycle_id]);
        $upcoming = $stmt->fetchAll();

        $alerts = [];
        foreach ($upcoming as $v) {
            $days_left = (int)((strtotime($v['scheduled_date']) - strtotime('today')) / 86400);
            $when = $days_left === 0 ? 'Hôm nay' : 'Còn ' . $days_left . ' ngày';
            $alerts[] = [
                'code'     => 'VACCINE_REMIND',
                'severity' => $days_left === 0 ? 'orange' : 'info',
                'message'  => 'Lịch tiêm: ' . $v['vaccine_name'],
                'detail'   => $when . ' · ' . date('d/m/Y', strtotime($v['scheduled_date'])),
            ];
        }
        return $alerts;
    }

    // ----------------------------------------------------------------
    // No weigh: chưa cân > 7 ngày
    // ----------------------------------------------------------------
    private function check_no_weigh(int $cycle_id, int $day_age): array
    {
        if ($day_age < 8) return [];

        $stmt = $this->pdo->prepare("SELECT MAX(day_age) FROM weight_sessions WHERE cycle_id=:c");
        $stmt->execute([':c' => $cycle_id]);
        $last = (int)$stmt->fetchColumn();
        $days_since = $day_age - $last;

        if ($days_since > 7) {
            return [[
                'code'     => 'NO_WEIGH',
                'severity' => 'info',
                'message'  => 'Chưa cân gà ' . $days_since . ' ngày',
                'detail'   => 'Lần cân gần nhất: ngày ' . $last . ' của chu kỳ',
            ]];
        }
        return [];
    }
}
