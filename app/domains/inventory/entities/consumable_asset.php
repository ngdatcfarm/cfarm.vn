<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Entities;
class ConsumableAsset {
    public function __construct(
        public readonly int     $item_id,
        public readonly string  $status           = 'stock',
        public readonly ?string $serial_no        = null,
        public readonly ?int    $barn_id          = null,
        public readonly ?string $install_location = null,
        public readonly ?int    $ref_device_id    = null,
        public readonly ?string $installed_at     = null,
        public readonly ?string $warranty_until   = null,
        public readonly ?int    $purchase_id      = null,
        public readonly ?string $note             = null,
        public readonly ?int    $id               = null,
    ) {}
    public function status_label(): string {
        return match($this->status) {
            'stock' => 'Trong kho', 'installed' => 'Đang lắp đặt',
            'broken' => 'Hỏng chờ xử lý', 'disposed' => 'Đã thanh lý', default => 'Không rõ',
        };
    }
    public function warranty_days_left(): ?int {
        if (!$this->warranty_until) return null;
        return (int)(( strtotime($this->warranty_until) - time() ) / 86400);
    }
}
