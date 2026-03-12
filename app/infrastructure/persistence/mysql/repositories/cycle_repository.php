<?php
/**
 * app/infrastructure/persistence/mysql/repositories/cycle_repository.php
 *
 * Implementation của cycle_repository_interface sử dụng MySQL/PDO.
 * Chịu trách nhiệm đọc/ghi dữ liệu cycle vào database.
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mysql\Repositories;

use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use App\Domains\Cycle\Entities\Cycle;
use PDO;

class CycleRepository implements CycleRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function find_by_barn(int $barn_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cycles
            WHERE barn_id = :barn_id
            ORDER BY start_date DESC
        ");
        $stmt->execute([':barn_id' => $barn_id]);
        $rows = $stmt->fetchAll();
        return array_map(fn($row) => $this->map($row), $rows);
    }

    public function find_by_id(int $id): ?Cycle
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cycles WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function find_active_by_barn(int $barn_id): ?Cycle
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cycles
            WHERE barn_id = :barn_id
              AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':barn_id' => $barn_id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function has_active_cycle(int $barn_id): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM cycles
            WHERE barn_id = :barn_id
              AND status = 'active'
        ");
        $stmt->execute([':barn_id' => $barn_id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function code_exists(string $code): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM cycles WHERE code = :code
        ");
        $stmt->execute([':code' => $code]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(Cycle $cycle): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cycles (
                barn_id, parent_cycle_id, split_date, code, breed,
                initial_quantity, male_quantity, female_quantity,
                purchase_price, current_quantity,
                start_date, expected_end_date,
                stage, feed_waste_pct, status,
                season, flock_source
            ) VALUES (
                :barn_id, :parent_cycle_id, :split_date, :code, :breed,
                :initial_quantity, :male_quantity, :female_quantity,
                :purchase_price, :current_quantity,
                :start_date, :expected_end_date,
                :stage, :feed_waste_pct, 'active',
                :season, :flock_source
            )
        ");

        $stmt->execute([
            ':barn_id'           => $cycle->barn_id,
            ':parent_cycle_id'   => $cycle->parent_cycle_id,
            ':split_date'        => $cycle->split_date,
            ':code'              => $cycle->code,
            ':breed'             => $cycle->breed,
            ':initial_quantity'  => $cycle->initial_quantity,
            ':male_quantity'     => $cycle->male_quantity,
            ':female_quantity'   => $cycle->female_quantity,
            ':purchase_price'    => $cycle->purchase_price,
            ':current_quantity'  => $cycle->current_quantity,
            ':start_date'        => $cycle->start_date,
            ':expected_end_date' => $cycle->expected_end_date,
            ':stage'             => $cycle->stage,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, Cycle $cycle): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cycles
            SET breed             = :breed,
                expected_end_date = :expected_end_date,
                stage             = :stage,
                initial_quantity  = :initial_quantity,
                male_quantity     = :male_quantity,
                female_quantity   = :female_quantity,
                purchase_price    = :purchase_price
            WHERE id = :id
        ");

        $stmt->execute([
            ':id'               => $id,
            ':breed'            => $cycle->breed,
            ':expected_end_date'=> $cycle->expected_end_date,
            ':stage'            => $cycle->stage,
            ':initial_quantity' => $cycle->initial_quantity,
            ':male_quantity'    => $cycle->male_quantity,
            ':female_quantity'  => $cycle->female_quantity,
            ':purchase_price'   => $cycle->purchase_price,
        ]);
    }

    public function update_current_quantity(int $id, int $quantity): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cycles SET current_quantity = :quantity WHERE id = :id
        ");
        $stmt->execute([':id' => $id, ':quantity' => $quantity]);
    }

    public function close(int $id, array $result): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cycles
            SET status               = 'closed',
                end_date             = :end_date,
                final_quantity       = :final_quantity,
                total_sold_weight_kg = :total_sold_weight_kg,
                total_revenue        = :total_revenue,
                close_reason         = :close_reason
            WHERE id = :id
        ");

        $stmt->execute([
            ':id'                  => $id,
            ':end_date'            => $result['end_date'],
            ':final_quantity'      => $result['final_quantity'],
            ':total_sold_weight_kg'=> $result['total_sold_weight_kg'],
            ':total_revenue'       => $result['total_revenue'],
            ':close_reason'        => $result['close_reason'],
        ]);
    }

    private function map(array $row): Cycle
    {
        return new Cycle(
            barn_id:              (int)   $row['barn_id'],
            code:                         $row['code'],
            initial_quantity:     (int)   $row['initial_quantity'],
            male_quantity:        (int)   $row['male_quantity'],
            female_quantity:      (int)   $row['female_quantity'],
            purchase_price:       (float) $row['purchase_price'],
            current_quantity:     (int)   $row['current_quantity'],
            start_date:                   $row['start_date'],
            breed:                        $row['breed'],
            parent_cycle_id:      $row['parent_cycle_id'] ? (int) $row['parent_cycle_id'] : null,
            split_date:                   $row['split_date'],
            expected_end_date:            $row['expected_end_date'],
            end_date:                     $row['end_date'],
            stage:                        $row['stage'],
            status:                       $row['status'],
            final_quantity:       $row['final_quantity']       ? (int)   $row['final_quantity']       : null,
            total_sold_weight_kg: $row['total_sold_weight_kg'] ? (float) $row['total_sold_weight_kg'] : null,
            total_revenue:        $row['total_revenue']        ? (float) $row['total_revenue']        : null,
            close_reason:                 $row['close_reason'],
            id:                   (int)   $row['id'],
            vaccine_program_id:   !empty($row['vaccine_program_id']) ? (int)$row['vaccine_program_id'] : null,
            season:                       $row['season'] ?? null,
            flock_source:                 $row['flock_source'] ?? null,
        );
    }
}
