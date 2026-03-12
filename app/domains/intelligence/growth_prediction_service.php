<?php
declare(strict_types=1);
namespace App\Domains\Intelligence;

use PDO;

class GrowthPredictionService
{
    public function __construct(private PDO $pdo) {}

    /**
     * Dự đoán tăng trọng dựa trên linear regression
     * Trả về: predicted_weight_at_day, r_squared, data_points, trend_g_per_day
     */
    public function predict(int $cycle_id, ?int $target_day = null): ?array
    {
        // Lấy tất cả avg weight theo day_age (dùng moving avg 2 sessions nếu có)
        $stmt = $this->pdo->prepare("
            SELECT ws.day_age,
                   AVG(wsa.weight_g) AS avg_g
            FROM weight_sessions ws
            JOIN weight_samples wsa ON wsa.session_id = ws.id
            WHERE ws.cycle_id = :c
            GROUP BY ws.id, ws.day_age
            ORDER BY ws.day_age ASC
        ");
        $stmt->execute([':c' => $cycle_id]);
        $rows = $stmt->fetchAll();

        if (count($rows) < 2) return null; // Cần ít nhất 2 điểm

        // Gộp session cùng ngày (lấy avg)
        $by_day = [];
        foreach ($rows as $r) {
            $d = (int)$r['day_age'];
            $by_day[$d][] = (float)$r['avg_g'];
        }
        $points = [];
        foreach ($by_day as $day => $vals) {
            $points[] = ['x' => $day, 'y' => array_sum($vals) / count($vals)];
        }
        usort($points, fn($a, $b) => $a['x'] <=> $b['x']);

        if (count($points) < 2) return null;

        // Linear regression: y = a + b*x
        [$a, $b, $r2] = $this->linear_regression($points);

        // Target day
        $cycle = $this->pdo->prepare("SELECT * FROM cycles WHERE id=:id");
        $cycle->execute([':id' => $cycle_id]);
        $cycle = $cycle->fetch();

        $current_day = (int)((strtotime('today') - strtotime($cycle['start_date'])) / 86400) + 1;
        if (!$target_day) {
            $target_day = $cycle['expected_end_date']
                ? (int)((strtotime($cycle['expected_end_date']) - strtotime($cycle['start_date'])) / 86400) + 1
                : $current_day + 14;
        }

        $predicted_g     = max(0, $a + $b * $target_day);
        $predicted_kg    = round($predicted_g / 1000, 3);

        // Confidence interval ±1 std error
        $std_err = $this->std_error($points, $a, $b);
        $ci_kg   = round($std_err / 1000, 3);

        // Dự đoán cho từng ngày từ hôm nay đến target
        $forecast = [];
        $last_day = end($points)['x'];
        for ($d = $last_day + 1; $d <= $target_day; $d++) {
            $forecast[] = [
                'day'          => $d,
                'predicted_g'  => round(max(0, $a + $b * $d), 1),
                'lower_g'      => round(max(0, $a + $b * $d - $std_err), 1),
                'upper_g'      => round(max(0, $a + $b * $d + $std_err), 1),
            ];
        }

        return [
            'points'           => $points,
            'forecast'         => $forecast,
            'trend_g_per_day'  => round($b, 1),
            'predicted_g'      => round($predicted_g, 1),
            'predicted_kg'     => $predicted_kg,
            'ci_kg'            => $ci_kg,
            'target_day'       => $target_day,
            'r_squared'        => round($r2, 3),
            'n_points'         => count($points),
            'reliable'         => $r2 >= 0.85 && count($points) >= 3,
        ];
    }

    // ----------------------------------------------------------------
    // Linear regression — trả về [intercept, slope, r²]
    // ----------------------------------------------------------------
    private function linear_regression(array $points): array
    {
        $n  = count($points);
        $sx = array_sum(array_column($points, 'x'));
        $sy = array_sum(array_column($points, 'y'));
        $sxx = array_sum(array_map(fn($p) => $p['x'] ** 2, $points));
        $sxy = array_sum(array_map(fn($p) => $p['x'] * $p['y'], $points));
        $syy = array_sum(array_map(fn($p) => $p['y'] ** 2, $points));

        $denom = $n * $sxx - $sx ** 2;
        if ($denom == 0) return [0, 0, 0];

        $b = ($n * $sxy - $sx * $sy) / $denom;
        $a = ($sy - $b * $sx) / $n;

        // R²
        $ss_res = array_sum(array_map(fn($p) => ($p['y'] - ($a + $b * $p['x'])) ** 2, $points));
        $y_mean = $sy / $n;
        $ss_tot = array_sum(array_map(fn($p) => ($p['y'] - $y_mean) ** 2, $points));
        $r2 = $ss_tot > 0 ? 1 - $ss_res / $ss_tot : 0;

        return [$a, $b, $r2];
    }

    private function std_error(array $points, float $a, float $b): float
    {
        $n = count($points);
        if ($n < 3) return 0;
        $ss_res = array_sum(array_map(fn($p) => ($p['y'] - ($a + $b * $p['x'])) ** 2, $points));
        return sqrt($ss_res / ($n - 2));
    }
}
