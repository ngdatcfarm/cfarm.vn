<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Weight;

use App\Domains\Weight\Entities\WeightSession;
use App\Domains\Weight\Entities\WeightSample;
use App\Infrastructure\Persistence\Mysql\Repositories\WeightRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\CycleRepository;
use PDO;
use App\Domains\Snapshot\SnapshotService;

class WeightController
{
    private WeightRepository $weight_repo;
    private CycleRepository  $cycle_repo;

    private SnapshotService $snapshot;

    public function __construct(private PDO $pdo)
    {
        $this->weight_repo = new WeightRepository($pdo);
        $this->snapshot = new SnapshotService($pdo);
        $this->cycle_repo  = new CycleRepository($pdo);
    }

    private function json(bool $ok, string $msg, array $data = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $data));
    }

    // POST /weight/session
    public function store_session(array $vars): void
    {
        try {
            $cycle_id = (int)$_POST['cycle_id'];
            $cycle    = $this->cycle_repo->find_by_id($cycle_id);
            if (!$cycle) throw new \InvalidArgumentException('Cycle không tồn tại');

            $day_age = isset($_POST['day_age']) && (int)$_POST['day_age'] > 0
                ? (int)$_POST['day_age']
                : (int)((time() - strtotime($cycle->start_date)) / 86400) + 1;

            $session = new WeightSession(
                cycle_id:   $cycle_id,
                day_age:    $day_age,
                weighed_at: $_POST['weighed_at'] ?? date('Y-m-d H:i:s'),
                note:       !empty($_POST['note']) ? trim($_POST['note']) : null,
            );
            $id = $this->weight_repo->create_session($session);
            $this->json(true, 'Đã tạo buổi cân', ['session_id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    // POST /weight/session/{id}/sample
    public function add_sample(array $vars): void
    {
        try {
            $session_id = (int)$vars['id'];
            $weight_g   = (float)($_POST['weight_g'] ?? 0);
            if ($weight_g <= 0)     throw new \InvalidArgumentException('Trọng lượng phải lớn hơn 0');
            if ($weight_g > 20000)  throw new \InvalidArgumentException('Trọng lượng không hợp lệ');

            $sample = new WeightSample(
                session_id: $session_id,
                weight_g:   $weight_g,
                gender:     $_POST['gender'] ?? 'unknown',
            );
            $id      = $this->weight_repo->add_sample($sample);
            $session = $this->weight_repo->find_session_by_id($session_id);
            $samples = $this->weight_repo->find_samples_by_session($session_id);

            $this->json(true, 'Đã thêm mẫu', [
                'sample_id'    => $id,
                'sample_count' => $session['sample_count'],
                'avg_weight_g' => round((float)$session['avg_weight_g'], 1),
                'samples'      => $samples,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    // GET /weight/session/{id}/samples
    public function get_samples(array $vars): void
    {
        $samples = $this->weight_repo->find_samples_by_session((int)$vars['id']);
        $this->json(true, 'ok', ['samples' => $samples]);
    }

    // POST /weight/sample/{id}/delete
    public function delete_sample(array $vars): void
    {
        $this->weight_repo->delete_sample((int)$vars['id']);
        $this->json(true, 'Đã xóa');
    }

    // POST /weight/session/{id}/delete
    public function delete_session(array $vars): void
    {
        $this->weight_repo->delete_session((int)$vars['id']);
        $this->json(true, 'Đã xóa buổi cân');
    }

    // GET /weight/cycle/{id}/chart-data
    public function chart_data(array $vars): void
    {
        $sessions = $this->weight_repo->find_sessions_by_cycle((int)$vars['id']);
        $this->json(true, 'ok', ['sessions' => $sessions]);
    }

    // POST /weight/session/{id}/update
    public function update_session(array $vars): void
    {
        try {
            $id = (int)$vars['id'];
            $this->pdo->prepare("
                UPDATE weight_sessions SET
                    day_age    = :day_age,
                    weighed_at = :weighed_at,
                    note       = :note
                WHERE id = :id
            ")->execute([
                ':day_age'    => (int)$_POST['day_age'],
                ':weighed_at' => $_POST['weighed_at'],
                ':note'       => !empty($_POST['note']) ? trim($_POST['note']) : null,
                ':id'         => $id,
            ]);
            $this->json(true, 'Đã cập nhật');
        } catch (\Exception $e) {
            $this->json(false, $e->getMessage());
        }
    }


    // POST /weight/sample/{id}/update
    public function update_sample(array $vars): void
    {
        try {
            $id       = (int)$vars['id'];
            $weight_g = (float)($_POST['weight_g'] ?? 0);
            if ($weight_g <= 0) throw new \InvalidArgumentException('Trọng lượng phải lớn hơn 0');

            $stmt = $this->pdo->prepare("SELECT session_id FROM weight_samples WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) throw new \InvalidArgumentException('Không tìm thấy mẫu');

            $this->pdo->prepare("UPDATE weight_samples SET weight_g = :g, gender = :gender WHERE id = :id")
                ->execute([':g' => $weight_g, ':gender' => $_POST['gender'] ?? 'unknown', ':id' => $id]);

            $this->weight_repo->recalc_session((int)$row['session_id']);
            $samples = $this->weight_repo->find_samples_by_session((int)$row['session_id']);
            $session = $this->weight_repo->find_session_by_id((int)$row['session_id']);

            $this->json(true, 'Đã cập nhật', [
                'samples'      => $samples,
                'avg_weight_g' => round((float)$session['avg_weight_g'], 1),
                'sample_count' => $session['sample_count'],
            ]);
        } catch (\Exception $e) {
            $this->json(false, $e->getMessage());
        }
    }


    private function trigger_snapshot(int $cycle_id, int $day_age): void
    {
        try {
            $this->snapshot->recalculate_from_day($cycle_id, $day_age);
        } catch (\Throwable $e) {
            error_log("Snapshot error: " . $e->getMessage());
        }
    }

}