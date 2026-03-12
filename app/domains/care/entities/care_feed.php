<?php
/**
 * app/domains/care/entities/care_feed.php
 * Entity ghi chép cho ăn.
 * remaining_pct: % cám còn lại trong máng (null = không ghi = 0%)
 */
declare(strict_types=1);
namespace App\Domains\Care\Entities;

class CareFeed
{
    public function __construct(
        public readonly int     $cycle_id,
        public readonly int     $feed_type_id,
        public readonly float   $bags,
        public readonly float   $kg_actual,
        public readonly string  $session,
        public readonly string  $recorded_at,
        public readonly ?int    $remaining_pct = null,
        public readonly ?string $note          = null,
        public readonly ?int    $id            = null,
    ) {}

    // kg thực tế gà ăn = kg đổ vào - phần còn lại
    public function consumed_kg(): float
    {
        $remaining = $this->remaining_pct ?? 0;
        return round($this->kg_actual * (1 - $remaining / 100), 2);
    }
}
