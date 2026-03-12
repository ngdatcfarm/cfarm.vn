<?php
/**
 * app/domains/cycle/usecases/update_cycle_usecase.php
 *
 * Cập nhật các field của cycle đang active.
 * Các field nhạy cảm (số lượng, giá) cần confirmed = '1' từ form.
 * Field cố định duy nhất: code, barn_id, start_date.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Usecases;

use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use App\Domains\Cycle\Entities\Cycle;
use InvalidArgumentException;

class UpdateCycleUsecase
{
    public function __construct(
        private CycleRepositoryInterface $cycle_repository,
    ) {}

    public function execute(int $id, array $input): void
    {
        $existing = $this->cycle_repository->find_by_id($id);

        if (!$existing) {
            throw new InvalidArgumentException("Không tìm thấy cycle #{$id}");
        }

        if (!$existing->is_active()) {
            throw new InvalidArgumentException("Cycle đã đóng — không thể chỉnh sửa");
        }

        $allowed_stages = ['chick', 'grower', 'adult'];
        if (!empty($input['stage']) && !in_array($input['stage'], $allowed_stages)) {
            throw new InvalidArgumentException("Stage không hợp lệ");
        }

        // nếu có thay đổi số lượng hoặc giá — cần confirmed
        $is_sensitive = isset($input['initial_quantity']) || isset($input['purchase_price']);
        if ($is_sensitive && empty($input['confirmed'])) {
            throw new InvalidArgumentException("Cần xác nhận trước khi sửa số lượng hoặc giá nhập");
        }

        // validate số lượng nếu có thay đổi
        $initial  = !empty($input['initial_quantity'])  ? (int)   $input['initial_quantity']  : $existing->initial_quantity;
        $male     = !empty($input['male_quantity'])     ? (int)   $input['male_quantity']     : $existing->male_quantity;
        $female   = !empty($input['female_quantity'])   ? (int)   $input['female_quantity']   : $existing->female_quantity;
        $price    = !empty($input['purchase_price'])    ? (float) $input['purchase_price']    : $existing->purchase_price;

        if ($initial <= 0) {
            throw new InvalidArgumentException("Số lượng gà phải lớn hơn 0");
        }

        if ($male + $female !== $initial) {
            throw new InvalidArgumentException("Số trống + mái phải bằng tổng số gà");
        }

        if ($price <= 0) {
            throw new InvalidArgumentException("Giá nhập phải lớn hơn 0");
        }

        $updated = new Cycle(
            barn_id:              $existing->barn_id,
            code:                 $existing->code,
            initial_quantity:     $initial,
            male_quantity:        $male,
            female_quantity:      $female,
            purchase_price:       $price,
            current_quantity:     $existing->current_quantity,
            start_date:           $existing->start_date,
            breed:                !empty($input['breed'])             ? strtolower(trim($input['breed'])) : $existing->breed,
            expected_end_date:    !empty($input['expected_end_date']) ? $input['expected_end_date']       : $existing->expected_end_date,
            stage:                $input['stage']                     ?? $existing->stage,
        );

        $this->cycle_repository->update($id, $updated);
    }
}
