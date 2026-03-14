<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Care;

use App\Domains\Care\Usecases\RecordFeedUsecase;
use App\Domains\Care\Usecases\RecordDeathUsecase;
use App\Domains\Care\Usecases\RecordMedicationUsecase;
use App\Domains\Care\Usecases\RecordSaleUsecase;
use App\Domains\Care\Usecases\RecordTroughCheckUsecase;
use App\Domains\Care\Services\CareEditPermission;
use App\Infrastructure\Persistence\Mysql\Repositories\CareRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\CycleRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\FeedTypeRepository;
use PDO;
use App\Domains\Snapshot\SnapshotService;

class CareController
{
    private CareRepository  $care_repository;
    private CycleRepository $cycle_repository;

    public function __construct(private PDO $pdo)
    {
        $this->snapshot = new SnapshotService($pdo);
        $this->care_repository  = new CareRepository($pdo);
        $this->cycle_repository = new CycleRepository($pdo);
    }

    private SnapshotService $snapshot;

    private function trigger_snapshot(int $cycle_id, ?string $recorded_at): void
    {
        try {
            $start_stmt = $this->pdo->prepare("SELECT start_date FROM cycles WHERE id=:id");
            $start_stmt->execute([':id' => $cycle_id]);
            $cycle = $start_stmt->fetch();
            if (!$cycle) return;

            $from_day = $recorded_at
                ? max(1, (int)((strtotime(substr($recorded_at, 0, 10)) - strtotime($cycle['start_date'])) / 86400) + 1)
                : 1;
            $this->snapshot->recalculate_from_day($cycle_id, $from_day);
        } catch (\Throwable $e) {
            // snapshot failure không block user
            error_log("Snapshot error: " . $e->getMessage());
        }
    }

    private function json(bool $ok, string $message, array $data = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $data));
    }

    // ----------------------------------------------------------------
    // CREATE
    // ----------------------------------------------------------------

    public function store_feed(array $vars): void
    {
        try {
            // KIỂM TRA: Cycle phải có feed_program đang active
            $cycle_id = (int)$_POST['cycle_id'];
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM cycle_feed_programs
                WHERE cycle_id = :cycle_id AND end_date IS NULL
            ");
            $stmt->execute([':cycle_id' => $cycle_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                throw new \InvalidArgumentException('Cycle chưa cài đặt hãng cám. Vui lòng cài đặt trước khi ghi cho ăn.');
            }

            $usecase = new RecordFeedUsecase(
                $this->care_repository,
                new FeedTypeRepository($this->pdo),
                $this->cycle_repository
            );
            $id = $usecase->execute($cycle_id, $_POST);
            $this->trigger_snapshot((int)$_POST['cycle_id'], $_POST['recorded_at'] ?? null);
            // AUTO DEDUCT INVENTORY
            try {
                $stock_svc = new \App\Domains\Inventory\Services\InventoryStockService($this->pdo);
                $stock_svc->deduct_feed($id, (int)$_POST['cycle_id'], (int)$_POST['feed_type_id'], (float)$_POST['bags']);
            } catch (\InvalidArgumentException $e) {
                // Đã ghi care_feeds nhưng không trừ được inventory - báo lỗi cho user
                $this->json(false, $e->getMessage());
                return;
            } catch (\Throwable $e) {
                error_log("Inventory deduct_feed: ".$e->getMessage());
            }
            $this->json(true, 'Đã ghi chép cho ăn', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_death(array $vars): void
    {
        try {
            $usecase = new RecordDeathUsecase($this->care_repository, $this->cycle_repository);
            $id = $usecase->execute((int)$_POST['cycle_id'], $_POST);
            $this->trigger_snapshot((int)$_POST['cycle_id'], $_POST['recorded_at'] ?? null);
            $this->json(true, 'Đã ghi chép hao hụt', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_medication(array $vars): void
    {
        try {
            $usecase = new RecordMedicationUsecase($this->care_repository, $this->cycle_repository);
            $id = $usecase->execute((int)$_POST['cycle_id'], $_POST);
            // AUTO DEDUCT INVENTORY
            try {
                $stock_svc = new \App\Domains\Inventory\Services\InventoryStockService($this->pdo);
                $stock_svc->deduct_medication($id, (int)$_POST['cycle_id'], !empty($_POST['medication_id']) ? (int)$_POST['medication_id'] : null, (float)$_POST['dosage'], $_POST['unit'] ?? '');
            } catch (\InvalidArgumentException $e) {
                $this->json(false, $e->getMessage());
                return;
            } catch (\Throwable $e) { error_log("Inventory deduct_med: ".$e->getMessage()); }
            $this->json(true, 'Đã ghi chép thuốc', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_sale(array $vars): void
    {
        try {
            $usecase = new RecordSaleUsecase($this->care_repository, $this->cycle_repository);
            $id = $usecase->execute((int)$_POST['cycle_id'], $_POST);
            $this->trigger_snapshot((int)$_POST['cycle_id'], $_POST['recorded_at'] ?? null);
            $this->json(true, 'Đã ghi chép bán gà', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_trough_check(array $vars): void
    {
        try {
            $usecase = new RecordTroughCheckUsecase($this->care_repository);
            $id = $usecase->execute((int)$_POST['cycle_id'], $_POST);
            $this->trigger_snapshot((int)$_POST['cycle_id'], $_POST['recorded_at'] ?? null);
            $this->json(true, 'Đã ghi nhận kiểm tra máng', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // DELETE
    // ----------------------------------------------------------------

    private function handle_delete(string $table, int $id, ?string $override_pass): void
    {
        $stmt = $this->pdo->prepare("SELECT recorded_at FROM {$table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->json(false, 'Không tìm thấy bản ghi');
            return;
        }

        if (!CareEditPermission::can_delete($row['recorded_at'], $override_pass)) {
            $deadline = CareEditPermission::delete_deadline($row['recorded_at']);
            $this->json(false, "Quá hạn xóa ({$deadline}). Nhập mật khẩu để tiếp tục.", [
                'need_pass' => true
            ]);
            return;
        }

        // Lấy cycle_id trước khi xóa để trigger snapshot
        $cycle_stmt = $this->pdo->prepare("SELECT cycle_id, recorded_at FROM {$table} WHERE id = :id");
        $cycle_stmt->execute([':id' => $id]);
        $cycle_row = $cycle_stmt->fetch();

        $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id")->execute([':id' => $id]);

        if ($cycle_row && isset($cycle_row['cycle_id'])) {
            $this->trigger_snapshot((int)$cycle_row['cycle_id'], $cycle_row['recorded_at']);
        }
        $this->json(true, 'Đã xóa');
    }

    public function delete_feed(array $vars): void
    {
        $this->handle_delete('care_feeds', (int)$vars['id'], $_POST['override_pass'] ?? null);
    }

    public function delete_death(array $vars): void
    {
        $this->handle_delete('care_deaths', (int)$vars['id'], $_POST['override_pass'] ?? null);
    }

    public function delete_medication(array $vars): void
    {
        $this->handle_delete('care_medications', (int)$vars['id'], $_POST['override_pass'] ?? null);
    }

    public function delete_sale(array $vars): void
    {
        $this->handle_delete('care_sales', (int)$vars['id'], $_POST['override_pass'] ?? null);
    }

    public function delete_trough_check(array $vars): void
    {
        // trough check dùng checked_at thay vì recorded_at
        $id   = (int)$vars['id'];
        $pass = $_POST['override_pass'] ?? null;
        $stmt = $this->pdo->prepare("SELECT checked_at AS recorded_at FROM feed_trough_checks WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { $this->json(false, 'Không tìm thấy'); return; }
        if (!CareEditPermission::can_delete($row['recorded_at'], $pass)) {
            $this->json(false, 'Quá hạn xóa. Nhập mật khẩu để tiếp tục.', ['need_pass' => true]);
            return;
        }
        // Lấy cycle_id qua care_feeds
        $cid_stmt = $this->pdo->prepare("
            SELECT cf.cycle_id, ftc.checked_at AS recorded_at
            FROM feed_trough_checks ftc
            JOIN care_feeds cf ON ftc.ref_feed_id = cf.id
            WHERE ftc.id = :id
        ");
        $cid_stmt->execute([':id' => $id]);
        $cid_row = $cid_stmt->fetch();

        $this->pdo->prepare("DELETE FROM feed_trough_checks WHERE id = :id")->execute([':id' => $id]);

        if ($cid_row) {
            $this->trigger_snapshot((int)$cid_row['cycle_id'], $cid_row['recorded_at']);
        }
        $this->json(true, 'Đã xóa');
    }

    // ----------------------------------------------------------------
    // EDIT — trả về data để frontend prefill form
    // ----------------------------------------------------------------

    public function get_feed(array $vars): void
    {
        $id   = (int)$vars['id'];
        $pass = $_GET['override_pass'] ?? null;
        $row  = $this->care_repository->find_feed_by_id($id);
        if (!$row) { $this->json(false, 'Không tìm thấy'); return; }
        if (!CareEditPermission::can_edit($row['recorded_at'], $pass)) {
            $this->json(false, 'Quá hạn sửa. Nhập mật khẩu để tiếp tục.', ['need_pass' => true]);
            return;
        }
        $this->json(true, 'ok', ['data' => $row]);
    }

    public function get_death(array $vars): void
    {
        $this->get_generic('care_deaths', (int)$vars['id'], $_GET['override_pass'] ?? null);
    }

    public function get_medication(array $vars): void
    {
        $this->get_generic('care_medications', (int)$vars['id'], $_GET['override_pass'] ?? null);
    }

    public function get_sale(array $vars): void
    {
        $this->get_generic('care_sales', (int)$vars['id'], $_GET['override_pass'] ?? null);
    }

    private function get_generic(string $table, int $id, ?string $pass): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { $this->json(false, 'Không tìm thấy'); return; }
        if (!CareEditPermission::can_edit($row['recorded_at'], $pass)) {
            $this->json(false, 'Quá hạn sửa. Nhập mật khẩu để tiếp tục.', ['need_pass' => true]);
            return;
        }
        $this->json(true, 'ok', ['data' => $row]);
    }

    // ----------------------------------------------------------------
    // UPDATE
    // ----------------------------------------------------------------

    public function update_feed(array $vars): void
    {
        $id   = (int)$vars['id'];
        $pass = $_POST['override_pass'] ?? null;
        $row  = $this->care_repository->find_feed_by_id($id);
        if (!$row) { $this->json(false, 'Không tìm thấy'); return; }
        if (!CareEditPermission::can_edit($row['recorded_at'], $pass)) {
            $this->json(false, 'Quá hạn sửa. Nhập mật khẩu để tiếp tục.', ['need_pass' => true]);
            return;
        }
        $bags      = (float)($_POST['bags'] ?? $row['bags']);
        $kg_actual = !empty($_POST['kg_actual']) ? (float)$_POST['kg_actual'] : $bags * (float)($_POST['kg_per_bag'] ?? 0);
        $this->pdo->prepare("
            UPDATE care_feeds SET bags=:bags, kg_actual=:kg, note=:note, recorded_at=:rat WHERE id=:id
        ")->execute([
            ':bags' => $bags,
            ':kg'   => $kg_actual,
            ':note' => $_POST['note'] ?? null,
            ':rat'  => $_POST['recorded_at'] ?? $row['recorded_at'],
            ':id'   => $id,
        ]);
        $this->json(true, 'Đã cập nhật');
    }

    public function update_death(array $vars): void
    {
        $id  = (int)$vars['id'];
        $pass = $_POST['override_pass'] ?? null;
        $stmt = $this->pdo->prepare("SELECT * FROM care_deaths WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { $this->json(false, 'Không tìm thấy'); return; }
        if (!CareEditPermission::can_edit($row['recorded_at'], $pass)) {
            $this->json(false, 'Quá hạn sửa.', ['need_pass' => true]); return;
        }
        $this->pdo->prepare("
            UPDATE care_deaths SET quantity=:q, reason=:r, symptoms=:s, note=:n, recorded_at=:rat WHERE id=:id
        ")->execute([
            ':q'   => (int)($_POST['quantity'] ?? $row['quantity']),
            ':r'   => $_POST['reason']   ?? $row['reason'],
            ':s'   => $_POST['symptoms'] ?? $row['symptoms'],
            ':n'   => $_POST['note']     ?? $row['note'],
            ':rat' => $_POST['recorded_at'] ?? $row['recorded_at'],
            ':id'  => $id,
        ]);
        $this->json(true, 'Đã cập nhật');
    }

    public function update_medication(array $vars): void
    {
        $id   = (int)$vars['id'];
        $pass = $_POST['override_pass'] ?? null;
        $stmt = $this->pdo->prepare("SELECT * FROM care_medications WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { $this->json(false, 'Không tìm thấy'); return; }
        if (!CareEditPermission::can_edit($row['recorded_at'], $pass)) {
            $this->json(false, 'Quá hạn sửa.', ['need_pass' => true]); return;
        }
        $this->pdo->prepare("
            UPDATE care_medications SET medication_name=:mn, dosage=:d, unit=:u, method=:m, note=:n, recorded_at=:rat WHERE id=:id
        ")->execute([
            ':mn'  => $_POST['medication_name'] ?? $row['medication_name'],
            ':d'   => $_POST['dosage']          ?? $row['dosage'],
            ':u'   => $_POST['unit']            ?? $row['unit'],
            ':m'   => $_POST['method']          ?? $row['method'],
            ':n'   => $_POST['note']            ?? $row['note'],
            ':rat' => $_POST['recorded_at']     ?? $row['recorded_at'],
            ':id'  => $id,
        ]);
        $this->json(true, 'Đã cập nhật');
    }

    public function update_sale(array $vars): void
    {
        $id   = (int)$vars['id'];
        $pass = $_POST['override_pass'] ?? null;
        $stmt = $this->pdo->prepare("SELECT * FROM care_sales WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { $this->json(false, 'Không tìm thấy'); return; }
        if (!CareEditPermission::can_edit($row['recorded_at'], $pass)) {
            $this->json(false, 'Quá hạn sửa.', ['need_pass' => true]); return;
        }
        $weight = (float)($_POST['weight_kg']    ?? $row['weight_kg']);
        $price  = (float)($_POST['price_per_kg'] ?? $row['price_per_kg']);
        $this->pdo->prepare("
            UPDATE care_sales SET weight_kg=:w, price_per_kg=:p, total_amount=:t,
            quantity=:q, gender=:g, note=:n, recorded_at=:rat WHERE id=:id
        ")->execute([
            ':w'   => $weight,
            ':p'   => $price,
            ':t'   => $weight * $price,
            ':q'   => $_POST['quantity'] ?? $row['quantity'],
            ':g'   => $_POST['gender']   ?? $row['gender'],
            ':n'   => $_POST['note']     ?? $row['note'],
            ':rat' => $_POST['recorded_at'] ?? $row['recorded_at'],
            ':id'  => $id,
        ]);
        $this->json(true, 'Đã cập nhật');
    }
}
