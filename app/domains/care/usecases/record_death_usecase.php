<?php
/**
 * app/domains/care/usecases/record_death_usecase.php
 * Ghi chép gà chết. Tự động trừ current_quantity của cycle.
 */
declare(strict_types=1);
namespace App\Domains\Care\Usecases;

use App\Domains\Care\Contracts\CareRepositoryInterface;
use App\Domains\Care\Entities\CareDeath;
use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use InvalidArgumentException;

class RecordDeathUsecase
{
    public function __construct(
        private CareRepositoryInterface  $care_repository,
        private CycleRepositoryInterface $cycle_repository,
    ) {}

    public function execute(int $cycle_id, array $input): int
    {
        $cycle = $this->cycle_repository->find_by_id($cycle_id);
        if (!$cycle || !$cycle->is_active()) {
            throw new InvalidArgumentException('Cycle không hợp lệ hoặc đã đóng');
        }

        $quantity = (int)($input['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Số con chết phải lớn hơn 0');
        }

        if ($quantity >= $cycle->current_quantity) {
            throw new InvalidArgumentException(
                "Số con chết ({$quantity}) không thể lớn hơn hoặc bằng số con hiện tại ({$cycle->current_quantity})"
            );
        }

        $death = new CareDeath(
            cycle_id:    $cycle_id,
            quantity:    $quantity,
            recorded_at: $input['recorded_at'] ?? date('Y-m-d H:i:s'),
            reason:      !empty($input['reason'])   ? $input['reason']   : null,
            symptoms:    !empty($input['symptoms']) ? $input['symptoms'] : null,
            note:        !empty($input['note'])     ? $input['note']     : null,
        );

        $id = $this->care_repository->create_death($death);

        // trừ current_quantity
        $new_quantity = $cycle->current_quantity - $quantity;
        $this->cycle_repository->update_current_quantity($cycle_id, $new_quantity);

        return $id;
    }
}
