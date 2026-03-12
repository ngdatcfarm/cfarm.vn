<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Contracts;
interface InventoryRepositoryInterface {
    public function list_suppliers(): array;
    public function find_supplier(int $id): ?array;
    public function create_supplier(array $data): int;
    public function update_supplier(int $id, array $data): void;
    public function list_items(?string $category = null): array;
    public function find_item(int $id): ?array;
    public function create_item(array $data): int;
    public function update_item(int $id, array $data): void;
    public function get_stock(int $item_id, ?int $barn_id): float;
    public function upsert_stock(int $item_id, ?int $barn_id, float $delta): void;
    public function list_stock_for_item(int $item_id): array;
    public function list_low_stock_items(): array;
    public function create_purchase(array $data): int;
    public function list_purchases(?int $item_id = null, int $limit = 50): array;
    public function find_purchase(int $id): ?array;
    public function get_avg_cost(int $item_id): float;
    public function create_transaction(array $data): int;
    public function list_transactions(int $item_id, int $limit = 30): array;
    public function list_transactions_by_barn(int $barn_id, int $limit = 50): array;
    public function create_sale(array $data): int;
    public function list_sales(?int $item_id = null, int $limit = 50): array;
    public function create_asset(array $data): int;
    public function update_asset(int $id, array $data): void;
    public function find_asset(int $id): ?array;
    public function list_assets(?string $status = null, ?int $item_id = null): array;
    public function list_expiring_warranties(int $days = 30): array;
    public function create_litter(array $data): int;
    public function list_litters_by_cycle(int $cycle_id): array;
}
