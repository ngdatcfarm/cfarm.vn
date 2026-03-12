<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Vaccine;
use PDO;
class VaccineController
{
    public function __construct(private PDO $pdo) {}

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/x-www-form-urlencoded');
    }

    public function store(array $vars): void
    {
        $cycle_id = (int)$_POST['cycle_id'];
        $cycle    = $this->pdo->prepare("SELECT * FROM cycles WHERE id=:id");
        $cycle->execute([':id' => $cycle_id]);
        $cycle    = $cycle->fetch();
        if (!$cycle) { $this->json(['ok' => false, 'message' => 'Cycle not found'], 404); }

        $scheduled = $_POST['scheduled_date'];
        $day_age   = (int)((strtotime($scheduled) - strtotime($cycle['start_date'])) / 86400) + 1;

        $this->pdo->prepare("
            INSERT INTO vaccine_schedules
                (cycle_id, vaccine_name, scheduled_date, day_age_target, method, dosage, remind_days, notes)
            VALUES
                (:cycle_id, :vaccine_name, :scheduled_date, :day_age, :method, :dosage, :remind_days, :notes)
        ")->execute([
            ':cycle_id'       => $cycle_id,
            ':vaccine_name'   => trim($_POST['vaccine_name'] ?? ''),
            ':scheduled_date' => $scheduled,
            ':day_age'        => $day_age,
            ':method'         => $_POST['method'] ?? 'drink',
            ':dosage'         => trim($_POST['dosage'] ?? '') ?: null,
            ':remind_days'    => (int)($_POST['remind_days'] ?? 1),
            ':notes'          => trim($_POST['notes'] ?? '') ?: null,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        if ($this->isAjax()) {
            $this->json(['ok' => true, 'id' => $id]);
        }
        header('Location: /events/create?cycle_id=' . $cycle_id);
        exit;
    }

    public function show(array $vars): void
    {
        $id  = (int)$vars['id'];
        $vac = $this->pdo->prepare("SELECT * FROM vaccine_schedules WHERE id=:id");
        $vac->execute([':id' => $id]);
        $vac = $vac->fetch();
        if (!$vac) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }
        $this->json(['ok' => true, 'data' => $vac]);
    }

    public function update(array $vars): void
    {
        $id  = (int)$vars['id'];
        $vac = $this->pdo->prepare("SELECT * FROM vaccine_schedules WHERE id=:id");
        $vac->execute([':id' => $id]);
        $vac = $vac->fetch();
        if (!$vac) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }

        $vaccine_name   = trim($_POST['vaccine_name'] ?? $vac['vaccine_name']);
        $scheduled_date = $_POST['scheduled_date'] ?? $vac['scheduled_date'];
        $method         = $_POST['method'] ?? $vac['method'];
        $dosage         = trim($_POST['dosage'] ?? $vac['dosage'] ?? '') ?: null;
        $notes          = trim($_POST['notes'] ?? $vac['notes'] ?? '') ?: null;

        $this->pdo->prepare("
            UPDATE vaccine_schedules
            SET vaccine_name=:vaccine_name, scheduled_date=:scheduled_date,
                method=:method, dosage=:dosage, notes=:notes
            WHERE id=:id
        ")->execute([
            ':vaccine_name'   => $vaccine_name,
            ':scheduled_date' => $scheduled_date,
            ':method'         => $method,
            ':dosage'         => $dosage,
            ':notes'          => $notes,
            ':id'             => $id,
        ]);

        $this->json(['ok' => true]);
    }

    public function done(array $vars): void
    {
        $id  = (int)$vars['id'];
        $vac = $this->pdo->prepare("SELECT * FROM vaccine_schedules WHERE id=:id");
        $vac->execute([':id' => $id]);
        $vac = $vac->fetch();
        if (!$vac) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }

        $this->pdo->prepare("UPDATE vaccine_schedules SET done=1, done_at=NOW() WHERE id=:id")
            ->execute([':id' => $id]);

        if ($this->isAjax()) {
            $this->json(['ok' => true]);
        }
        header('Location: /events/create?cycle_id=' . $vac['cycle_id']);
        exit;
    }

    public function delete(array $vars): void
    {
        $id  = (int)$vars['id'];
        $vac = $this->pdo->prepare("SELECT * FROM vaccine_schedules WHERE id=:id");
        $vac->execute([':id' => $id]);
        $vac = $vac->fetch();
        if (!$vac) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }

        $this->pdo->prepare("DELETE FROM vaccine_schedules WHERE id=:id")->execute([':id' => $id]);

        if ($this->isAjax()) {
            $this->json(['ok' => true]);
        }
        header('Location: /events/create?cycle_id=' . $vac['cycle_id']);
        exit;
    }
}
