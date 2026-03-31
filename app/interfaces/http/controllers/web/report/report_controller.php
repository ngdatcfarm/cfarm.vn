<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Report;

use App\Infrastructure\Persistence\Mysql\Repositories\CycleRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\WeightRepository;
use App\Domains\Snapshot\SnapshotService;
use App\Domains\Intelligence\AlertService;
use App\Domains\Intelligence\GrowthPredictionService;
use PDO;

class ReportController
{
    private CycleRepository    $cycle_repo;
    private WeightRepository   $weight_repo;
    private SnapshotService    $snapshot_svc;
    private AlertService       $alert_svc;
    private GrowthPredictionService $growth_svc;

    public function __construct(private PDO $pdo)
    {
        $this->cycle_repo    = new CycleRepository($pdo);
        $this->weight_repo   = new WeightRepository($pdo);
        $this->snapshot_svc  = new SnapshotService($pdo);
        $this->alert_svc     = new AlertService($pdo);
        $this->growth_svc    = new GrowthPredictionService($pdo);
    }

    // GET /reports
    public function index(array $vars): void
    {
        $stmt   = $this->pdo->query("
            SELECT c.*, b.name AS barn_name, b.number AS barn_number
            FROM cycles c
            JOIN barns b ON c.barn_id = b.id
            ORDER BY c.status ASC, c.start_date DESC
        ");
        $cycles = $stmt->fetchAll();
        require view_path('report/report_index.php');
    }

    // GET /reports/{id}
    public function show(array $vars): void
    {
        $cycle_id = (int)$vars['id'];
        $cycle    = $this->cycle_repo->find_by_id($cycle_id);
        if (!$cycle) { http_response_code(404); echo '404'; return; }

        // Feed
        $stmt = $this->pdo->prepare("
            SELECT cf.*, ft.code AS feed_code, fb.name AS brand_name,
                   fb.kg_per_bag, ft.price_per_bag,
                   (SELECT ftc.remaining_pct FROM feed_trough_checks ftc
                    WHERE ftc.ref_feed_id = cf.id
                    ORDER BY ftc.checked_at DESC LIMIT 1) AS latest_remaining_pct
            FROM care_feeds cf
            JOIN feed_types ft  ON cf.feed_type_id  = ft.id
            JOIN feed_brands fb ON ft.feed_brand_id = fb.id
            WHERE cf.cycle_id = :id ORDER BY cf.recorded_at ASC
        ");
        $stmt->execute([':id' => $cycle_id]);
        $care_feeds = $stmt->fetchAll();

        // Medications
        $stmt = $this->pdo->prepare("
            SELECT cm.*, m.price_per_unit
            FROM care_medications cm
            LEFT JOIN medications m ON cm.medication_name = m.name
            WHERE cm.cycle_id = :id ORDER BY cm.recorded_at ASC
        ");
        $stmt->execute([':id' => $cycle_id]);
        $care_medications = $stmt->fetchAll();

        // Deaths
        $stmt = $this->pdo->prepare("SELECT * FROM care_deaths WHERE cycle_id=:id ORDER BY recorded_at ASC");
        $stmt->execute([':id' => $cycle_id]);
        $care_deaths = $stmt->fetchAll();

        // Sales
        $stmt = $this->pdo->prepare("SELECT * FROM care_sales WHERE cycle_id=:id ORDER BY recorded_at ASC");
        $stmt->execute([':id' => $cycle_id]);
        $care_sales = $stmt->fetchAll();

        // Weight
        $weight_sessions = $this->weight_repo->find_sessions_with_gender_avg($cycle_id);

        // ---- Incremental snapshot: chỉ tính từ snapshot cuối cùng đã có ----
        $last_snap = $this->pdo->prepare("
            SELECT MAX(day_age) FROM cycle_daily_snapshots WHERE cycle_id = :id
        ");
        $last_snap->execute([':id' => $cycle_id]);
        $last_day = (int)$last_snap->fetchColumn();
        $this->snapshot_svc->recalculate_from_day($cycle_id, max(1, $last_day));

        // ---- Load snapshots ----
        $snap_stmt = $this->pdo->prepare("
            SELECT * FROM cycle_daily_snapshots
            WHERE cycle_id = :id ORDER BY day_age ASC
        ");
        $snap_stmt->execute([':id' => $cycle_id]);
        $daily_snapshots = $snap_stmt->fetchAll();
        $latest_snap = !empty($daily_snapshots) ? end($daily_snapshots) : null;

        // ---- FCR tổng — dùng lần cân đầu và cuối ----
        $total_feed_kg_consumed = $latest_snap ? (float)$latest_snap['feed_cumulative_kg'] : 0;
        $fcr         = null;
        $weight_gain_kg = null;
        if (count($weight_sessions) >= 2) {
            $ws_first = $weight_sessions[0];
            $ws_last  = end($weight_sessions);
            $snap_first = null;
            foreach ($daily_snapshots as $s) {
                if ((int)$s['day_age'] === (int)$ws_first['day_age']) { $snap_first = $s; break; }
            }
            $actual_count = $snap_first ? (int)$snap_first['alive_total'] : (int)$cycle->current_quantity;
            $gain_g = (float)$ws_last['avg_weight_g'] - (float)$ws_first['avg_weight_g'];
            if ($gain_g > 0 && $actual_count > 0) {
                $weight_gain_kg = $gain_g / 1000 * $actual_count;
                $fcr = $total_feed_kg_consumed > 0
                    ? round($total_feed_kg_consumed / $weight_gain_kg, 2) : null;
            }
        }

        // ---- Feed kg đổ vào ----
        $total_feed_kg_poured = array_sum(array_column($care_feeds, 'kg_actual'));

        // ---- Chi phí cám ----
        $total_feed_cost  = 0;
        $feed_cost_by_day = [];
        foreach ($care_feeds as $cf) {
            $cost = !empty($cf['price_per_bag']) ? (float)$cf['bags'] * (float)$cf['price_per_bag'] : 0;
            $total_feed_cost += $cost;
            $day = (int)((strtotime($cf['recorded_at']) - strtotime($cycle->start_date)) / 86400) + 1;
            $feed_cost_by_day[$day] = ($feed_cost_by_day[$day] ?? 0) + $cost;
        }
        ksort($feed_cost_by_day);

        // ---- Chi phí thuốc ----
        $total_med_cost = 0;
        foreach ($care_medications as $m) {
            if (!empty($m['price_per_unit'])) {
                $total_med_cost += (float)$m['dosage'] * (float)$m['price_per_unit'];
            }
        }

        // ---- Chi phí & P&L ----
        $chick_cost    = (float)$cycle->initial_quantity * (float)$cycle->purchase_price;
        $total_cost    = $chick_cost + $total_feed_cost + $total_med_cost;
        $total_revenue = array_sum(array_column($care_sales, 'total_amount'));
        $profit        = $total_revenue - $total_cost;

        // ---- FCR theo giai đoạn ----
        $fcr_stages = [];
        if (count($weight_sessions) >= 2) {
            for ($i = 0; $i < count($weight_sessions) - 1; $i++) {
                $ws_from  = $weight_sessions[$i];
                $ws_to    = $weight_sessions[$i + 1];
                $day_from = (int)$ws_from['day_age'];
                $day_to   = (int)$ws_to['day_age'];

                $snap_from = null; $snap_to = null;
                foreach ($daily_snapshots as $s) {
                    if ((int)$s['day_age'] === $day_from) $snap_from = $s;
                    if ((int)$s['day_age'] === $day_to)   $snap_to   = $s;
                }

                $feed_from     = $snap_from ? (float)$snap_from['feed_cumulative_kg'] : 0;
                $feed_to       = $snap_to   ? (float)$snap_to['feed_cumulative_kg']   : 0;
                $stage_feed_kg = max(0, $feed_to - $feed_from);
                $actual_count  = $snap_from ? (int)$snap_from['alive_total'] : (int)$cycle->current_quantity;

                $avg_from = (float)$ws_from['avg_weight_g'];
                $avg_to   = (float)$ws_to['avg_weight_g'];
                $gain_g   = $avg_to - $avg_from;
                $gain_kg  = ($gain_g > 0 && $actual_count > 0) ? $gain_g / 1000 * $actual_count : null;
                $stage_fcr = ($gain_kg && $stage_feed_kg > 0) ? round($stage_feed_kg / $gain_kg, 2) : null;

                $gender_fcr = [];
                foreach (['male', 'female'] as $g) {
                    $avg_from_g = (float)($ws_from['by_gender'][$g]['avg_g'] ?? 0);
                    $avg_to_g   = (float)($ws_to['by_gender'][$g]['avg_g']   ?? 0);
                    if ($avg_from_g > 0 && $avg_to_g > 0) {
                        $gain_g2      = $avg_to_g - $avg_from_g;
                        $gender_count = $g === 'male'
                            ? (int)($snap_from['alive_male']   ?? $actual_count / 2)
                            : (int)($snap_from['alive_female'] ?? $actual_count / 2);
                        $gain_kg2 = $gain_g2 > 0 ? $gain_g2 / 1000 * $gender_count : null;
                        $gender_fcr[$g] = [
                            'avg_from' => $avg_from_g,
                            'avg_to'   => $avg_to_g,
                            'gain_g'   => $gain_g2,
                            'fcr'      => ($gain_kg2 && $stage_feed_kg > 0)
                                ? round($stage_feed_kg / 2 / $gain_kg2, 2) : null,
                        ];
                    }
                }

                $fcr_stages[] = [
                    'from_day'     => $day_from,
                    'to_day'       => $day_to,
                    'actual_count' => $actual_count,
                    'avg_from'     => $avg_from,
                    'avg_to'       => $avg_to,
                    'gain_g'       => $gain_g,
                    'feed_kg'      => round($stage_feed_kg, 1),
                    'fcr'          => $stage_fcr,
                    'gender_fcr'   => $gender_fcr,
                ];
            }
        }

        // Intelligence
        $cycle_alerts = $this->alert_svc->get_alerts($cycle_id);
        $growth_pred  = $this->growth_svc->predict($cycle_id);

        require view_path('report/report_show.php');
    }
}
