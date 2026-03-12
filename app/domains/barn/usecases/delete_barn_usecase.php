<?php
/**
 * app/domains/barn/usecases/delete_barn_usecase.php
 *
 * Xử lý logic xóa một barn.
 * Kiểm tra barn tồn tại trước khi xóa.
 */

declare(strict_types=1);

namespace App\Domains\Barn\Usecases;

use App\Domains\Barn\Contracts\BarnRepositoryInterface;
use InvalidArgumentException;

class DeleteBarnUsecase
{
    public function __construct(
        private BarnRepositoryInterface $barn_repository
    ) {}

    public function execute(int $id): void
    {
        $existing = $this->barn_repository->find_by_id($id);
        if (!$existing) {
            throw new InvalidArgumentException("Không tìm thấy chuồng #{$id}");
        }

        $this->barn_repository->delete($id);
    }
}
