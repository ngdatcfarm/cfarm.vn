<?php
/**
 * app/domains/cycle/contracts/cycle_split_repository_interface.php
 *
 * Interface định nghĩa các thao tác dữ liệu cho cycle_splits.
 * Ghi log mỗi lần tách đàn giữa các cycle.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Contracts;

interface CycleSplitRepositoryInterface
{
    // Ghi log một lần tách đàn
    public function create(array $data): int;

    // Lấy lịch sử tách của một cycle
    public function find_by_cycle(int $cycle_id): array;
}
