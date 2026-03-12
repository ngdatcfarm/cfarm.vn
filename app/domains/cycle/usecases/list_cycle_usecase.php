<?php
/**
 * app/domains/cycle/usecases/list_cycle_usecase.php
 *
 * Lấy danh sách tất cả cycle của một barn.
 * Trả về mới nhất trước.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Usecases;

use App\Domains\Cycle\Contracts\CycleRepositoryInterface;

class ListCycleUsecase
{
    public function __construct(
        private CycleRepositoryInterface $cycle_repository,
    ) {}

    public function execute(int $barn_id): array
    {
        return $this->cycle_repository->find_by_barn($barn_id);
    }
}
