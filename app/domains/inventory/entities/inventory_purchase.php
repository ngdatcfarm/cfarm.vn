<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Entities;
class InventoryPurchase {
    public function __construct(
        public readonly int     $item_id,
        public readonly float   $quantity,
        public readonly int     $unit_price,
        public readonly int     $total_price,
        public readonly string  $purchased_at,
        public readonly ?int    $supplier_id      = null,
        public readonly ?string $expiry_date      = null,
        public readonly ?string $batch_no         = null,
        public readonly ?string $storage_location = null,
        public readonly ?string $note             = null,
        public readonly ?int    $id               = null,
    ) {}
}
