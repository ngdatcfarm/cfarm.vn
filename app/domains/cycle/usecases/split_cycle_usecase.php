<?php
/**
 * app/domains/cycle/usecases/split_cycle_usecase.php
 *
 * Tách một phần đàn gà từ cycle này sang barn khác.
 * Trừ số con từ cycle gốc (current_quantity).
 * Tạo cycle mới với parent_cycle_id trỏ về cycle gốc.
 * Ghi log vào bảng cycle_splits.
 */

declare(strict_types=1);

namespace App\Domains\Cycle\Usecases;

use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use App\Domains\Cycle\Contracts\CycleSplitRepositoryInterface;
use App\Domains\Cycle\Entities\Cycle;
use App\Domains\Barn\Contracts\BarnRepositoryInterface;
use InvalidArgumentException;

class SplitCycleUsecase
{
    public function __construct(
        private CycleRepositoryInterface      $cycle_repository,
        private CycleSplitRepositoryInterface $split_repository,
        private BarnRepositoryInterface       $barn_repository,
    ) {}

    public function execute(array $input): int
    {
        $this->validate($input);

        $from_cycle = $this->cycle_repository->find_by_id((int) $input['from_cycle_id']);
        if (!$from_cycle) {
            throw new InvalidArgumentException("Không tìm thấy cycle gốc");
        }

        if (!$from_cycle->is_active()) {
            throw new InvalidArgumentException("Cycle gốc đã đóng — không thể tách");
        }

        $split_quantity = (int) $input['quantity'];

        if ($split_quantity >= $from_cycle->current_quantity) {
            throw new InvalidArgumentException(
                "Số con tách ({$split_quantity}) phải nhỏ hơn số con hiện tại ({$from_cycle->current_quantity})"
            );
        }

        $to_barn_id = (int) $input['to_barn_id'];
        $to_barn    = $this->barn_repository->find_by_id($to_barn_id);
        if (!$to_barn) {
            throw new InvalidArgumentException("Không tìm thấy chuồng đích");
        }

        if ($this->cycle_repository->has_active_cycle($to_barn_id)) {
            throw new InvalidArgumentException(
                "Chuồng {$to_barn->name} đang có cycle active — không thể tách vào"
            );
        }

        // tạo code cho cycle mới
        $split_date = $input['split_date'];
        $code       = 'b' . $to_barn->number . '-' . date('Ymd', strtotime($split_date)) . '-split';

        if ($this->cycle_repository->code_exists($code)) {
            $code = $code . '-' . time();
        }

        // tạo cycle mới
        $new_cycle = new Cycle(
            barn_id:          $to_barn_id,
            code:             $code,
            initial_quantity: $split_quantity,
            male_quantity:    0, // không xác định khi tách
            female_quantity:  0,
            purchase_price:   $from_cycle->purchase_price,
            current_quantity: $split_quantity,
            start_date:       $split_date,
            breed:            $from_cycle->breed,
            parent_cycle_id:  $from_cycle->id,
            split_date:       $split_date,
            stage:            $from_cycle->stage,
        );

        $new_cycle_id = $this->cycle_repository->create($new_cycle);

        // cập nhật current_quantity cycle gốc
        $new_quantity = $from_cycle->current_quantity - $split_quantity;
        $this->cycle_repository->update_current_quantity($from_cycle->id, $new_quantity);

        // ghi log split
        $this->split_repository->create([
            'from_cycle_id' => $from_cycle->id,
            'to_cycle_id'   => $new_cycle_id,
            'quantity'      => $split_quantity,
            'split_date'    => $split_date,
            'note'          => $input['note'] ?? null,
        ]);

        return $new_cycle_id;
    }

    private function validate(array $input): void
    {
        $required = ['from_cycle_id', 'to_barn_id', 'quantity', 'split_date'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new InvalidArgumentException("Thiếu trường: {$field}");
            }
        }

        if ((int) $input['quantity'] <= 0) {
            throw new InvalidArgumentException("Số con tách phải lớn hơn 0");
        }
    }
}
