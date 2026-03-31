<?php
/**
 * app/domains/care/usecases/record_sale_usecase.php
 * Ghi chép bán gà. Tự tính total_amount = weight_kg × price_per_kg.
 */
declare(strict_types=1);
namespace App\Domains\Care\Usecases;

use App\Domains\Care\Contracts\CareRepositoryInterface;
use App\Domains\Care\Entities\CareSale;
use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use App\Domains\Care\Services\RecordedAtValidator;
use InvalidArgumentException;

class RecordSaleUsecase
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

        RecordedAtValidator::validate($input['recorded_at'] ?? null, $cycle);

        if (empty($input['weight_kg']) || (float)$input['weight_kg'] <= 0) {
            throw new InvalidArgumentException('Tổng cân nặng phải lớn hơn 0');
        }

        if (empty($input['price_per_kg']) || (float)$input['price_per_kg'] <= 0) {
            throw new InvalidArgumentException('Giá/kg phải lớn hơn 0');
        }

        $weight_kg    = (float) $input['weight_kg'];
        $price_per_kg = (float) $input['price_per_kg'];
        $total_amount = $weight_kg * $price_per_kg;

        // nếu có số con thì trừ current_quantity
        $quantity = !empty($input['quantity']) ? (int)$input['quantity'] : null;
        if ($quantity !== null && $quantity > $cycle->current_quantity) {
            throw new InvalidArgumentException(
                "Số con bán ({$quantity}) lớn hơn số con hiện tại ({$cycle->current_quantity})"
            );
        }

        $sale = new CareSale(
            cycle_id:     $cycle_id,
            weight_kg:    $weight_kg,
            price_per_kg: $price_per_kg,
            total_amount: $total_amount,
            recorded_at:  $input['recorded_at'] ?? date('Y-m-d H:i:s'),
            quantity:     $quantity,
            gender:       !empty($input['gender']) ? $input['gender'] : null,
            note:         !empty($input['note'])   ? $input['note']   : null,
        );

        $id = $this->care_repository->create_sale($sale);

        // trừ current_quantity nếu có số con
        if ($quantity !== null) {
            $new_quantity = $cycle->current_quantity - $quantity;
            $this->cycle_repository->update_current_quantity($cycle_id, $new_quantity);
        }

        return $id;
    }
}
