<?php
/**
 * app/infrastructure/persistence/mysql/repositories/barn_repository.php
 *
 * Implementation của barn_repository_interface sử dụng MySQL/PDO.
 * Chịu trách nhiệm đọc/ghi dữ liệu barn vào database.
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mysql\Repositories;

use App\Domains\Barn\Contracts\BarnRepositoryInterface;
use App\Domains\Barn\Entities\Barn;
use PDO;

class BarnRepository implements BarnRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function find_all(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM barns
            ORDER BY number ASC
        ");

        $rows = $stmt->fetchAll();
        return array_map(fn($row) => $this->map($row), $rows);
    }

    public function find_by_id(int $id): ?Barn
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM barns WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function find_by_number(int $number): ?Barn
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM barns WHERE number = :number LIMIT 1
        ");
        $stmt->execute([':number' => $number]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function create(Barn $barn): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO barns (number, name, length_m, width_m, height_m, status, note)
            VALUES (:number, :name, :length_m, :width_m, :height_m, :status, :note)
        ");

        $stmt->execute([
            ':number'   => $barn->number,
            ':name'     => $barn->name,
            ':length_m' => $barn->length_m,
            ':width_m'  => $barn->width_m,
            ':height_m' => $barn->height_m,
            ':status'   => $barn->status,
            ':note'     => $barn->note,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, Barn $barn): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE barns
            SET number   = :number,
                name     = :name,
                length_m = :length_m,
                width_m  = :width_m,
                height_m = :height_m,
                status   = :status,
                note     = :note
            WHERE id = :id
        ");

        $stmt->execute([
            ':id'       => $id,
            ':number'   => $barn->number,
            ':name'     => $barn->name,
            ':length_m' => $barn->length_m,
            ':width_m'  => $barn->width_m,
            ':height_m' => $barn->height_m,
            ':status'   => $barn->status,
            ':note'     => $barn->note,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM barns WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
    }

    // --- private helpers ---

    private function map(array $row): Barn
    {
        return new Barn(
            name:     $row['name'],
            number:   (int)   $row['number'],
            length_m: (float) $row['length_m'],
            width_m:  (float) $row['width_m'],
            height_m: (float) $row['height_m'],
            status:   $row['status'],
            note:     $row['note'],
            id:       (int)   $row['id'],
        );
    }
}
