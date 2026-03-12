<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Expense;

use PDO;

class ExpenseController
{
    public function __construct(private PDO $pdo) {}

    // POST /expenses/store
    public function store(array $vars): void
    {
        $cycle_id = (int)$_POST['cycle_id'];
        $amount   = (int)str_replace([',', '.', ' '], '', $_POST['amount'] ?? '0');

        if (!$cycle_id || $amount <= 0) {
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        $this->pdo->prepare("
            INSERT INTO care_expenses (cycle_id, category, label, amount, recorded_at, note)
            VALUES (:cycle_id, :category, :label, :amount, :recorded_at, :note)
        ")->execute([
            ':cycle_id'    => $cycle_id,
            ':category'    => $_POST['category'] ?? 'other',
            ':label'       => trim($_POST['label'] ?? ''),
            ':amount'      => $amount,
            ':recorded_at' => $_POST['recorded_at'] ?? date('Y-m-d'),
            ':note'        => trim($_POST['note'] ?? '') ?: null,
        ]);

        $redirect = $_POST['redirect'] ?? '/cycles/' . $cycle_id . '?tab=expense#expense';
        header('Location: ' . $redirect);
        exit;
    }

    // POST /expenses/{id}/delete
    public function delete(array $vars): void
    {
        $id  = (int)$vars['id'];
        $exp = $this->pdo->prepare("SELECT * FROM care_expenses WHERE id=:id");
        $exp->execute([':id' => $id]);
        $exp = $exp->fetch();
        if (!$exp) { http_response_code(404); exit; }

        $this->pdo->prepare("DELETE FROM care_expenses WHERE id=:id")->execute([':id' => $id]);
        header('Location: ' . ($_POST['redirect'] ?? '/cycles/' . $exp['cycle_id'] . '?tab=expense#expense'));
        exit;
    }
}
