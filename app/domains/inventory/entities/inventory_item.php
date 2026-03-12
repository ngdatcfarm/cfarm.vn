<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Entities;
class InventoryItem {
    public function __construct(
        public readonly string  $name,
        public readonly string  $category,
        public readonly string  $unit,
        public readonly string  $sub_category,
        public readonly float   $min_stock_alert   = 0,
        public readonly ?int    $ref_medication_id = null,
        public readonly ?int    $ref_feed_brand_id = null,
        public readonly ?int    $supplier_id       = null,
        public readonly ?string $note              = null,
        public readonly string  $status            = 'active',
        public readonly ?int    $id                = null,
    ) {}
    public function is_production(): bool { return $this->category === 'production'; }
    public function is_consumable(): bool { return $this->category === 'consumable'; }
    public function sub_category_label(): string {
        return match($this->sub_category) {
            'feed' => 'Cám', 'medicine' => 'Thuốc', 'litter' => 'Trấu/Chất độn',
            'vitamin' => 'Vitamin/Khoáng', 'bio' => 'Chế phẩm sinh học',
            'feeder' => 'Máng ăn/uống', 'iot_device' => 'Thiết bị IoT',
            'sensor' => 'Cảm biến', 'lighting' => 'Đèn chiếu sáng',
            'fan' => 'Quạt/Thông gió', 'camera' => 'Camera', default => 'Khác',
        };
    }
    public function category_icon(): string {
        return match($this->sub_category) {
            'feed' => '🌾', 'medicine' => '💊', 'litter' => '🪨',
            'vitamin' => '🧪', 'bio' => '🦠', 'feeder' => '🍽️',
            'iot_device' => '📡', 'sensor' => '🌡️', 'lighting' => '💡',
            'fan' => '🌀', 'camera' => '📷', default => '📦',
        };
    }
}
