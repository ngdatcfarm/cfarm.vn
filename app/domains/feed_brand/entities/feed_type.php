<?php
/**
 * app/domains/feed_brand/entities/feed_type.php
 *
 * Entity đại diện cho một mã cám thuộc một hãng.
 * VD: hãng C-P có mã 311H (chick), 312 (grower), 313 (adult).
 */
declare(strict_types=1);
namespace App\Domains\FeedBrand\Entities;

class FeedType
{
    public function __construct(
        public readonly int     $feed_brand_id,
        public readonly string  $code,
        public readonly string  $suggested_stage = 'all',
        public readonly ?string $name            = null,
        public readonly ?string $note            = null,
        public readonly string  $status          = 'active',
        public readonly ?int    $id              = null,
        public readonly ?int    $price_per_bag   = null,
    ) {}

    public function stage_label(): string
    {
        return match($this->suggested_stage) {
            'chick'  => 'Gà con',
            'grower' => 'Gà choai',
            'adult'  => 'Gà trưởng thành',
            default  => 'Tất cả stage',
        };
    }
}
