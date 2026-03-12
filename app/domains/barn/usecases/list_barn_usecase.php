<?php
/**
 * app/domains/barn/usecases/list_barn_usecase.php
 *
 * Lấy danh sách tất cả barn, sắp xếp theo số thứ tự.
 */

declare(strict_types=1);

namespace App\Domains\Barn\Usecases;

use App\Domains\Barn\Contracts\BarnRepositoryInterface;

class ListBarnUsecase
{
    public function __construct(
        private BarnRepositoryInterface $barn_repository
    ) {}

    public function execute(): array
    {
        return $this->barn_repository->find_all();
    }
}
