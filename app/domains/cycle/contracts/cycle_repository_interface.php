<?php
/**
 * app/domains/cycle/contracts/cycle_repository_interface.php
 *
 * Interface định nghĩa các thao tác dữ liệu cho cycle.
 * Implementation cụ thể nằm ở infrastructure layer.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Contracts;

use App\Domains\Cycle\Entities\Cycle;

interface CycleRepositoryInterface
{
    // Lấy tất cả cycle của một barn, mới nhất trước
    public function find_by_barn(int $barn_id): array;

    // Tìm cycle theo id
    public function find_by_id(int $id): ?Cycle;

    // Tìm cycle đang active của một barn
    public function find_active_by_barn(int $barn_id): ?Cycle;

    // Kiểm tra barn có cycle active không
    public function has_active_cycle(int $barn_id): bool;

    // Kiểm tra code đã tồn tại chưa
    public function code_exists(string $code): bool;

    // Tạo cycle mới, trả về id vừa tạo
    public function create(Cycle $cycle): int;

    // Cập nhật các field được phép sửa
    public function update(int $id, Cycle $cycle): void;

    // Cập nhật current_quantity (tách đàn + chết)
    public function update_current_quantity(int $id, int $quantity): void;

    // Close cycle — ghi nhận kết quả
    public function close(int $id, array $result): void;
}
