<?php
/**
 * app/domains/barn/usecases/update_barn_usecase.php
 *
 * Xử lý logic cập nhật thông tin một barn.
 * Kiểm tra barn tồn tại, validate input, kiểm tra trùng số thứ tự.
 */

declare(strict_types=1);

namespace App\Domains\Barn\Usecases;

use App\Domains\Barn\Contracts\BarnRepositoryInterface;
use App\Domains\Barn\Entities\Barn;
use InvalidArgumentException;

class UpdateBarnUsecase
{
    public function __construct(
        private BarnRepositoryInterface $barn_repository
    ) {}

    public function execute(int $id, array $input): void
    {
        $existing = $this->barn_repository->find_by_id($id);
        if (!$existing) {
            throw new InvalidArgumentException("Không tìm thấy chuồng #{$id}");
        }

        $this->validate($input);

        // kiểm tra trùng số thứ tự với barn khác
        $duplicate = $this->barn_repository->find_by_number((int)$input['number']);
        if ($duplicate && $duplicate->id !== $id) {
            throw new InvalidArgumentException(
                "Số thứ tự chuồng {$input['number']} đã tồn tại"
            );
        }

        $barn = new Barn(
            name:     trim($input['name']),
            number:   (int)   $input['number'],
            length_m: (float) $input['length_m'],
            width_m:  (float) $input['width_m'],
            height_m: (float) $input['height_m'],
            status:   $input['status'] ?? $existing->status,
            note:     !empty($input['note']) ? trim($input['note']) : null,
        );

        $this->barn_repository->update($id, $barn);
    }

    private function validate(array $input): void
    {
        $required = ['number', 'name', 'length_m', 'width_m', 'height_m'];

        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new InvalidArgumentException("Thiếu trường: {$field}");
            }
        }

        if ((int)$input['number'] < 1 || (int)$input['number'] > 9) {
            throw new InvalidArgumentException("Số thứ tự chuồng phải từ 1 đến 9");
        }

        foreach (['length_m', 'width_m', 'height_m'] as $field) {
            if ((float)$input[$field] <= 0) {
                throw new InvalidArgumentException("{$field} phải lớn hơn 0");
            }
        }
    }
}
