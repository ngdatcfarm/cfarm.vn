<?php
/**
 * app/domains/cycle/usecases/create_cycle_usecase.php
 *
 * Xử lý logic tạo mới một cycle cho một barn.
 * Validate input, kiểm tra barn không có cycle active,
 * tự động generate code, tạo entity và lưu vào db.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Usecases;

use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use App\Domains\Cycle\Entities\Cycle;
use App\Domains\Barn\Contracts\BarnRepositoryInterface;
use InvalidArgumentException;

class CreateCycleUsecase
{
    public function __construct(
        private CycleRepositoryInterface $cycle_repository,
        private BarnRepositoryInterface  $barn_repository,
    ) {}

    public function execute(array $input): int
    {
        $this->validate($input);

        $barn_id = (int) $input['barn_id'];

        // kiểm tra barn tồn tại
        $barn = $this->barn_repository->find_by_id($barn_id);
        if (!$barn) {
            throw new InvalidArgumentException("Không tìm thấy chuồng #{$barn_id}");
        }

        // kiểm tra barn đã có cycle active chưa
        if ($this->cycle_repository->has_active_cycle($barn_id)) {
            throw new InvalidArgumentException(
                "Chuồng {$barn->name} đang có cycle hoạt động — cần close trước"
            );
        }

        // generate code: B{number}-{YYYYMMDD}
        $code = 'b' . $barn->number . '-' . date('Ymd', strtotime($input['start_date']));

        // nếu code đã tồn tại thì thêm suffix
        if ($this->cycle_repository->code_exists($code)) {
            $code = $code . '-' . time();
        }

        $initial_quantity = (int) $input['initial_quantity'];

        $cycle = new Cycle(
            barn_id:              $barn_id,
            code:                 $code,
            initial_quantity:     $initial_quantity,
            male_quantity:        (int)   $input['male_quantity'],
            female_quantity:      (int)   $input['female_quantity'],
            purchase_price:       (float) $input['purchase_price'],
            current_quantity:     $initial_quantity, // khởi tạo = initial
            start_date:           $input['start_date'],
            breed:                !empty($input['breed']) ? strtolower(trim($input['breed'])) : null,
            expected_end_date:    !empty($input['expected_end_date']) ? $input['expected_end_date'] : null,
            stage:                $input['stage'] ?? 'chick',
            season:               $input['season'] ?? null,
            flock_source:         !empty($input['flock_source']) ? $input['flock_source'] : null,
        );

        return $this->cycle_repository->create($cycle);
    }

    private function validate(array $input): void
    {
        $required = [
            'barn_id', 'start_date',
            'initial_quantity', 'male_quantity',
            'female_quantity', 'purchase_price'
        ];

        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new InvalidArgumentException("Thiếu trường: {$field}");
            }
        }

        if ((int) $input['initial_quantity'] <= 0) {
            throw new InvalidArgumentException('Số lượng gà phải lớn hơn 0');
        }

        if ((int) $input['male_quantity'] + (int) $input['female_quantity'] !== (int) $input['initial_quantity']) {
            throw new InvalidArgumentException('Số trống + mái phải bằng tổng số gà');
        }

        if ((float) $input['purchase_price'] <= 0) {
            throw new InvalidArgumentException('Giá nhập phải lớn hơn 0');
        }
    }
}
