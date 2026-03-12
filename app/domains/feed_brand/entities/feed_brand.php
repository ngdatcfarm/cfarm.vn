<?php
/**
 * app/domains/feed_brand/entities/feed_brand.php
 *
 * Entity đại diện cho một hãng cám.
 * Mỗi hãng có trọng lượng/bao riêng và danh sách mã cám.
 */
declare(strict_types=1);
namespace App\Domains\FeedBrand\Entities;

class FeedBrand
{
    public function __construct(
        public readonly string  $name,
        public readonly float   $kg_per_bag,
        public readonly string  $status    = 'active',
        public readonly ?string $note      = null,
        public readonly ?int    $id        = null,
        public readonly ?array  $feed_types = null,
    ) {}

    public function is_active(): bool
    {
        return $this->status === 'active';
    }
}
