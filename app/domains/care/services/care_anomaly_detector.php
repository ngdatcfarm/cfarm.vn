<?php
declare(strict_types=1);
namespace App\Domains\Care\Services;

use PDO;

/**
 * Phát hiện giá trị bất thường và trùng lặp khi nhập liệu care.
 * Trả về mảng warnings (rỗng nếu OK). User có thể confirm để bỏ qua.
 */
class CareAnomalyDetector
{
    public function __construct(private PDO $pdo) {}

    /**
     * Kiểm tra feed: số bao bất thường + trùng lặp
     */
    public function check_feed(int $cycle_id, int $feed_type_id, float $bags, string $session, string $recorded_at): array
    {
        $warnings = [];

        // 1. So sánh với 7 ngày gần nhất cùng cycle
        $avg = $this->pdo->prepare("
            SELECT AVG(bags) AS avg_bags, MAX(bags) AS max_bags, COUNT(*) AS cnt
            FROM care_feeds
            WHERE cycle_id = :cid AND feed_type_id = :ftid
              AND recorded_at >= DATE_SUB(:rat, INTERVAL 7 DAY)
        ");
        $avg->execute([':cid' => $cycle_id, ':ftid' => $feed_type_id, ':rat' => $recorded_at]);
        $stats = $avg->fetch();

        if ($stats && (int)$stats['cnt'] >= 3 && (float)$stats['avg_bags'] > 0) {
            $ratio = $bags / (float)$stats['avg_bags'];
            if ($ratio >= 3) {
                $warnings[] = "Số bao ({$bags}) cao gấp " . round($ratio, 1) . "x so với trung bình 7 ngày (" . round($stats['avg_bags'], 1) . " bao)";
            }
            if ($ratio <= 0.2 && $bags > 0) {
                $warnings[] = "Số bao ({$bags}) thấp hơn nhiều so với trung bình 7 ngày (" . round($stats['avg_bags'], 1) . " bao)";
            }
        }

        // 2. Duplicate: cùng cycle, cùng feed_type, cùng session, cùng ngày
        $date = substr($recorded_at, 0, 10);
        $dup = $this->pdo->prepare("
            SELECT COUNT(*) FROM care_feeds
            WHERE cycle_id = :cid AND feed_type_id = :ftid
              AND DATE(recorded_at) = :date
              AND session = :session
        ");
        $dup->execute([':cid' => $cycle_id, ':ftid' => $feed_type_id, ':date' => $date, ':session' => $session]);
        if ((int)$dup->fetchColumn() > 0) {
            $warnings[] = "Đã có bữa ăn cùng mã cám, cùng buổi trong ngày này. Có thể bị trùng?";
        }

        return $warnings;
    }

    /**
     * Kiểm tra death: số con chết bất thường
     */
    public function check_death(int $cycle_id, int $quantity, string $recorded_at): array
    {
        $warnings = [];

        // So sánh với 7 ngày gần nhất
        $avg = $this->pdo->prepare("
            SELECT AVG(daily_dead) AS avg_dead, MAX(daily_dead) AS max_dead FROM (
                SELECT DATE(recorded_at) AS d, SUM(quantity) AS daily_dead
                FROM care_deaths
                WHERE cycle_id = :cid
                  AND recorded_at >= DATE_SUB(:rat, INTERVAL 7 DAY)
                  AND DATE(recorded_at) < DATE(:rat)
                GROUP BY DATE(recorded_at)
            ) sub
        ");
        $avg->execute([':cid' => $cycle_id, ':rat' => $recorded_at]);
        $stats = $avg->fetch();

        if ($stats && (float)$stats['avg_dead'] > 0) {
            $ratio = $quantity / (float)$stats['avg_dead'];
            if ($ratio >= 3) {
                $warnings[] = "Số con chết ({$quantity}) cao gấp " . round($ratio, 1) . "x so với trung bình 7 ngày (" . round($stats['avg_dead'], 1) . " con/ngày)";
            }
        }

        // Kiểm tra tuyệt đối: >20 con một lúc
        if ($quantity > 20) {
            $warnings[] = "Số con chết ({$quantity}) khá cao. Hãy kiểm tra lại?";
        }

        return $warnings;
    }

    /**
     * Kiểm tra sale: duplicate cùng ngày
     */
    public function check_sale(int $cycle_id, string $recorded_at): array
    {
        $warnings = [];
        $date = substr($recorded_at, 0, 10);
        $dup = $this->pdo->prepare("
            SELECT COUNT(*) FROM care_sales
            WHERE cycle_id = :cid AND DATE(recorded_at) = :date
        ");
        $dup->execute([':cid' => $cycle_id, ':date' => $date]);
        if ((int)$dup->fetchColumn() > 0) {
            $warnings[] = "Đã có lần bán gà khác trong ngày này. Có thể bị trùng?";
        }

        return $warnings;
    }
}
