<?php
/**
 * app/interfaces/http/controllers/web/event/event_controller.php
 */
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Event;

use App\Infrastructure\Persistence\Mysql\Repositories\CycleRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\BarnRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\CareRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\WeightRepository;
use App\Domains\Snapshot\SnapshotService;
use PDO;

class EventController
{
    private CycleRepository $cycle_repository;
    private BarnRepository  $barn_repository;
    private CareRepository  $care_repository;

    public function __construct(private PDO $pdo)
    {
        $this->cycle_repository = new CycleRepository($pdo);
        $this->barn_repository  = new BarnRepository($pdo);
        $this->care_repository  = new CareRepository($pdo);
        $this->weight_repository = new WeightRepository($pdo);
        $this->snapshot_service  = new SnapshotService($pdo);
    }

    public function create(array $vars): void
    {
        $cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : null;
        $date     = $_GET['date'] ?? date('Y-m-d');

        $cycle = $cycle_id ? $this->cycle_repository->find_by_id($cycle_id) : null;
        $barn  = $cycle    ? $this->barn_repository->find_by_id($cycle->barn_id) : null;

        // data ngày đang xem — phân sáng/chiều theo giờ
        $all_feeds       = $cycle ? $this->care_repository->find_feeds_by_cycle_and_date($cycle_id, $date) : [];
        $all_deaths      = $cycle ? $this->care_repository->find_deaths_by_cycle_and_date($cycle_id, $date) : [];
        $all_medications = $cycle ? $this->care_repository->find_medications_by_cycle_and_date($cycle_id, $date) : [];
        $all_sales       = $cycle ? $this->care_repository->find_sales_by_cycle_and_date($cycle_id, $date) : [];

        // phân sáng (00:00-11:59) / chiều (12:00-23:59)
        $morning_feeds       = array_filter($all_feeds,       fn($r) => (int)date('H', strtotime($r['recorded_at'])) < 12);
        $evening_feeds       = array_filter($all_feeds,       fn($r) => (int)date('H', strtotime($r['recorded_at'])) >= 12);
        $morning_deaths      = array_filter($all_deaths,      fn($r) => (int)date('H', strtotime($r['recorded_at'])) < 12);
        $evening_deaths      = array_filter($all_deaths,      fn($r) => (int)date('H', strtotime($r['recorded_at'])) >= 12);
        $morning_medications = array_filter($all_medications, fn($r) => (int)date('H', strtotime($r['recorded_at'])) < 12);
        $evening_medications = array_filter($all_medications, fn($r) => (int)date('H', strtotime($r['recorded_at'])) >= 12);
        $morning_sales       = array_filter($all_sales,       fn($r) => (int)date('H', strtotime($r['recorded_at'])) < 12);
        $evening_sales       = array_filter($all_sales,       fn($r) => (int)date('H', strtotime($r['recorded_at'])) >= 12);

        // reindex
        $morning_feeds       = array_values($morning_feeds);
        $evening_feeds       = array_values($evening_feeds);
        $morning_deaths      = array_values($morning_deaths);
        $evening_deaths      = array_values($evening_deaths);
        $morning_medications = array_values($morning_medications);
        $evening_medications = array_values($evening_medications);
        $morning_sales       = array_values($morning_sales);
        $evening_sales       = array_values($evening_sales);

        // trough checks hôm nay
        $today_trough_checks = $cycle
            ? $this->care_repository->find_trough_checks_by_cycle_and_date($cycle_id, $date)
            : [];

        // weight sessions hôm nay
        $all_weight_sessions   = $cycle ? $this->weight_repository->find_sessions_by_cycle($cycle_id) : [];
        $today_weight_sessions = array_values(array_filter(
            $all_weight_sessions,
            fn($w) => substr($w['weighed_at'], 0, 10) === $date
        ));
        foreach ($today_weight_sessions as &$w) {
            $w['session_label'] = (int)date('H', strtotime($w['weighed_at'])) < 12 ? 'morning' : 'evening';
        }
        unset($w);

        // feed types của cycle
        $feed_types            = [];
        $feed_inventory_items = []; // inventory_items có tồn kho (feed)
        $medications_list     = [];
        if ($cycle) {
            $stmt = $this->pdo->prepare("
                SELECT ft.*, fb.name AS brand_name, fb.kg_per_bag
                FROM cycle_feed_programs cfp
                JOIN feed_brands fb ON cfp.feed_brand_id = fb.id
                JOIN feed_types  ft ON ft.feed_brand_id  = fb.id
                WHERE cfp.cycle_id = :cycle_id
                  AND cfp.end_date IS NULL
                  AND ft.status = 'active'
                ORDER BY ft.suggested_stage ASC, ft.code ASC
            ");
            $stmt->execute([':cycle_id' => $cycle_id]);
            $feed_types = $stmt->fetchAll();

            // Lấy inventory_items có tồn kho cho feed (production/feed)
            // Ưu tiên lấy theo ref_feed_type_id (chính xác hơn)
            $inv_stmt = $this->pdo->prepare("
                SELECT ii.*, ft.code AS feed_type_code, fb.name AS brand_name
                FROM inventory_items ii
                LEFT JOIN feed_types ft ON ft.id = ii.ref_feed_type_id
                LEFT JOIN feed_brands fb ON fb.id = COALESCE(ii.ref_feed_brand_id, ft.feed_brand_id)
                WHERE ii.category = 'production'
                  AND ii.sub_category = 'feed'
                  AND ii.status = 'active'
                  AND (ii.quantity IS NULL OR ii.quantity > 0)
                ORDER BY fb.name ASC, ft.code ASC
            ");
            $inv_stmt->execute();
            $feed_inventory_items = $inv_stmt->fetchAll();

            $med_stmt = $this->pdo->query("SELECT * FROM medications WHERE status='active' ORDER BY name ASC");
            $medications_list = $med_stmt->fetchAll();
        }

        // ----------------------------------------------------------------
        // BANNER LOGIC
        // ----------------------------------------------------------------
        $now_hour     = (int) date('H');
        $is_today     = ($date === date('Y-m-d'));
        $current_session = $now_hour < 12 ? 'morning' : 'evening';

        // bữa gần nhất (feed gần nhất trong toàn bộ lịch sử)
        $latest_feed = null;
        if ($cycle) {
            $stmt = $this->pdo->prepare("
                SELECT cf.*, ft.code AS feed_code, fb.name AS brand_name, fb.kg_per_bag
                FROM care_feeds cf
                JOIN feed_types ft  ON cf.feed_type_id  = ft.id
                JOIN feed_brands fb ON ft.feed_brand_id = fb.id
                WHERE cf.cycle_id = :cycle_id
                ORDER BY cf.recorded_at DESC
                LIMIT 1
            ");
            $stmt->execute([':cycle_id' => $cycle_id]);
            $latest_feed = $stmt->fetch() ?: null;
        }

        // kiểm tra trough check cho bữa gần nhất chưa
        $latest_feed_has_trough = false;
        if ($latest_feed) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM feed_trough_checks WHERE ref_feed_id = :id
            ");
            $stmt->execute([':id' => $latest_feed['id']]);
            $latest_feed_has_trough = (int)$stmt->fetchColumn() > 0;
        }

        // đếm số bữa liên tiếp chưa ghi (để xác định warning level)
        // logic: đi ngược từ bữa trước bữa hiện tại
        $missed_sessions = 0;
        if ($cycle) {
            // kiểm tra 4 session gần nhất (2 ngày)
            $check_sessions = [];
            $check_date = $date;
            // thêm các session cần kiểm tra theo thứ tự ngược
            if ($current_session === 'evening') {
                // đang tối → kiểm tra sáng hôm nay, tối hôm qua, sáng hôm qua
                $check_sessions[] = [$check_date, 'morning'];
                $yesterday = date('Y-m-d', strtotime($check_date . ' -1 day'));
                $check_sessions[] = [$yesterday, 'evening'];
                $check_sessions[] = [$yesterday, 'morning'];
            } else {
                // đang sáng → kiểm tra tối hôm qua, sáng hôm qua, tối hôm kia
                $yesterday = date('Y-m-d', strtotime($check_date . ' -1 day'));
                $check_sessions[] = [$yesterday, 'evening'];
                $check_sessions[] = [$yesterday, 'morning'];
                $day_before = date('Y-m-d', strtotime($check_date . ' -2 days'));
                $check_sessions[] = [$day_before, 'evening'];
            }

            foreach ($check_sessions as [$s_date, $s_session]) {
                $hour_start = $s_session === 'morning' ? 0 : 12;
                $hour_end   = $s_session === 'morning' ? 12 : 24;
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM care_feeds
                    WHERE cycle_id = :cycle_id
                      AND DATE(recorded_at) = :date
                      AND HOUR(recorded_at) >= :h_start
                      AND HOUR(recorded_at) < :h_end
                ");
                $stmt->execute([
                    ':cycle_id' => $cycle_id,
                    ':date'     => $s_date,
                    ':h_start'  => $hour_start,
                    ':h_end'    => $hour_end,
                ]);
                $count = (int) $stmt->fetchColumn();
                if ($count === 0) {
                    $missed_sessions++;
                } else {
                    break; // dừng khi gặp bữa đã ghi
                }
            }
        }

        // banner level: 0=ok, 1=warning(vàng), 2=danger(đỏ)
        $banner_level = $missed_sessions === 0 ? 0 : ($missed_sessions === 1 ? 1 : 2);

        $today      = date('d/m/Y', strtotime($date));
        $date_value = $date;

        // Health notes + vaccine schedules
        $stmt = $this->pdo->prepare("SELECT * FROM health_notes WHERE cycle_id=:id ORDER BY recorded_at DESC");
        $stmt->execute([':id' => $cycle->id]);
        $health_notes = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("SELECT * FROM vaccine_schedules WHERE cycle_id=:id ORDER BY scheduled_date ASC");
        $stmt->execute([':id' => $cycle->id]);
        $vaccine_schedules = $stmt->fetchAll();

        // Vaccine program items cho dropdown
        $vaccine_program_items = [];
        if ($cycle->vaccine_program_id) {
            $stmt = $this->pdo->prepare("
                SELECT vpi.*, vb.name as brand_name
                FROM vaccine_program_items vpi
                LEFT JOIN vaccine_brands vb ON vb.id = vpi.vaccine_brand_id
                WHERE vpi.program_id = :pid
                ORDER BY vpi.day_age ASC
            ");
            $stmt->execute([':pid' => $cycle->vaccine_program_id]);
            $vaccine_program_items = $stmt->fetchAll();
        } else {
            $stmt = $this->pdo->query("
                SELECT vpi.*, vb.name as brand_name, vp.name as program_name
                FROM vaccine_program_items vpi
                LEFT JOIN vaccine_brands vb ON vb.id = vpi.vaccine_brand_id
                LEFT JOIN vaccine_programs vp ON vp.id = vpi.program_id
                WHERE vp.active = 1
                ORDER BY vp.name, vpi.day_age ASC
            ");
            $vaccine_program_items = $stmt->fetchAll();
        }
        require view_path('event/event_create.php');
    }
}
