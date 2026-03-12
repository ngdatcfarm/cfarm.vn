<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Export;
use PDO;

class ExportController
{
    public function __construct(private PDO $pdo) {}

    // GET /export — trang chọn export
    public function index(array $vars): void
    {
        // Thống kê data có sẵn
        $stats = [];
        $stats['cycles']           = (int)$this->pdo->query("SELECT COUNT(*) FROM cycles")->fetchColumn();
        $stats['snapshots']        = (int)$this->pdo->query("SELECT COUNT(*) FROM cycle_daily_snapshots")->fetchColumn();
        $stats['care_feeds']       = (int)$this->pdo->query("SELECT COUNT(*) FROM care_feeds")->fetchColumn();
        $stats['care_deaths']      = (int)$this->pdo->query("SELECT COUNT(*) FROM care_deaths")->fetchColumn();
        $stats['care_sales']       = (int)$this->pdo->query("SELECT COUNT(*) FROM care_sales")->fetchColumn();
        $stats['sensor_readings']  = (int)$this->pdo->query("SELECT COUNT(*) FROM sensor_readings")->fetchColumn();
        $stats['weight_sessions']  = (int)$this->pdo->query("SELECT COUNT(*) FROM weight_sessions")->fetchColumn();
        $stats['date_range_start'] = $this->pdo->query("SELECT MIN(start_date) FROM cycles")->fetchColumn();
        $stats['date_range_end']   = $this->pdo->query("SELECT MAX(snapshot_date) FROM cycle_daily_snapshots")->fetchColumn();

        require view_path('export/export_index.php');
    }

    // GET /export/download?type=xxx&format=yyy
    public function download(array $vars): void
    {
        $type   = $_GET['type']   ?? 'snapshots';
        $format = $_GET['format'] ?? 'csv';

        $data     = [];
        $filename = '';

        switch ($type) {
            case 'snapshots':
                $data     = $this->export_snapshots();
                $filename = 'cfarm_daily_snapshots_' . date('Ymd');
                break;
            case 'care':
                $data     = $this->export_care_events();
                $filename = 'cfarm_care_events_' . date('Ymd');
                break;
            case 'sensor':
                $data     = $this->export_sensor_readings();
                $filename = 'cfarm_sensor_readings_' . date('Ymd');
                break;
            case 'qa':
                $format   = 'jsonl';
                $data     = $this->export_qa_jsonl();
                $filename = 'cfarm_training_qa_' . date('Ymd');
                break;
            case 'full':
                $format   = 'json';
                $data     = $this->export_full_json();
                $filename = 'cfarm_full_export_' . date('Ymd');
                break;
        }

        if ($format === 'csv') {
            $this->send_csv($data, $filename . '.csv');
        } elseif ($format === 'jsonl') {
            $this->send_jsonl($data, $filename . '.jsonl');
        } else {
            $this->send_json($data, $filename . '.json');
        }
    }

    // ================================================================
    // EXPORT FUNCTIONS
    // ================================================================

    private function export_snapshots(): array
    {
        return $this->pdo->query("
            SELECT
                s.snapshot_date,
                s.day_age,
                c.code as cycle_code,
                c.breed,
                c.season,
                c.flock_source,
                b.name as barn_name,
                b.length_m, b.width_m, b.height_m,
                ROUND(b.length_m * b.width_m, 1) as area_m2,
                c.initial_quantity,
                s.alive_total,
                s.dead_today,
                s.sold_today,
                ROUND(s.dead_today / NULLIF(s.alive_total,0) * 100, 4) as daily_mortality_pct,
                s.feed_poured_kg,
                s.feed_consumed_kg,
                s.feed_cumulative_kg,
                ROUND(s.feed_consumed_kg / NULLIF(s.alive_total,0) * 1000, 2) as feed_per_bird_g,
                s.avg_weight_g,
                s.avg_weight_male_g,
                s.avg_weight_female_g,
                s.biomass_kg,
                s.fcr_cumulative,
                s.bird_days_cumulative
            FROM cycle_daily_snapshots s
            JOIN cycles c ON c.id = s.cycle_id
            JOIN barns b ON b.id = c.barn_id
            ORDER BY c.id, s.day_age
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function export_care_events(): array
    {
        // Merge feeds + deaths + sales thành 1 timeline
        $feeds = $this->pdo->query("
            SELECT
                'feed' as event_type,
                cf.recorded_at,
                c.code as cycle_code,
                c.breed,
                b.name as barn_name,
                DATEDIFF(cf.recorded_at, c.start_date)+1 as day_age,
                cf.bags,
                cf.kg_actual as feed_kg,
                cf.remaining_pct,
                cf.session,
                NULL as death_count,
                NULL as death_category,
                NULL as sale_qty,
                NULL as sale_weight_kg,
                NULL as sale_price_per_kg,
                cf.note
            FROM care_feeds cf
            JOIN cycles c ON c.id = cf.cycle_id
            JOIN barns b ON b.id = c.barn_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $deaths = $this->pdo->query("
            SELECT
                'death' as event_type,
                cd.recorded_at,
                c.code as cycle_code,
                c.breed,
                b.name as barn_name,
                DATEDIFF(cd.recorded_at, c.start_date)+1 as day_age,
                NULL as bags,
                NULL as feed_kg,
                NULL as remaining_pct,
                NULL as session,
                cd.quantity as death_count,
                cd.death_category,
                NULL as sale_qty,
                NULL as sale_weight_kg,
                NULL as sale_price_per_kg,
                cd.note
            FROM care_deaths cd
            JOIN cycles c ON c.id = cd.cycle_id
            JOIN barns b ON b.id = c.barn_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $sales = $this->pdo->query("
            SELECT
                'sale' as event_type,
                cs.recorded_at,
                c.code as cycle_code,
                c.breed,
                b.name as barn_name,
                DATEDIFF(cs.recorded_at, c.start_date)+1 as day_age,
                NULL as bags,
                NULL as feed_kg,
                NULL as remaining_pct,
                NULL as session,
                NULL as death_count,
                NULL as death_category,
                cs.quantity as sale_qty,
                cs.weight_kg as sale_weight_kg,
                cs.price_per_kg as sale_price_per_kg,
                cs.note
            FROM care_sales cs
            JOIN cycles c ON c.id = cs.cycle_id
            JOIN barns b ON b.id = c.barn_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $all = array_merge($feeds, $deaths, $sales);
        usort($all, fn($a, $b) => strcmp($a['recorded_at'], $b['recorded_at']));
        return $all;
    }

    private function export_sensor_readings(): array
    {
        return $this->pdo->query("
            SELECT
                sr.recorded_at,
                d.device_code,
                d.mqtt_topic,
                b.name as barn_name,
                sr.temperature,
                sr.humidity,
                sr.heat_index,
                d.wifi_rssi,
                d.firmware_version
            FROM sensor_readings sr
            JOIN devices d ON d.id = sr.device_id
            LEFT JOIN barns b ON b.id = d.barn_id
            ORDER BY sr.recorded_at
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function export_qa_jsonl(): array
    {
        // Tạo Q&A pairs từ dữ liệu thực tế cho fine-tune LLM
        $pairs = [];
        $snapshots = $this->pdo->query("
            SELECT s.*, c.code, c.breed, b.name as barn_name
            FROM cycle_daily_snapshots s
            JOIN cycles c ON c.id = s.cycle_id
            JOIN barns b ON b.id = c.barn_id
            WHERE s.fcr_cumulative IS NOT NULL
              AND s.avg_weight_g IS NOT NULL
            ORDER BY RAND() LIMIT 500
        ")->fetchAll(PDO::FETCH_OBJ);

        foreach ($snapshots as $s) {
            // Q&A 1: FCR
            $pairs[] = [
                'messages' => [
                    ['role' => 'system', 'content' => 'Bạn là chuyên gia nuôi gà với dữ liệu thực tế từ trang trại CFarm.'],
                    ['role' => 'user',   'content' => "Chu kỳ {$s->code}, giống {$s->breed}, ngày tuổi {$s->day_age}, đã ăn {$s->feed_cumulative_kg}kg, còn {$s->alive_total} con. FCR tích lũy là bao nhiêu?"],
                    ['role' => 'assistant', 'content' => "FCR tích lũy ngày {$s->day_age} là {$s->fcr_cumulative}. Tức là mỗi kg tăng trọng cần {$s->fcr_cumulative}kg thức ăn."],
                ]
            ];

            // Q&A 2: Tăng trưởng
            if ($s->avg_weight_g) {
                $pairs[] = [
                    'messages' => [
                        ['role' => 'system', 'content' => 'Bạn là chuyên gia nuôi gà với dữ liệu thực tế từ trang trại CFarm.'],
                        ['role' => 'user',   'content' => "Gà {$s->breed} ở chuồng {$s->barn_name}, ngày tuổi {$s->day_age}, cân nặng trung bình là bao nhiêu?"],
                        ['role' => 'assistant', 'content' => "Ngày tuổi {$s->day_age}, cân nặng trung bình {$s->avg_weight_g}g/con. Tổng đàn còn {$s->alive_total} con, biomass {$s->biomass_kg}kg."],
                    ]
                ];
            }
        }

        // Q&A từ care_deaths
        $deaths = $this->pdo->query("
            SELECT cd.*, c.code, c.breed, b.name as barn_name,
                   DATEDIFF(cd.recorded_at, c.start_date)+1 as day_age
            FROM care_deaths cd
            JOIN cycles c ON c.id = cd.cycle_id
            JOIN barns b ON b.id = c.barn_id
            LIMIT 200
        ")->fetchAll(PDO::FETCH_OBJ);

        foreach ($deaths as $d) {
            $pairs[] = [
                'messages' => [
                    ['role' => 'system', 'content' => 'Bạn là chuyên gia nuôi gà với dữ liệu thực tế từ trang trại CFarm.'],
                    ['role' => 'user',   'content' => "Ngày {$d->day_age} chu kỳ {$d->code}, gà chết {$d->quantity} con, nguyên nhân {$d->death_category}. Đây có phải dấu hiệu đáng lo không?"],
                    ['role' => 'assistant', 'content' => "Ghi nhận {$d->quantity} con chết ngày {$d->day_age}, phân loại: {$d->death_category}." . ($d->note ? " Ghi chú: {$d->note}." : "") . " Cần theo dõi tỷ lệ chết tích lũy để đánh giá mức độ nghiêm trọng."],
                ]
            ];
        }

        shuffle($pairs);
        return $pairs;
    }

    private function export_full_json(): array
    {
        $cycles = $this->pdo->query("
            SELECT c.*, b.name as barn_name, b.length_m, b.width_m, b.height_m
            FROM cycles c JOIN barns b ON b.id = c.barn_id
        ")->fetchAll(PDO::FETCH_OBJ);

        $result = [];
        foreach ($cycles as $c) {
            $snapshots = $this->pdo->prepare("SELECT * FROM cycle_daily_snapshots WHERE cycle_id=:id ORDER BY day_age");
            $snapshots->execute([':id' => $c->id]);

            $feeds = $this->pdo->prepare("SELECT cf.*, ft.code as feed_code FROM care_feeds cf LEFT JOIN feed_types ft ON ft.id=cf.feed_type_id WHERE cf.cycle_id=:id ORDER BY recorded_at");
            $feeds->execute([':id' => $c->id]);

            $deaths = $this->pdo->prepare("SELECT * FROM care_deaths WHERE cycle_id=:id ORDER BY recorded_at");
            $deaths->execute([':id' => $c->id]);

            $sales = $this->pdo->prepare("SELECT * FROM care_sales WHERE cycle_id=:id ORDER BY recorded_at");
            $sales->execute([':id' => $c->id]);

            $result[] = [
                'cycle'     => $c,
                'snapshots' => $snapshots->fetchAll(PDO::FETCH_OBJ),
                'feeds'     => $feeds->fetchAll(PDO::FETCH_OBJ),
                'deaths'    => $deaths->fetchAll(PDO::FETCH_OBJ),
                'sales'     => $sales->fetchAll(PDO::FETCH_OBJ),
            ];
        }
        return $result;
    }

    // ================================================================
    // SEND HELPERS
    // ================================================================

    private function send_csv(array $data, string $filename): void
    {
        if (empty($data)) {
            header('Content-Type: text/plain');
            echo 'Không có dữ liệu để export';
            return;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM cho Excel
        fputcsv($out, array_keys($data[0]));
        foreach ($data as $row) fputcsv($out, $row);
        fclose($out);
    }

    private function send_jsonl(array $data, string $filename): void
    {
        header('Content-Type: application/jsonl; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');
        foreach ($data as $row) echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function send_json(array $data, string $filename): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
