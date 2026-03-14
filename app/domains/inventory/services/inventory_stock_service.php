<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Services;

use App\Infrastructure\Persistence\Mysql\Repositories\InventoryRepository;
use PDO;
use InvalidArgumentException;

class InventoryStockService
{
    private InventoryRepository $repo;
    public function __construct(private PDO $pdo)
    {
        $this->repo = new InventoryRepository($pdo);
    }

    /**
     * Trừ tồn kho cám khi ghi care_feeds
     * @throws InvalidArgumentException nếu không đủ tồn kho
     */
    public function deduct_feed(int $care_feed_id, int $cycle_id, int $feed_type_id, float $bags): void
    {
        if ($bags <= 0) return;

        try {
            // Lấy barn_id từ cycle
            $stmt = $this->pdo->prepare("SELECT barn_id FROM cycles WHERE id=:id");
            $stmt->execute([':id' => $cycle_id]);
            $cycle = $stmt->fetch();
            if (!$cycle) return;
            $barn_id = (int)$cycle['barn_id'];

            // Lấy feed_type và feed_brand_id
            $stmt = $this->pdo->prepare("SELECT feed_brand_id FROM feed_types WHERE id=:id");
            $stmt->execute([':id' => $feed_type_id]);
            $ft = $stmt->fetch();
            if (!$ft) return;

            // Tìm inventory_items qua ref_feed_type_id (chính xác hơn)
            $stmt = $this->pdo->prepare("
                SELECT id FROM inventory_items
                WHERE ref_feed_type_id = :type_id
                AND category='production'
                AND sub_category='feed'
                AND status='active'
                LIMIT 1
            ");
            $stmt->execute([':type_id' => $feed_type_id]);
            $item = $stmt->fetch();

            // Fallback: tìm qua ref_feed_brand_id nếu không tìm thấy qua type
            if (!$item) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM inventory_items
                    WHERE ref_feed_brand_id = :brand_id
                    AND category='production'
                    AND sub_category='feed'
                    AND status='active'
                    LIMIT 1
                ");
                $stmt->execute([':brand_id' => (int)$ft['feed_brand_id']]);
                $item = $stmt->fetch();
            }

            if (!$item) {
                throw new InvalidArgumentException('Không tìm thấy vật tư cám trong kho');
            }

            // Kiểm tra tồn kho trước khi trừ
            $currentStock = $this->repo->get_stock((int)$item['id'], $barn_id);
            if ($currentStock < $bags) {
                throw new InvalidArgumentException(
                    "Tồn kho không đủ! Hiện có: " . number_format($currentStock, 1) . " bao, cần: " . number_format($bags, 1) . " bao"
                );
            }

            // Trừ tồn kho
            $this->repo->upsert_stock((int)$item['id'], $barn_id, -$bags);
            $this->repo->create_transaction([
                'item_id'          => (int)$item['id'],
                'txn_type'         => 'use_feed',
                'from_barn_id'     => $barn_id,
                'quantity'         => $bags,
                'cycle_id'         => $cycle_id,
                'ref_care_feed_id' => $care_feed_id,
                'note'             => "Tự động từ care_feeds #{$care_feed_id}",
                'recorded_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (InvalidArgumentException $e) {
            throw $e; // Re-throw để controller xử lý
        } catch (\Throwable $e) {
            error_log("Inventory deduct_feed error: " . $e->getMessage());
            throw new InvalidArgumentException('Lỗi khi trừ tồn kho: ' . $e->getMessage());
        }
    }

    /**
     * Trừ tồn kho thuốc khi ghi care_medications
     * @throws InvalidArgumentException nếu không đủ tồn kho
     */
    public function deduct_medication(int $care_med_id, int $cycle_id, ?int $medication_id, float $dosage, string $unit): void
    {
        if (!$medication_id || $dosage <= 0) return;

        try {
            $stmt = $this->pdo->prepare("SELECT barn_id FROM cycles WHERE id=:id");
            $stmt->execute([':id' => $cycle_id]);
            $cycle = $stmt->fetch();
            if (!$cycle) return;
            $barn_id = (int)$cycle['barn_id'];

            $stmt = $this->pdo->prepare("
                SELECT id FROM inventory_items
                WHERE ref_medication_id=:med AND category='production'
                AND sub_category='medicine' AND status='active' LIMIT 1
            ");
            $stmt->execute([':med' => $medication_id]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new InvalidArgumentException('Không tìm thấy vật tư thuốc trong kho');
            }

            // Kiểm tra tồn kho trước khi trừ
            $currentStock = $this->repo->get_stock((int)$item['id'], $barn_id);
            if ($currentStock < $dosage) {
                throw new InvalidArgumentException(
                    "Tồn kho không đủ! Hiện có: " . number_format($currentStock, 1) . ", cần: " . number_format($dosage, 1)
                );
            }

            $this->repo->upsert_stock((int)$item['id'], $barn_id, -$dosage);
            $this->repo->create_transaction([
                'item_id'                => (int)$item['id'],
                'txn_type'               => 'use_medicine',
                'from_barn_id'           => $barn_id,
                'quantity'               => $dosage,
                'cycle_id'               => $cycle_id,
                'ref_care_medication_id' => $care_med_id,
                'note'                   => "Tự động từ care_medications #{$care_med_id}",
                'recorded_at'            => date('Y-m-d H:i:s'),
            ]);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log("Inventory deduct_medication error: " . $e->getMessage());
            throw new InvalidArgumentException('Lỗi khi trừ tồn kho: ' . $e->getMessage());
        }
    }
}
