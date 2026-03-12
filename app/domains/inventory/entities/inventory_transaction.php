<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Entities;
class InventoryTransaction {
    public function __construct(
        public readonly int     $item_id,
        public readonly string  $txn_type,
        public readonly float   $quantity,
        public readonly string  $recorded_at,
        public readonly ?int    $from_barn_id           = null,
        public readonly ?int    $to_barn_id             = null,
        public readonly ?int    $unit_price             = null,
        public readonly ?int    $ref_purchase_id        = null,
        public readonly ?int    $ref_care_feed_id       = null,
        public readonly ?int    $ref_care_medication_id = null,
        public readonly ?int    $cycle_id               = null,
        public readonly ?string $install_location       = null,
        public readonly ?string $note                   = null,
        public readonly ?int    $id                     = null,
    ) {}
    public function txn_type_label(): string {
        return match($this->txn_type) {
            'purchase' => 'Nhập kho', 'transfer_out' => 'Xuất về barn',
            'transfer_in' => 'Nhận từ kho', 'use_feed' => 'Dùng cám',
            'use_medicine' => 'Dùng thuốc', 'use_litter' => 'Dùng trấu',
            'use_consumable' => 'Sử dụng', 'sell' => 'Bán ra',
            'adjust' => 'Điều chỉnh', 'dispose' => 'Thanh lý', default => 'Khác',
        };
    }
}
