<?php
/**
 * app/domains/cycle/entities/cycle.php
 *
 * Entity đại diện cho một chu kỳ nuôi gà trong một barn.
 * initial_quantity là IMMUTABLE — không thay đổi sau khi tạo.
 * current_quantity thay đổi theo tách đàn và ghi nhận chết.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Entities;

class Cycle
{
    public function __construct(
        // quan hệ
        public readonly int     $barn_id,
        public readonly string  $code,

        // khởi tạo (IMMUTABLE)
        public readonly int     $initial_quantity,
        public readonly int     $male_quantity,
        public readonly int     $female_quantity,
        public readonly float   $purchase_price,

        // số con thực tế
        public readonly int     $current_quantity,

        // thời gian
        public readonly string  $start_date,

        // tùy chọn
        public readonly ?string $breed             = null,
        public readonly ?int    $parent_cycle_id   = null,
        public readonly ?int    $vaccine_program_id = null,
        public readonly ?string $season             = null,
        public readonly ?string $flock_source       = null,
        public readonly ?string $split_date        = null,
        public readonly ?string $expected_end_date = null,
        public readonly ?string $end_date          = null,

        // trạng thái
        public readonly string  $stage             = 'chick',
        public readonly float   $feed_waste_pct    = 3.0,
        public readonly string  $status            = 'active',

        // kết quả (điền khi close)
        public readonly ?int    $final_quantity       = null,
        public readonly ?float  $total_sold_weight_kg = null,
        public readonly ?float  $total_revenue        = null,
        public readonly ?string $close_reason         = null,

        public readonly ?int    $id                = null,
    ) {}

    // số ngày tuổi tính từ start_date
    public function age_in_days(): int
    {
        $start = new \DateTime($this->start_date);
        $today = new \DateTime('today');
        return (int) $start->diff($today)->days;
    }

    // tỷ lệ tử vong
    public function mortality_rate(): float
    {
        if ($this->initial_quantity === 0) return 0.0;
        $dead = $this->initial_quantity - $this->current_quantity;
        return round($dead / $this->initial_quantity * 100, 2);
    }

    // còn đang active không
    public function is_active(): bool
    {
        return $this->status === 'active';
    }
}
