<?php
/**
 * app/domains/barn/contracts/barn_repository_interface.php
 *
 * Interface định nghĩa các thao tác dữ liệu cho barn.
 * Implementation cụ thể nằm ở infrastructure layer.
 */

declare(strict_types=1);

namespace App\Domains\Barn\Contracts;

use App\Domains\Barn\Entities\Barn;

interface BarnRepositoryInterface
{
    // Lấy tất cả barn
    public function find_all(): array;

    // Tìm barn theo id
    public function find_by_id(int $id): ?Barn;

    // Tìm barn theo số thứ tự
    public function find_by_number(int $number): ?Barn;

    // Tạo barn mới, trả về id vừa tạo
    public function create(Barn $barn): int;

    // Cập nhật barn
    public function update(int $id, Barn $barn): void;

    // Xóa barn
    public function delete(int $id): void;
}
