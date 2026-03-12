<?php
/**
 * app/infrastructure/persistence/mysql/repositories/feed_brand_repository.php
 */
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Mysql\Repositories;

use App\Domains\FeedBrand\Contracts\FeedBrandRepositoryInterface;
use App\Domains\FeedBrand\Entities\FeedBrand;
use App\Domains\FeedBrand\Entities\FeedType;
use PDO;

class FeedBrandRepository implements FeedBrandRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function find_all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM feed_brands ORDER BY name ASC");
        return array_map(fn($r) => $this->map($r), $stmt->fetchAll());
    }

    public function find_active(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM feed_brands WHERE status='active' ORDER BY name ASC");
        return array_map(fn($r) => $this->map($r), $stmt->fetchAll());
    }

    public function find_by_id(int $id): ?FeedBrand
    {
        $stmt = $this->pdo->prepare("SELECT * FROM feed_brands WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function find_with_types(int $id): ?FeedBrand
    {
        $brand = $this->find_by_id($id);
        if (!$brand) return null;

        $stmt = $this->pdo->prepare("
            SELECT * FROM feed_types
            WHERE feed_brand_id = :id
            ORDER BY suggested_stage ASC, code ASC
        ");
        $stmt->execute([':id' => $id]);
        $types = array_map(fn($r) => new FeedType(
            feed_brand_id:   (int) $r['feed_brand_id'],
            code:                  $r['code'],
            suggested_stage:       $r['suggested_stage'],
            name:                  $r['name'],
            note:                  $r['note'],
            status:                $r['status'],
            id:              (int) $r['id'],
            price_per_bag:   isset($r['price_per_bag']) ? (int)$r['price_per_bag'] : null,
        ), $stmt->fetchAll());

        return new FeedBrand(
            name:          $brand->name,
            kg_per_bag:    $brand->kg_per_bag,
            status:        $brand->status,
            note:          $brand->note,
            id:            $brand->id,
            feed_types:    $types,
        );
    }

    public function create(FeedBrand $brand): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO feed_brands (name, kg_per_bag, note, status)
            VALUES (:name, :kg_per_bag, :note, :status)
        ");
        $stmt->execute([
            ':name'       => $brand->name,
            ':kg_per_bag' => $brand->kg_per_bag,
            ':note'       => $brand->note,
            ':status'     => $brand->status,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, FeedBrand $brand): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE feed_brands
            SET name=:name, kg_per_bag=:kg_per_bag, note=:note, status=:status
            WHERE id=:id
        ");
        $stmt->execute([
            ':id'         => $id,
            ':name'       => $brand->name,
            ':kg_per_bag' => $brand->kg_per_bag,
            ':note'       => $brand->note,
            ':status'     => $brand->status,
        ]);
    }

    private function map(array $r): FeedBrand
    {
        return new FeedBrand(
            name:          $r['name'],
            kg_per_bag:    (float) $r['kg_per_bag'],
            status:        $r['status'],
            note:          $r['note'],
            id:            (int) $r['id'],
        );
    }
}
