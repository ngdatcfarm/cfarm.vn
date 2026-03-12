<?php
declare(strict_types=1);
namespace App\Domains\Snapshot;

use PDO;

/**
 * SnapshotService
 * Tính toán daily snapshot cho một cycle.
 * Recalculate từ ngày có event trở đi.
 */
class SnapshotService
{
    public function __construct(private PDO $pdo) {}

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    /** Recalculate toàn bộ cycle từ ngày 1 */
    public function recalculate_cycle(int $cycle_id): void
    {
        $this->recalculate_from_day($cycle_id, 1);
    }

    /** Recalculate từ ngày có event trở đi */
    public function recalculate_from_day(int $cycle_id, int $from_day): void
    {
        $cycle = $this->load_cycle($cycle_id);
        if (!$cycle) return;

        $events   = $this->load_all_events($cycle_id, $cycle);
        $today_age = (int)((strtotime('today') - strtotime($cycle['start_date'])) / 86400) + 1;
        $max_day  = max($today_age, $this->max_event_day($events));

        // Xóa snapshots từ from_day trở đi
        $this->pdo->prepare("DELETE FROM cycle_daily_snapshots WHERE cycle_id=:c AND day_age >= :d")
            ->execute([':c' => $cycle_id, ':d' => $from_day]);

        // Load snapshot ngày trước from_day để có state ban đầu
        $prev = $this->load_prev_snapshot($cycle_id, $from_day);

        // State tích lũy
        $state = $this->init_state($cycle, $prev, $from_day);

        for ($day = $from_day; $day <= $max_day; $day++) {
            $snapshot_date = date('Y-m-d', strtotime($cycle['start_date']) + ($day - 1) * 86400);
            $day_events    = $this->events_on_day($events, $day);

            $state = $this->compute_day($cycle, $state, $day, $snapshot_date, $day_events);
            $this->upsert_snapshot($cycle_id, $day, $snapshot_date, $state);
        }
    }

    // ----------------------------------------------------------------
    // Core computation
    // ----------------------------------------------------------------

    private function compute_day(array $cycle, array $state, int $day, string $date, array $ev): array
    {
        $waste_pct = (float)($cycle['feed_waste_pct'] ?? 3.0) / 100;

        // 1. Số con chết hôm nay
        $dead_today = array_sum(array_column($ev['deaths'], 'quantity'));

        // 2. Số con bán hôm nay (theo gender)
        $sold_male = $sold_female = $sold_mixed = 0;
        foreach ($ev['sales'] as $s) {
            $qty = (int)$s['quantity'];
            match ($s['gender'] ?? 'mixed') {
                'male'   => $sold_male   += $qty,
                'female' => $sold_female += $qty,
                default  => $sold_mixed  += $qty,
            };
        }
        // mixed chia đôi theo tỷ lệ hiện tại
        $total_prev = max($state['alive_male'] + $state['alive_female'], 1);
        $sold_male   += (int)round($sold_mixed * $state['alive_male']   / $total_prev);
        $sold_female += (int)round($sold_mixed * $state['alive_female'] / $total_prev);
        $sold_today   = $sold_male + $sold_female;

        // 3. Số con sống cuối ngày
        // Gà chết chia đều trống/mái theo tỷ lệ
        $dead_male   = $state['alive_male']   > 0 ? (int)round($dead_today * $state['alive_male']   / $total_prev) : 0;
        $dead_female = $dead_today - $dead_male;

        $alive_male   = max(0, $state['alive_male']   - $dead_male   - $sold_male);
        $alive_female = max(0, $state['alive_female'] - $dead_female - $sold_female);
        $alive_total  = $alive_male + $alive_female;

        // 4. Bird-days (tính cuối ngày sau khi trừ chết/bán)
        $bird_days = $state['bird_days_cumulative'] + $alive_total;

        // 5. Feed hôm nay
        $feed_poured    = array_sum(array_column($ev['feeds'], 'kg_actual'));
        $feed_remaining = array_sum(array_column($ev['trough_checks'], 'remaining_kg'));
        $feed_net       = max(0, $feed_poured - $feed_remaining);

        // Gà chết ăn 50% khẩu phần ngày đó
        $feed_per_bird  = $alive_total > 0 ? $feed_net / max($alive_total, 1) : 0;
        $feed_dead_adj  = $dead_today * $feed_per_bird * 0.5;
        $feed_consumed  = max(0, ($feed_net + $feed_dead_adj) * (1 - $waste_pct));
        $feed_cumulative = $state['feed_cumulative_kg'] + $feed_consumed;

        // 6. Avg weight từ weight_samples (moving avg 2 sessions gần nhất)
        [$avg_all, $avg_male, $avg_female] = $this->get_weight_avg($cycle['id'], $day);

        // 7. Biomass
        $biomass_kg      = null;
        $biomass_dead_kg = null;
        $biomass_sold_kg = null;
        if ($avg_all !== null) {
            $m = $avg_male   ?? $avg_all;
            $f = $avg_female ?? $avg_all;
            $biomass_kg      = ($alive_male * $m + $alive_female * $f) / 1000;
            $biomass_dead_kg = ($dead_male  * $m + $dead_female  * $f) / 1000;
            $biomass_sold_kg = array_sum(array_column($ev['sales'], 'weight_kg'));
        }

        // 8. Weight produced (tích lũy)
        // Set biomass_day0 lần đầu tiên có weight data
        $biomass_day0 = $state['biomass_day0'];
        if ($biomass_day0 == 0 && $biomass_kg !== null) {
            $biomass_day0 = $biomass_kg;
        }

        $wp_cumulative = null;
        if ($biomass_kg !== null && $biomass_day0 > 0) {
            $sold_cum = $state['biomass_sold_cumulative'] + ($biomass_sold_kg ?? 0);
            $dead_cum = $state['biomass_dead_cumulative'] + ($biomass_dead_kg ?? 0);
            $wp_cumulative = $biomass_kg - $biomass_day0 + $sold_cum + $dead_cum;
        }

        // 9. FCR
        $fcr = ($wp_cumulative > 0 && $feed_cumulative > 0)
            ? round($feed_cumulative / $wp_cumulative, 3)
            : null;

        return [
            'alive_total'              => $alive_total,
            'alive_male'               => $alive_male,
            'alive_female'             => $alive_female,
            'dead_today'               => $dead_today,
            'sold_today'               => $sold_today,
            'sold_male_today'          => $sold_male,
            'sold_female_today'        => $sold_female,
            'bird_days_cumulative'     => $bird_days,
            'feed_poured_kg'           => round($feed_poured, 3),
            'feed_remaining_kg'        => round($feed_remaining, 3),
            'feed_consumed_kg'         => round($feed_consumed, 3),
            'feed_cumulative_kg'       => round($feed_cumulative, 3),
            'avg_weight_g'             => $avg_all,
            'avg_weight_male_g'        => $avg_male,
            'avg_weight_female_g'      => $avg_female,
            'biomass_kg'               => $biomass_kg   !== null ? round($biomass_kg, 2)   : null,
            'biomass_dead_kg'          => $biomass_dead_kg !== null ? round($biomass_dead_kg + ($state['biomass_dead_cumulative'] ?? 0), 2) : null,
            'biomass_sold_kg'          => $biomass_sold_kg !== null ? round($biomass_sold_kg + ($state['biomass_sold_cumulative'] ?? 0), 2) : null,
            'weight_produced_kg'       => $wp_cumulative !== null ? round($wp_cumulative, 2) : null,
            'fcr_cumulative'           => $fcr,
            // Internal state (không lưu DB)
            'biomass_day0'             => $state['biomass_day0'],
            'biomass_sold_cumulative'  => ($state['biomass_sold_cumulative'] ?? 0) + ($biomass_sold_kg ?? 0),
            'biomass_dead_cumulative'  => ($state['biomass_dead_cumulative'] ?? 0) + ($biomass_dead_kg ?? 0),
        ];
    }

    // ----------------------------------------------------------------
    // Weight moving average (2 sessions gần nhất <= day)
    // ----------------------------------------------------------------

    private function get_weight_avg(int $cycle_id, int $day): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ws.day_age,
                   AVG(CASE WHEN wsa.gender='male'   THEN wsa.weight_g END) AS avg_male,
                   AVG(CASE WHEN wsa.gender='female' THEN wsa.weight_g END) AS avg_female,
                   AVG(wsa.weight_g) AS avg_all
            FROM weight_sessions ws
            JOIN weight_samples wsa ON wsa.session_id = ws.id
            WHERE ws.cycle_id = :c AND ws.day_age <= :d
            GROUP BY ws.id
            ORDER BY ws.day_age DESC
            LIMIT 2
        ");
        $stmt->execute([':c' => $cycle_id, ':d' => $day]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) return [null, null, null];

        // Moving average của tối đa 2 sessions
        $avg_all    = array_sum(array_column($rows, 'avg_all'))    / count($rows);
        $male_vals  = array_filter(array_column($rows, 'avg_male'));
        $female_vals= array_filter(array_column($rows, 'avg_female'));
        $avg_male   = count($male_vals)   > 0 ? array_sum($male_vals)   / count($male_vals)   : null;
        $avg_female = count($female_vals) > 0 ? array_sum($female_vals) / count($female_vals) : null;

        return [round($avg_all, 1), $avg_male ? round($avg_male, 1) : null, $avg_female ? round($avg_female, 1) : null];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function load_cycle(int $cycle_id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cycles WHERE id=:id");
        $stmt->execute([':id' => $cycle_id]);
        return $stmt->fetch() ?: null;
    }

    private function load_all_events(int $cycle_id, array $cycle): array
    {
        $start = strtotime($cycle['start_date']);

        // Feeds + trough checks
        $stmt = $this->pdo->prepare("
            SELECT cf.*,
                   DATEDIFF(DATE(cf.recorded_at), :start) + 1 AS day_age
            FROM care_feeds cf WHERE cf.cycle_id=:c
        ");
        $stmt->execute([':c' => $cycle_id, ':start' => $cycle['start_date']]);
        $feeds = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT ftc.ref_feed_id,
                   ftc.remaining_pct,
                   cf.kg_actual,
                   cf.kg_actual * ftc.remaining_pct / 100 AS remaining_kg,
                   DATEDIFF(DATE(ftc.checked_at), :start) + 1 AS day_age
            FROM feed_trough_checks ftc
            JOIN care_feeds cf ON ftc.ref_feed_id = cf.id
            WHERE cf.cycle_id=:c
        ");
        $stmt->execute([':c' => $cycle_id, ':start' => $cycle['start_date']]);
        $trough = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT *, DATEDIFF(DATE(recorded_at), :start) + 1 AS day_age
            FROM care_deaths WHERE cycle_id=:c
        ");
        $stmt->execute([':c' => $cycle_id, ':start' => $cycle['start_date']]);
        $deaths = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT *, DATEDIFF(DATE(recorded_at), :start) + 1 AS day_age
            FROM care_sales WHERE cycle_id=:c
        ");
        $stmt->execute([':c' => $cycle_id, ':start' => $cycle['start_date']]);
        $sales = $stmt->fetchAll();

        return compact('feeds', 'trough', 'deaths', 'sales');
    }

    private function events_on_day(array $events, int $day): array
    {
        $filter = fn($arr) => array_values(array_filter($arr, fn($r) => (int)$r['day_age'] === $day));
        return [
            'feeds'         => $filter($events['feeds']),
            'trough_checks' => $filter($events['trough']),
            'deaths'        => $filter($events['deaths']),
            'sales'         => $filter($events['sales']),
        ];
    }

    private function max_event_day(array $events): int
    {
        $days = [];
        foreach ($events as $group) {
            foreach ($group as $r) $days[] = (int)($r['day_age'] ?? 0);
        }
        return $days ? max($days) : 1;
    }

    private function load_prev_snapshot(int $cycle_id, int $from_day): ?array
    {
        if ($from_day <= 1) return null;
        $stmt = $this->pdo->prepare("
            SELECT * FROM cycle_daily_snapshots
            WHERE cycle_id=:c AND day_age < :d
            ORDER BY day_age DESC LIMIT 1
        ");
        $stmt->execute([':c' => $cycle_id, ':d' => $from_day]);
        return $stmt->fetch() ?: null;
    }

    private function init_state(array $cycle, ?array $prev, int $from_day): array
    {
        if ($prev) {
            return [
                'alive_total'             => (int)$prev['alive_total'],
                'alive_male'              => (int)$prev['alive_male'],
                'alive_female'            => (int)$prev['alive_female'],
                'bird_days_cumulative'    => (int)$prev['bird_days_cumulative'],
                'feed_cumulative_kg'      => (float)$prev['feed_cumulative_kg'],
                'biomass_day0'            => (float)($prev['biomass_day0'] ?? 0),
                'biomass_sold_cumulative' => (float)$prev['biomass_sold_kg'],
                'biomass_dead_cumulative' => (float)$prev['biomass_dead_kg'],
            ];
        }

        // Day 0 — biomass_day0 sẽ được set khi có lần cân đầu tiên
        $male   = (int)$cycle['male_quantity'];
        $female = (int)$cycle['female_quantity'];
        $biomass_day0 = 0; // sẽ update khi có weight data

        return [
            'alive_total'             => (int)$cycle['initial_quantity'],
            'alive_male'              => $male,
            'alive_female'            => $female,
            'bird_days_cumulative'    => 0,
            'feed_cumulative_kg'      => 0.0,
            'biomass_day0'            => $biomass_day0,
            'biomass_sold_cumulative' => 0.0,
            'biomass_dead_cumulative' => 0.0,
        ];
    }

    private function get_initial_weight_g(array $cycle): ?float
    {
        // Lấy avg weight của session đầu tiên
        $stmt = $this->pdo->prepare("
            SELECT AVG(wsa.weight_g) AS avg_g
            FROM weight_sessions ws
            JOIN weight_samples wsa ON wsa.session_id = ws.id
            WHERE ws.cycle_id = :c
            GROUP BY ws.id
            ORDER BY ws.day_age ASC, ws.id ASC
            LIMIT 1
        ");
        $stmt->execute([':c' => $cycle['id']]);
        $row = $stmt->fetch();
        return $row && $row['avg_g'] ? round((float)$row['avg_g'], 1) : null;
    }

    private function upsert_snapshot(int $cycle_id, int $day, string $date, array $s): void
    {
        $this->pdo->prepare("
            INSERT INTO cycle_daily_snapshots
                (cycle_id, day_age, snapshot_date,
                 alive_total, alive_male, alive_female,
                 dead_today, sold_today, sold_male_today, sold_female_today,
                 bird_days_cumulative,
                 feed_poured_kg, feed_remaining_kg, feed_consumed_kg, feed_cumulative_kg,
                 avg_weight_g, avg_weight_male_g, avg_weight_female_g,
                 biomass_kg, biomass_dead_kg, biomass_sold_kg,
                 weight_produced_kg, fcr_cumulative)
            VALUES
                (:cycle_id, :day, :date,
                 :alive_total, :alive_male, :alive_female,
                 :dead_today, :sold_today, :sold_male, :sold_female,
                 :bird_days,
                 :feed_poured, :feed_remaining, :feed_consumed, :feed_cumulative,
                 :avg_all, :avg_male, :avg_female,
                 :biomass, :biomass_dead, :biomass_sold,
                 :wp, :fcr)
            ON DUPLICATE KEY UPDATE
                alive_total=VALUES(alive_total), alive_male=VALUES(alive_male),
                alive_female=VALUES(alive_female), dead_today=VALUES(dead_today),
                sold_today=VALUES(sold_today), sold_male_today=VALUES(sold_male_today),
                sold_female_today=VALUES(sold_female_today),
                bird_days_cumulative=VALUES(bird_days_cumulative),
                feed_poured_kg=VALUES(feed_poured_kg), feed_remaining_kg=VALUES(feed_remaining_kg),
                feed_consumed_kg=VALUES(feed_consumed_kg), feed_cumulative_kg=VALUES(feed_cumulative_kg),
                avg_weight_g=VALUES(avg_weight_g), avg_weight_male_g=VALUES(avg_weight_male_g),
                avg_weight_female_g=VALUES(avg_weight_female_g),
                biomass_kg=VALUES(biomass_kg), biomass_dead_kg=VALUES(biomass_dead_kg),
                biomass_sold_kg=VALUES(biomass_sold_kg),
                weight_produced_kg=VALUES(weight_produced_kg), fcr_cumulative=VALUES(fcr_cumulative),
                computed_at=NOW()
        ")->execute([
            ':cycle_id'       => $cycle_id,
            ':day'            => $day,
            ':date'           => $date,
            ':alive_total'    => $s['alive_total'],
            ':alive_male'     => $s['alive_male'],
            ':alive_female'   => $s['alive_female'],
            ':dead_today'     => $s['dead_today'],
            ':sold_today'     => $s['sold_today'],
            ':sold_male'      => $s['sold_male_today'],
            ':sold_female'    => $s['sold_female_today'],
            ':bird_days'      => $s['bird_days_cumulative'],
            ':feed_poured'    => $s['feed_poured_kg'],
            ':feed_remaining' => $s['feed_remaining_kg'],
            ':feed_consumed'  => $s['feed_consumed_kg'],
            ':feed_cumulative'=> $s['feed_cumulative_kg'],
            ':avg_all'        => $s['avg_weight_g'],
            ':avg_male'       => $s['avg_weight_male_g'],
            ':avg_female'     => $s['avg_weight_female_g'],
            ':biomass'        => $s['biomass_kg'],
            ':biomass_dead'   => $s['biomass_dead_kg'],
            ':biomass_sold'   => $s['biomass_sold_kg'],
            ':wp'             => $s['weight_produced_kg'],
            ':fcr'            => $s['fcr_cumulative'],
        ]);
    }
}
