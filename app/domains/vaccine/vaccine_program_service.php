<?php
declare(strict_types=1);
namespace App\Domains\Vaccine;

use PDO;

class VaccineProgramService
{
    public function __construct(private PDO $pdo) {}

    public function apply(int $cycle_id, int $program_id): int
    {
        $cycle = $this->pdo->prepare("SELECT * FROM cycles WHERE id=:id");
        $cycle->execute([':id' => $cycle_id]);
        $cycle = $cycle->fetch();
        if (!$cycle) return 0;

        $this->pdo->prepare("
            DELETE FROM vaccine_schedules
            WHERE cycle_id=:cid AND done=0 AND skipped=0 AND program_item_id IS NOT NULL
        ")->execute([':cid' => $cycle_id]);

        $stmt = $this->pdo->prepare("
            SELECT * FROM vaccine_program_items
            WHERE program_id=:pid ORDER BY day_age, sort_order
        ");
        $stmt->execute([':pid' => $program_id]);
        $items = $stmt->fetchAll();

        $count = 0;
        foreach ($items as $item) {
            $scheduled_date = date('Y-m-d', strtotime($cycle['start_date']) + ($item['day_age'] - 1) * 86400);
            $this->pdo->prepare("
                INSERT INTO vaccine_schedules
                    (cycle_id, vaccine_name, scheduled_date, day_age_target,
                     method, remind_days, vaccine_brand_id, program_item_id, done)
                VALUES
                    (:cycle_id, :name, :date, :day_age,
                     :method, :remind_days, :brand_id, :item_id, 0)
            ")->execute([
                ':cycle_id'    => $cycle_id,
                ':name'        => $item['vaccine_name'],
                ':date'        => $scheduled_date,
                ':day_age'     => $item['day_age'],
                ':method'      => $item['method'],
                ':remind_days' => $item['remind_days'],
                ':brand_id'    => $item['vaccine_brand_id'],
                ':item_id'     => $item['id'],
            ]);
            $count++;
        }

        $this->pdo->prepare("UPDATE cycles SET vaccine_program_id=:pid WHERE id=:id")
            ->execute([':pid' => $program_id, ':id' => $cycle_id]);

        return $count;
    }

    public function get_programs(): array
    {
        return $this->pdo->query("
            SELECT vp.*, COUNT(vpi.id) AS item_count
            FROM vaccine_programs vp
            LEFT JOIN vaccine_program_items vpi ON vpi.program_id = vp.id
            WHERE vp.active = 1
            GROUP BY vp.id ORDER BY vp.name
        ")->fetchAll();
    }
}
