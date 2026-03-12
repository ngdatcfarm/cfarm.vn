<?php
/**
 * app/domains/feed_brand/contracts/feed_brand_repository_interface.php
 */
declare(strict_types=1);
namespace App\Domains\FeedBrand\Contracts;

use App\Domains\FeedBrand\Entities\FeedBrand;

interface FeedBrandRepositoryInterface
{
    public function find_all(): array;
    public function find_active(): array;
    public function find_by_id(int $id): ?FeedBrand;
    public function find_with_types(int $id): ?FeedBrand;
    public function create(FeedBrand $brand): int;
    public function update(int $id, FeedBrand $brand): void;
}
