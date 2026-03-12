<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Health;
use PDO;
class HealthController
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

        $day_age    = (int)((strtotime($_POST['recorded_at'] ?? 'today') - strtotime($cycle['start_date'])) / 86400) + 1;
        $image_path = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','heic'];
            if (in_array($ext, $allowed) && $_FILES['image']['size'] < 10 * 1024 * 1024) {
                $fname = 'health_' . $cycle_id . '_' . time() . '.' . $ext;
                $dest  = '/var/www/app.cfarm.vn/public/uploads/health/' . $fname;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $image_path = '/uploads/health/' . $fname;
                }
            }
        }
        $this->pdo->prepare("
            INSERT INTO health_notes (cycle_id, recorded_at, day_age, severity, symptoms, image_path)
            VALUES (:cycle_id, :recorded_at, :day_age, :severity, :symptoms, :image_path)
        ")->execute([
            ':cycle_id'    => $cycle_id,
            ':recorded_at' => $_POST['recorded_at'] ?? date('Y-m-d H:i:s'),
            ':day_age'     => $day_age,
            ':severity'    => $_POST['severity'] ?? 'mild',
            ':symptoms'    => trim($_POST['symptoms'] ?? ''),
            ':image_path'  => $image_path,
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
        $id   = (int)$vars['id'];
        $note = $this->pdo->prepare("SELECT * FROM health_notes WHERE id=:id");
        $note->execute([':id' => $id]);
        $note = $note->fetch();
        if (!$note) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }
        $this->json(['ok' => true, 'data' => $note]);
    }

    public function update(array $vars): void
    {
        $id   = (int)$vars['id'];
        $note = $this->pdo->prepare("SELECT * FROM health_notes WHERE id=:id");
        $note->execute([':id' => $id]);
        $note = $note->fetch();
        if (!$note) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }

        $symptoms = trim($_POST['symptoms'] ?? $note['symptoms']);
        $severity = $_POST['severity'] ?? $note['severity'];

        $this->pdo->prepare("
            UPDATE health_notes SET symptoms=:symptoms, severity=:severity WHERE id=:id
        ")->execute([
            ':symptoms' => $symptoms,
            ':severity' => $severity,
            ':id'       => $id,
        ]);

        $this->json(['ok' => true]);
    }

    public function resolve(array $vars): void
    {
        $id   = (int)$vars['id'];
        $note = $this->pdo->prepare("SELECT * FROM health_notes WHERE id=:id");
        $note->execute([':id' => $id]);
        $note = $note->fetch();
        if (!$note) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }

        $this->pdo->prepare("UPDATE health_notes SET resolved=1, resolved_at=NOW() WHERE id=:id")
            ->execute([':id' => $id]);

        if ($this->isAjax()) {
            $this->json(['ok' => true]);
        }
        header('Location: /events/create?cycle_id=' . $note['cycle_id']);
        exit;
    }

    public function delete(array $vars): void
    {
        $id   = (int)$vars['id'];
        $note = $this->pdo->prepare("SELECT * FROM health_notes WHERE id=:id");
        $note->execute([':id' => $id]);
        $note = $note->fetch();
        if (!$note) { $this->json(['ok' => false, 'message' => 'Not found'], 404); }

        if ($note['image_path']) {
            $path = '/var/www/app.cfarm.vn/public' . $note['image_path'];
            if (file_exists($path)) unlink($path);
        }
        $this->pdo->prepare("DELETE FROM health_notes WHERE id=:id")->execute([':id' => $id]);

        if ($this->isAjax()) {
            $this->json(['ok' => true]);
        }
        header('Location: /events/create?cycle_id=' . $note['cycle_id']);
        exit;
    }
}
