<?php
/**
 * app/infrastructure/persistence/mysql/repositories/feed_type_repository.php
 */
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Mysql\Repositories;

use App\Domains\FeedBrand\Contracts\FeedTypeRepositoryInterface;
use App\Domains\FeedBrand\Entities\FeedType;
use PDO;

class FeedTypeRepository implements FeedTypeRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function find_by_brand(int $feed_brand_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM feed_types
            WHERE feed_brand_id=:id
            ORDER BY suggested_stage ASC, code ASC
        ");
        $stmt->execute([':id' => $feed_brand_id]);
        return array_map(fn($r) => $this->map($r), $stmt->fetchAll());
    }

    public function find_by_id(int $id): ?FeedType
    {
        $stmt = $this->pdo->prepare("SELECT * FROM feed_types WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function create(FeedType $type): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO feed_types (feed_brand_id, code, price_per_bag, name, suggested_stage, note, status)
            VALUES (:feed_brand_id, :code, :price_per_bag, :name, :suggested_stage, :note, :status)
        ");
        $stmt->execute([
            ':feed_brand_id'   => $type->feed_brand_id,
            ':code'            => strtoupper(trim($type->code)),
            ':name'            => $type->name,
            ':price_per_bag'   => $type->price_per_bag,
            ':suggested_stage' => $type->suggested_stage,
            ':note'            => $type->note,
            ':status'          => $type->status,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, FeedType $type): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE feed_types
            SET code=:code, name=:name, suggested_stage=:suggested_stage,
                note=:note, status=:status
            WHERE id=:id
        ");
        $stmt->execute([
            ':id'              => $id,
            ':code'            => strtoupper(trim($type->code)),
            ':name'            => $type->name,
            ':suggested_stage' => $type->suggested_stage,
            ':note'            => $type->note,
            ':status'          => $type->status,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM feed_types WHERE id=:id");
        $stmt->execute([':id' => $id]);
    }

    private function map(array $r): FeedType
    {
        return new FeedType(
            feed_brand_id:   (int) $r['feed_brand_id'],
            code:                  $r['code'],
            suggested_stage:       $r['suggested_stage'],
            name:                  $r['name'],
            note:                  $r['note'],
            status:                $r['status'],
            id:              (int) $r['id'],
            price_per_bag:   isset($r['price_per_bag']) ? (int)$r['price_per_bag'] : null,
        );
    }
}
