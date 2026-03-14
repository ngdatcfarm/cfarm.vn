<?php
/**
 * app/domains/feed_brand/services/feed_brand_service.php
 *
 * Service xử lý logic nghiệp vụ liên quan đến feed_brand.
 * Khi tạo feed_brand mới -> tự động sinh feed_types + inventory_items
 */
declare(strict_types=1);

namespace App\Domains\FeedBrand\Services;

use App\Domains\FeedBrand\Entities\FeedBrand;
use App\Domains\FeedBrand\Entities\FeedType;
use PDO;

class FeedBrandService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Đảm bảo cột ref_feed_type_id tồn tại trong bảng inventory_items
     */
    private function ensureColumnExists(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'inventory_items'
                AND COLUMN_NAME = 'ref_feed_type_id'
            ");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $this->pdo->exec("
                    ALTER TABLE inventory_items
                    ADD COLUMN ref_feed_type_id INT NULL AFTER ref_feed_brand_id,
                    ADD INDEX idx_ref_feed_type_id (ref_feed_type_id)
                ");
            }
        } catch (\Throwable $e) {
            // Ignore if table doesn't exist or other error - will handle at runtime
            error_log("ensureColumnExists: " . $e->getMessage());
        }
    }

    /**
     * Tạo feed_brand mới và tự động sinh:
     * 1. feed_types (3 giai đoạn: chick, grower, adult)
     * 2. inventory_items (cho mỗi feed_type)
     */
    public function createWithAutoGenerate(FeedBrand $brand, float $kgPerBag): int
    {
        // Đảm bảo cột ref_feed_type_id tồn tại
        $this->ensureColumnExists();

        $this->pdo->beginTransaction();

        try {
            // 1. Tạo feed_brand
            $stmt = $this->pdo->prepare("
                INSERT INTO feed_brands (name, kg_per_bag, note, status)
                VALUES (:name, :kg_per_bag, :note, :status)
            ");
            $stmt->execute([
                ':name'       => $brand->name,
                ':kg_per_bag' => $kgPerBag,
                ':note'       => $brand->note,
                ':status'     => $brand->status,
            ]);
            $feedBrandId = (int) $this->pdo->lastInsertId();

            // 2. Tạo feed_types (3 giai đoạn)
            $stages = ['chick', 'grower', 'adult'];
            $stageNames = [
                'chick'  => 'Gà con',
                'grower' => 'Gà choai',
                'adult'  => 'Gà trưởng thành',
            ];

            foreach ($stages as $stage) {
                // Tạo feed_type
                $stmt = $this->pdo->prepare("
                    INSERT INTO feed_types (feed_brand_id, code, name, suggested_stage, status)
                    VALUES (:brand_id, :code, :name, :stage, :status)
                ");
                $code = strtoupper(substr($brand->name, 0, 2)) . strtoupper(substr($stage, 0, 2)) . $feedBrandId;
                $stmt->execute([
                    ':brand_id' => $feedBrandId,
                    ':code'     => $code,
                    ':name'     => $brand->name . ' - ' . $stageNames[$stage],
                    ':stage'    => $stage,
                    ':status'   => 'active',
                ]);
                $feedTypeId = (int) $this->pdo->lastInsertId();

                // 3. Tạo inventory_items cho feed_type này
                $stmt = $this->pdo->prepare("
                    INSERT INTO inventory_items (name, category, sub_category, unit, ref_feed_brand_id, ref_feed_type_id, status)
                    VALUES (:name, 'production', 'feed', 'bao', :feed_brand_id, :feed_type_id, 'active')
                ");
                $stmt->execute([
                    ':name'          => $brand->name . ' - ' . $stageNames[$stage],
                    ':feed_brand_id' => $feedBrandId,
                    ':feed_type_id'  => $feedTypeId,
                ]);
            }

            $this->pdo->commit();
            return $feedBrandId;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
