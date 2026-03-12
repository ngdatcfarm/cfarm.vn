<?php
/**
 * app/infrastructure/persistence/mysql/repositories/cycle_split_repository.php
 *
 * Implementation của cycle_split_repository_interface sử dụng MySQL/PDO.
 * Chịu trách nhiệm đọc/ghi log tách đàn vào bảng cycle_splits.
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mysql\Repositories;

use App\Domains\Cycle\Contracts\CycleSplitRepositoryInterface;
use PDO;

class CycleSplitRepository implements CycleSplitRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cycle_splits (from_cycle_id, to_cycle_id, quantity, split_date, note)
            VALUES (:from_cycle_id, :to_cycle_id, :quantity, :split_date, :note)
        ");

        $stmt->execute([
            ':from_cycle_id' => $data['from_cycle_id'],
            ':to_cycle_id'   => $data['to_cycle_id'],
            ':quantity'      => $data['quantity'],
            ':split_date'    => $data['split_date'],
            ':note'          => $data['note'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function find_by_cycle(int $cycle_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cs.*,
                   c_from.code AS from_code,
                   c_to.code   AS to_code,
                   b.name      AS to_barn_name
            FROM cycle_splits cs
            JOIN cycles c_from ON cs.from_cycle_id = c_from.id
            JOIN cycles c_to   ON cs.to_cycle_id   = c_to.id
            JOIN barns  b      ON c_to.barn_id      = b.id
            WHERE cs.from_cycle_id = :from_cycle_id
               OR cs.to_cycle_id   = :to_cycle_id
            ORDER BY cs.split_date DESC
        ");

        $stmt->execute([
            ':from_cycle_id' => $cycle_id,
            ':to_cycle_id'   => $cycle_id,
        ]);

        return $stmt->fetchAll();
    }
}
