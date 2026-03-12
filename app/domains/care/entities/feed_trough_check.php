<?php
/**
 * app/domains/care/entities/feed_trough_check.php
 *
 * Entity kiểm tra cám còn lại trong máng.
 * remaining_pct phản ánh sức ăn từ lần cho ăn ref_feed_id.
 */
declare(strict_types=1);
namespace App\Domains\Care\Entities;

class FeedTroughCheck
{
    public function __construct(
        public readonly int     $cycle_id,
        public readonly int     $ref_feed_id,
        public readonly int     $remaining_pct,
        public readonly string  $checked_at,
        public readonly ?string $note = null,
        public readonly ?int    $id   = null,
    ) {}

    // kg gà thực ăn từ lần cho ăn ref (cần truyền vào kg_actual của feed đó)
    public function consumed_kg(float $feed_kg_actual): float
    {
        return round($feed_kg_actual * (1 - $this->remaining_pct / 100), 2);
    }
}
