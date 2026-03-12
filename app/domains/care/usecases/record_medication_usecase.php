<?php
/**
 * app/domains/care/usecases/record_medication_usecase.php
 * Ghi chép thuốc.
 */
declare(strict_types=1);
namespace App\Domains\Care\Usecases;

use App\Domains\Care\Contracts\CareRepositoryInterface;
use App\Domains\Care\Entities\CareMedication;
use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use InvalidArgumentException;

class RecordMedicationUsecase
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

        if (empty($input['medication_name'])) {
            throw new InvalidArgumentException('Thiếu tên thuốc');
        }

        if (empty($input['dosage']) || (float)$input['dosage'] <= 0) {
            throw new InvalidArgumentException('Liều lượng phải lớn hơn 0');
        }

        if (empty($input['unit'])) {
            throw new InvalidArgumentException('Thiếu đơn vị');
        }

        $allowed_methods = ['water', 'inject', 'feed_mix', 'other'];
        if (!in_array($input['method'] ?? '', $allowed_methods)) {
            throw new InvalidArgumentException('Cách dùng không hợp lệ');
        }

        $med = new CareMedication(
            cycle_id:        $cycle_id,
            medication_name: trim($input['medication_name']),
            dosage:          (float) $input['dosage'],
            unit:            trim($input['unit']),
            method:          $input['method'],
            recorded_at:     $input['recorded_at'] ?? date('Y-m-d H:i:s'),
            medication_id:   !empty($input['medication_id']) ? (int)$input['medication_id'] : null,
            note:            !empty($input['note']) ? $input['note'] : null,
        );

        return $this->care_repository->create_medication($med);
    }
}
