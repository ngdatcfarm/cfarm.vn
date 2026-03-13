<?php
/**
 * app/interfaces/http/controllers/web/cycle/cycle_controller.php
 *
 * Controller xử lý các request HTTP liên quan đến cycle.
 * Nhận request, gọi use case tương ứng, trả về view hoặc redirect.
 */

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Web\Cycle;

use App\Domains\Cycle\Usecases\CreateCycleUsecase;
use App\Domains\Cycle\Usecases\UpdateCycleUsecase;
use App\Domains\Cycle\Usecases\CloseCycleUsecase;
use App\Domains\Cycle\Usecases\ListCycleUsecase;
use App\Domains\Cycle\Usecases\SplitCycleUsecase;
use App\Infrastructure\Persistence\Mysql\Repositories\CycleRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\CycleSplitRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\BarnRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\FeedBrandRepository;
use InvalidArgumentException;
use App\Infrastructure\Persistence\Mysql\Repositories\WeightRepository;
use PDO;
use App\Domains\Vaccine\VaccineProgramService;

class CycleController
{
    private CycleRepository      $cycle_repository;
    private CycleSplitRepository $split_repository;
    private BarnRepository       $barn_repository;
    private FeedBrandRepository  $feed_brand_repository;

    private WeightRepository $weight_repo;

    public function __construct(private PDO $pdo)
    {
        $this->cycle_repository      = new CycleRepository($pdo);
        $this->weight_repo           = new WeightRepository($pdo);
        $this->split_repository      = new CycleSplitRepository($pdo);
        $this->barn_repository       = new BarnRepository($pdo);
        $this->feed_brand_repository = new FeedBrandRepository($pdo);
    }

    // GET /barns/{barn_id}/cycles/create
    public function create(array $vars): void
    {
        $barn        = $this->barn_repository->find_by_id((int) $vars['barn_id']);
        $feed_brands = $this->feed_brand_repository->find_active();

        if (!$barn) {
            http_response_code(404);
            echo '404 — không tìm thấy chuồng';
            return;
        }

        require view_path('cycle/cycle_create.php');
    }

    // POST /barns/{barn_id}/cycles
    public function store(array $vars): void
    {
        $barn        = $this->barn_repository->find_by_id((int) $vars['barn_id']);
        $feed_brands = $this->feed_brand_repository->find_active();

        try {
            $usecase  = new CreateCycleUsecase($this->cycle_repository, $this->barn_repository);
            $cycle_id = $usecase->execute(array_merge($_POST, ['barn_id' => $vars['barn_id']]));

            // lưu cycle_feed_program nếu có chọn hãng cám
            if (!empty($_POST['feed_brand_id'])) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO cycle_feed_programs (cycle_id, feed_brand_id, start_date)
                    VALUES (:cycle_id, :feed_brand_id, :start_date)
                ");
                $stmt->execute([
                    ':cycle_id'      => $cycle_id,
                    ':feed_brand_id' => (int) $_POST['feed_brand_id'],
                    ':start_date'    => $_POST['start_date'],
                ]);
            }

            redirect('/cycles/' . $cycle_id);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('cycle/cycle_create.php');
        }
    }

    // GET /cycles/{id}
    public function show(array $vars): void
    {
        $cycle = $this->cycle_repository->find_by_id((int) $vars['id']);

        if (!$cycle) {
            http_response_code(404);
            echo '404 — không tìm thấy cycle';
            return;
        }

        $barn   = $this->barn_repository->find_by_id($cycle->barn_id);
        $splits = $this->split_repository->find_by_cycle($cycle->id);

        // load feed program hiện tại
        $stmt = $this->pdo->prepare("
            SELECT cfp.*, fb.name AS brand_name, fb.kg_per_bag
            FROM cycle_feed_programs cfp
            JOIN feed_brands fb ON cfp.feed_brand_id = fb.id
            WHERE cfp.cycle_id = :cycle_id
            ORDER BY cfp.start_date DESC
        ");
        $stmt->execute([':cycle_id' => $cycle->id]);
        $feed_programs = $stmt->fetchAll();
        $current_feed_program = count($feed_programs) > 0
            ? $feed_programs[0]
            : null;


        // care data — toàn bộ lịch sử
        $stmt = $this->pdo->prepare("SELECT cf.*, ft.code AS feed_code, fb.name AS brand_name, fb.kg_per_bag,
                   (SELECT ftc.remaining_pct FROM feed_trough_checks ftc
                    WHERE ftc.ref_feed_id = cf.id
                    ORDER BY ftc.checked_at DESC LIMIT 1) AS latest_remaining_pct,
                   (SELECT ftc.checked_at FROM feed_trough_checks ftc
                    WHERE ftc.ref_feed_id = cf.id
                    ORDER BY ftc.checked_at DESC LIMIT 1) AS latest_checked_at
            FROM care_feeds cf
            JOIN feed_types ft  ON cf.feed_type_id  = ft.id
            JOIN feed_brands fb ON ft.feed_brand_id = fb.id
            WHERE cf.cycle_id = :cycle_id
            ORDER BY cf.recorded_at ASC");
        $stmt->execute([":cycle_id" => $cycle->id]);
        $care_feeds = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("SELECT * FROM care_deaths WHERE cycle_id=:id ORDER BY recorded_at ASC");
        $stmt->execute([":id" => $cycle->id]);
        $care_deaths = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("SELECT * FROM care_medications WHERE cycle_id=:id ORDER BY recorded_at ASC");
        $stmt->execute([":id" => $cycle->id]);
        $care_medications = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("SELECT * FROM care_sales WHERE cycle_id=:id ORDER BY recorded_at ASC");
        $stmt->execute([":id" => $cycle->id]);
        $care_sales = $stmt->fetchAll();
        $weight_sessions = $this->weight_repo->find_sessions_by_cycle($cycle->id);

        // Vaccine programs for dropdown
        $vaccine_svc      = new VaccineProgramService($this->pdo);
        $vaccine_programs = $vaccine_svc->get_programs();

        // Expenses
        $stmt = $this->pdo->prepare("SELECT * FROM care_expenses WHERE cycle_id=:id ORDER BY recorded_at DESC");
        $stmt->execute([':id' => $cycle->id]);
        $expenses = $stmt->fetchAll();

        // Litters
        $stmt = $this->pdo->prepare("
            SELECT l.*, ii.name as item_name
            FROM care_litters l
            LEFT JOIN inventory_items ii ON ii.id = l.item_id
            WHERE l.cycle_id = :id
            ORDER BY l.recorded_at DESC
        ");
        $stmt->execute([':id' => $cycle->id]);
        $litters = $stmt->fetchAll();

        // Health notes
        $stmt = $this->pdo->prepare("SELECT * FROM health_notes WHERE cycle_id=:id ORDER BY recorded_at DESC");
        $stmt->execute([':id' => $cycle->id]);
        $health_notes = $stmt->fetchAll();

        // Vaccine schedules
        $stmt = $this->pdo->prepare("SELECT * FROM vaccine_schedules WHERE cycle_id=:id ORDER BY scheduled_date ASC");
        $stmt->execute([':id' => $cycle->id]);
        $vaccine_schedules = $stmt->fetchAll();

        require view_path('cycle/cycle_show.php');
    }

    // GET /cycles/{id}/edit
    public function edit(array $vars): void
    {
        $cycle = $this->cycle_repository->find_by_id((int) $vars['id']);

        if (!$cycle) {
            http_response_code(404);
            echo '404 — không tìm thấy cycle';
            return;
        }

        $barn = $this->barn_repository->find_by_id($cycle->barn_id);
        require view_path('cycle/cycle_edit.php');
    }

    // POST /cycles/{id}
    public function update(array $vars): void
    {
        try {
            $usecase = new UpdateCycleUsecase($this->cycle_repository);
            $usecase->execute((int) $vars['id'], $_POST);
            redirect('/cycles/' . $vars['id']);
        } catch (InvalidArgumentException $e) {
            $cycle = $this->cycle_repository->find_by_id((int) $vars['id']);
            $barn  = $this->barn_repository->find_by_id($cycle->barn_id);
            $error = $e->getMessage();
            require view_path('cycle/cycle_edit.php');
        }
    }

    // GET /cycles/{id}/close
    public function close_form(array $vars): void
    {
        $cycle = $this->cycle_repository->find_by_id((int) $vars['id']);

        if (!$cycle) {
            http_response_code(404);
            echo '404 — không tìm thấy cycle';
            return;
        }

        $barn = $this->barn_repository->find_by_id($cycle->barn_id);
        require view_path('cycle/cycle_close.php');
    }

    // POST /cycles/{id}/close
    public function close(array $vars): void
    {
        try {
            $usecase = new CloseCycleUsecase($this->cycle_repository);
            $usecase->execute((int) $vars['id'], $_POST);

            // đóng cycle_feed_program
            $stmt = $this->pdo->prepare("
                UPDATE cycle_feed_programs
                SET end_date = :end_date
                WHERE cycle_id = :cycle_id AND end_date IS NULL
            ");
            $stmt->execute([
                ':end_date'  => $_POST['end_date'],
                ':cycle_id'  => (int) $vars['id'],
            ]);

            redirect('/cycles/' . $vars['id']);
        } catch (InvalidArgumentException $e) {
            $cycle = $this->cycle_repository->find_by_id((int) $vars['id']);
            $barn  = $this->barn_repository->find_by_id($cycle->barn_id);
            $error = $e->getMessage();
            require view_path('cycle/cycle_close.php');
        }
    }

    // GET /cycles/{id}/split
    public function split_form(array $vars): void
    {
        $cycle     = $this->cycle_repository->find_by_id((int) $vars['id']);

        if (!$cycle) {
            http_response_code(404);
            echo '404 — không tìm thấy cycle';
            return;
        }

        $barn      = $this->barn_repository->find_by_id($cycle->barn_id);
        $all_barns = $this->barn_repository->find_all();
        require view_path('cycle/cycle_split.php');
    }

    // POST /cycles/{id}/split
    public function split(array $vars): void
    {
        try {
            $usecase = new SplitCycleUsecase(
                $this->cycle_repository,
                $this->split_repository,
                $this->barn_repository
            );
            $new_id = $usecase->execute(array_merge($_POST, ['from_cycle_id' => $vars['id']]));
            redirect('/cycles/' . $new_id);
        } catch (InvalidArgumentException $e) {
            $cycle     = $this->cycle_repository->find_by_id((int) $vars['id']);
            $barn      = $this->barn_repository->find_by_id($cycle->barn_id);
            $all_barns = $this->barn_repository->find_all();
            $error     = $e->getMessage();
            require view_path('cycle/cycle_split.php');
        }
    }

    // GET /cycles/{id}/feed-program
    public function feed_program_form(array $vars): void
    {
        $cycle       = $this->cycle_repository->find_by_id((int) $vars['id']);
        $barn        = $this->barn_repository->find_by_id($cycle->barn_id);
        $feed_brands = $this->feed_brand_repository->find_active();
        require view_path('cycle/cycle_feed_program.php');
    }

    // POST /cycles/{id}/feed-program
    public function feed_program_store(array $vars): void
    {
        $cycle_id = (int) $vars['id'];
        try {
            if (empty($_POST['feed_brand_id'])) {
                throw new InvalidArgumentException('Vui lòng chọn hãng cám');
            }

            // đóng feed program cũ nếu có
            $stmt = $this->pdo->prepare("
                UPDATE cycle_feed_programs
                SET end_date = :end_date
                WHERE cycle_id = :cycle_id AND end_date IS NULL
            ");
            $stmt->execute([
                ':end_date'  => $_POST['start_date'],
                ':cycle_id'  => $cycle_id,
            ]);

            // tạo feed program mới
            $stmt = $this->pdo->prepare("
                INSERT INTO cycle_feed_programs (cycle_id, feed_brand_id, start_date, note)
                VALUES (:cycle_id, :feed_brand_id, :start_date, :note)
            ");
            $stmt->execute([
                ':cycle_id'      => $cycle_id,
                ':feed_brand_id' => (int) $_POST['feed_brand_id'],
                ':start_date'    => $_POST['start_date'],
                ':note'          => $_POST['note'] ?? null,
            ]);

            redirect('/cycles/' . $cycle_id);
        } catch (InvalidArgumentException $e) {
            $cycle       = $this->cycle_repository->find_by_id($cycle_id);
            $barn        = $this->barn_repository->find_by_id($cycle->barn_id);
            $feed_brands = $this->feed_brand_repository->find_active();
            $error       = $e->getMessage();
            require view_path('cycle/cycle_feed_program.php');
        }
    }

    // GET /cycles/{id}/feed-stages
    public function feed_stages_form(array $vars): void
    {
        $cycle_id = (int) $vars['id'];
        $cycle    = $this->cycle_repository->find_by_id($cycle_id);
        $barn     = $this->barn_repository->find_by_id($cycle->barn_id);

        // lấy hãng cám hiện tại
        $stmt = $this->pdo->prepare("
            SELECT fb.* FROM cycle_feed_programs cfp
            JOIN feed_brands fb ON cfp.feed_brand_id = fb.id
            WHERE cfp.cycle_id = :id AND cfp.end_date IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $cycle_id]);
        $current_brand = $stmt->fetch() ?: null;

        // load feed_types của hãng hiện tại
        $feed_types = [];
        if ($current_brand) {
            $stmt = $this->pdo->prepare("
                SELECT ft.*,
                    CASE ft.suggested_stage
                        WHEN 'chick'  THEN 'Gà con'
                        WHEN 'grower' THEN 'Gà choai'
                        WHEN 'adult'  THEN 'Gà trưởng thành'
                        ELSE 'Tất cả'
                    END AS suggested_stage_label
                FROM feed_types ft
                WHERE ft.feed_brand_id = :brand_id AND ft.status = 'active'
                ORDER BY ft.suggested_stage ASC, ft.code ASC
            ");
            $stmt->execute([':brand_id' => $current_brand['id']]);
            $feed_types = $stmt->fetchAll();
        }

        // load stage configs hiện tại — chỉ lấy config mới nhất của mỗi stage
        $stmt = $this->pdo->prepare("
            SELECT cfs1.*
            FROM cycle_feed_stages cfs1
            WHERE cfs1.cycle_id = :id
              AND cfs1.effective_date = (
                  SELECT MAX(cfs2.effective_date)
                  FROM cycle_feed_stages cfs2
                  WHERE cfs2.cycle_id = cfs1.cycle_id
                    AND cfs2.stage    = cfs1.stage
              )
        ");
        $stmt->execute([':id' => $cycle_id]);
        $rows = $stmt->fetchAll();

        $stage_configs = [];
        foreach ($rows as $row) {
            $stage_configs[$row['stage']] = $row;
        }

        require view_path('cycle/cycle_feed_stages.php');
    }

    // POST /cycles/{id}/feed-stages
    public function feed_stages_store(array $vars): void
    {
        $cycle_id = (int) $vars['id'];
        $cycle    = $this->cycle_repository->find_by_id($cycle_id);
        $barn     = $this->barn_repository->find_by_id($cycle->barn_id);

        // reload data cho view nếu có lỗi
        $stmt = $this->pdo->prepare("
            SELECT fb.* FROM cycle_feed_programs cfp
            JOIN feed_brands fb ON cfp.feed_brand_id = fb.id
            WHERE cfp.cycle_id = :id AND cfp.end_date IS NULL LIMIT 1
        ");
        $stmt->execute([':id' => $cycle_id]);
        $current_brand = $stmt->fetch() ?: null;

        $feed_types = [];
        if ($current_brand) {
            $stmt = $this->pdo->prepare("
                SELECT ft.*,
                    CASE ft.suggested_stage
                        WHEN 'chick'  THEN 'Gà con'
                        WHEN 'grower' THEN 'Gà choai'
                        WHEN 'adult'  THEN 'Gà trưởng thành'
                        ELSE 'Tất cả'
                    END AS suggested_stage_label
                FROM feed_types ft
                WHERE ft.feed_brand_id = :brand_id AND ft.status = 'active'
                ORDER BY ft.suggested_stage ASC, ft.code ASC
            ");
            $stmt->execute([':brand_id' => $current_brand['id']]);
            $feed_types = $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare("
            SELECT cfs1.* FROM cycle_feed_stages cfs1
            WHERE cfs1.cycle_id = :id
              AND cfs1.effective_date = (
                  SELECT MAX(cfs2.effective_date) FROM cycle_feed_stages cfs2
                  WHERE cfs2.cycle_id = cfs1.cycle_id AND cfs2.stage = cfs1.stage
              )
        ");
        $stmt->execute([':id' => $cycle_id]);
        $rows = $stmt->fetchAll();
        $stage_configs = [];
        foreach ($rows as $row) { $stage_configs[$row['stage']] = $row; }

        try {
            $stage = $_POST['stage'] ?? '';
            if (!in_array($stage, ['chick','grower','adult'])) {
                throw new \InvalidArgumentException('Stage không hợp lệ');
            }
            if (empty($_POST['primary_feed_type_id'])) {
                throw new \InvalidArgumentException('Vui lòng chọn mã cám chính');
            }

            $mix_type_id = !empty($_POST['mix_feed_type_id']) ? (int)$_POST['mix_feed_type_id'] : null;
            $mix_ratio   = $mix_type_id ? (int)($_POST['mix_ratio'] ?? 25) : null;

            $stmt = $this->pdo->prepare("
                INSERT INTO cycle_feed_stages
                    (cycle_id, stage, primary_feed_type_id, mix_feed_type_id, mix_ratio, effective_date, note)
                VALUES
                    (:cycle_id, :stage, :primary, :mix, :ratio, :date, :note)
            ");
            $stmt->execute([
                ':cycle_id' => $cycle_id,
                ':stage'    => $stage,
                ':primary'  => (int) $_POST['primary_feed_type_id'],
                ':mix'      => $mix_type_id,
                ':ratio'    => $mix_ratio,
                ':date'     => $_POST['effective_date'] ?? date('Y-m-d'),
                ':note'     => !empty($_POST['note']) ? $_POST['note'] : null,
            ]);

            // reload stage_configs
            $stmt = $this->pdo->prepare("
                SELECT cfs1.* FROM cycle_feed_stages cfs1
                WHERE cfs1.cycle_id = :id
                  AND cfs1.effective_date = (
                      SELECT MAX(cfs2.effective_date) FROM cycle_feed_stages cfs2
                      WHERE cfs2.cycle_id = cfs1.cycle_id AND cfs2.stage = cfs1.stage
                  )
            ");
            $stmt->execute([':id' => $cycle_id]);
            $rows = $stmt->fetchAll();
            $stage_configs = [];
            foreach ($rows as $row) { $stage_configs[$row['stage']] = $row; }

            $success = 'Đã lưu cấu hình ' . $stage;
            require view_path('cycle/cycle_feed_stages.php');

        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('cycle/cycle_feed_stages.php');
        }
    }

    // GET /cycles/{id}/feed-chart-data
    public function feed_chart_data(array $vars): void
    {
        header('Content-Type: application/json');
        $cycle_id = (int)$vars['id'];
        $cycle    = $this->cycle_repository->find_by_id($cycle_id);
        if (!$cycle) { echo json_encode(['ok' => false]); return; }

        $start_date = $cycle->start_date;

        $stmt = $this->pdo->prepare("
            SELECT
                DATEDIFF(MIN(DATE(recorded_at)), :start_date) + 1 AS day_age,
                MIN(DATE(recorded_at)) AS feed_date,
                SUM(bags)              AS total_bags,
                SUM(kg_actual)         AS total_kg
            FROM care_feeds
            WHERE cycle_id = :cycle_id
            GROUP BY DATE(recorded_at)
            ORDER BY feed_date ASC
        ");
        $stmt->execute([':cycle_id' => $cycle_id, ':start_date' => $start_date]);
        $feed_rows = $stmt->fetchAll();

        $stmt2 = $this->pdo->prepare("
            SELECT
                DATEDIFF(DATE(ftc.checked_at), :start_date) + 1 AS day_age,
                ftc.checked_at,
                ftc.remaining_pct,
                cf.kg_actual AS feed_kg
            FROM feed_trough_checks ftc
            JOIN care_feeds cf ON ftc.ref_feed_id = cf.id
            WHERE ftc.cycle_id = :cycle_id
            ORDER BY ftc.checked_at ASC
        ");
        $stmt2->execute([':cycle_id' => $cycle_id, ':start_date' => $start_date]);
        $trough_rows = $stmt2->fetchAll();

        echo json_encode([
            'ok'            => true,
            'start_date'    => $start_date,
            'feed_by_day'   => array_values($feed_rows),
            'trough_checks' => array_values($trough_rows),
        ]);
    }


    // POST /cycles/{id}/apply-vaccine-program
    public function apply_vaccine_program(array $vars): void
    {
        $cycle_id   = (int)$vars['id'];
        $program_id = (int)($_POST['vaccine_program_id'] ?? 0);

        if ($program_id) {
            $svc   = new VaccineProgramService($this->pdo);
            $count = $svc->apply($cycle_id, $program_id);
            $msg   = "Da ap dung bo lich - {$count} vaccine";
        } else {
            $this->pdo->prepare("UPDATE cycles SET vaccine_program_id=NULL WHERE id=:id")
                ->execute([':id' => $cycle_id]);
            $this->pdo->prepare("
                DELETE FROM vaccine_schedules
                WHERE cycle_id=:cid AND done=0 AND program_item_id IS NOT NULL
            ")->execute([':cid' => $cycle_id]);
            $msg = "Da bo bo lich vaccine";
        }

        header('Location: /cycles/' . $cycle_id . '?tab=vaccine&msg=' . urlencode($msg) . '#vaccine');
        exit;
    }


    // GET /cycles/{id}/litters
    public function list_litters(array $vars): void
    {
        $cycle_id = (int)$vars['id'];
        $stmt = $this->pdo->prepare("
            SELECT l.*, ii.name as item_name
            FROM care_litters l
            LEFT JOIN inventory_items ii ON ii.id = l.item_id
            WHERE l.cycle_id = :id
            ORDER BY l.recorded_at DESC
        ");
        $stmt->execute([':id' => $cycle_id]);
        $litters = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode($litters);
    }


    // POST /cycles/{id}/litters/{litter_id}/delete
    public function delete_litter(array $vars): void
    {
        $cycle_id = (int)$vars['id'];
        $litter_id = (int)$vars['litter_id'];

        $stmt = $this->pdo->prepare("DELETE FROM care_litters WHERE id = :id AND cycle_id = :cycle_id");
        $stmt->execute([':id' => $litter_id, ':cycle_id' => $cycle_id]);

        header('Location: /cycles/' . $cycle_id . '?tab=litter#litter');
        exit;
    }

}