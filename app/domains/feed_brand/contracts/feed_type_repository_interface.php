<?php
/**
 * app/domains/feed_brand/contracts/feed_type_repository_interface.php
 */
declare(strict_types=1);
namespace App\Domains\FeedBrand\Contracts;

use App\Domains\FeedBrand\Entities\FeedType;

interface FeedTypeRepositoryInterface
{
    public function find_by_brand(int $feed_brand_id): array;
    public function find_by_id(int $id): ?FeedType;
    public function create(FeedType $type): int;
    public function update(int $id, FeedType $type): void;
    public function delete(int $id): void;
}
