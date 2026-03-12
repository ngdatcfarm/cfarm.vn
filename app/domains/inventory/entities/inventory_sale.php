<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Entities;
class InventorySale {
    public function __construct(
        public readonly int     $item_id,
        public readonly string  $buyer_name,
        public readonly float   $quantity,
        public readonly int     $unit_price,
        public readonly int     $total_price,
        public readonly string  $sold_at,
        public readonly ?string $buyer_phone = null,
        public readonly ?string $note        = null,
        public readonly ?int    $id          = null,
    ) {}
}
