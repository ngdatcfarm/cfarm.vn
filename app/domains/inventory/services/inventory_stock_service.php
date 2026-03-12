<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Services;

use App\Infrastructure\Persistence\Mysql\Repositories\InventoryRepository;
use PDO;

class InventoryStockService
{
    private InventoryRepository $repo;
    public function __construct(private PDO $pdo)
    {
        $this->repo = new InventoryRepository($pdo);
    }

    public function deduct_feed(int $care_feed_id, int $cycle_id, int $feed_type_id, float $bags): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT barn_id FROM cycles WHERE id=:id");
            $stmt->execute([':id' => $cycle_id]);
            $cycle = $stmt->fetch();
            if (!$cycle) return;
            $barn_id = (int)$cycle['barn_id'];

            $stmt = $this->pdo->prepare("SELECT feed_brand_id FROM feed_types WHERE id=:id");
            $stmt->execute([':id' => $feed_type_id]);
            $ft = $stmt->fetch();
            if (!$ft) return;

            $stmt = $this->pdo->prepare("
                SELECT id FROM inventory_items
                WHERE ref_feed_brand_id=:brand AND category='production'
                AND sub_category='feed' AND status='active' LIMIT 1
            ");
            $stmt->execute([':brand' => (int)$ft['feed_brand_id']]);
            $item = $stmt->fetch();
            if (!$item) return;

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
        } catch (\Throwable $e) {
            error_log("Inventory deduct_feed error: " . $e->getMessage());
        }
    }

    public function deduct_medication(int $care_med_id, int $cycle_id, ?int $medication_id, float $dosage, string $unit): void
    {
        try {
            if (!$medication_id) return;
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
            if (!$item) return;

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
        } catch (\Throwable $e) {
            error_log("Inventory deduct_medication error: " . $e->getMessage());
        }
    }
}
