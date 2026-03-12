<?php
/**
 * app/domains/cycle/usecases/close_cycle_usecase.php
 *
 * Đóng một cycle đang active.
 * Tự tính final_quantity từ current_quantity.
 * Tự tính total_revenue từ care_sale records.
 * Người dùng nhập: total_sold_weight_kg, close_reason.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Usecases;

use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use InvalidArgumentException;

class CloseCycleUsecase
{
    public function __construct(
        private CycleRepositoryInterface $cycle_repository,
    ) {}

    public function execute(int $id, array $input): void
    {
        $cycle = $this->cycle_repository->find_by_id($id);

        if (!$cycle) {
            throw new InvalidArgumentException("Không tìm thấy cycle #{$id}");
        }

        if (!$cycle->is_active()) {
            throw new InvalidArgumentException("Cycle đã đóng rồi");
        }

        if (empty($input['end_date'])) {
            throw new InvalidArgumentException("Thiếu ngày kết thúc");
        }

        if (empty($input['close_reason'])) {
            throw new InvalidArgumentException("Thiếu lý do đóng cycle");
        }

        $allowed_reasons = ['sold', 'mortality', 'other'];
        if (!in_array($input['close_reason'], $allowed_reasons)) {
            throw new InvalidArgumentException("Lý do đóng không hợp lệ");
        }

        $this->cycle_repository->close($id, [
            'end_date'             => $input['end_date'],
            'final_quantity'       => $cycle->current_quantity,
            'total_sold_weight_kg' => !empty($input['total_sold_weight_kg']) ? (float) $input['total_sold_weight_kg'] : null,
            'total_revenue'        => !empty($input['total_revenue']) ? (float) $input['total_revenue'] : null,
            'close_reason'         => $input['close_reason'],
        ]);
    }
}
