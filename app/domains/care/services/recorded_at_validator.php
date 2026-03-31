<?php
declare(strict_types=1);
namespace App\Domains\Care\Services;

use App\Domains\Cycle\Entities\Cycle;
use InvalidArgumentException;

class RecordedAtValidator
{
    /**
     * Validate recorded_at: không trước start_date, không ở tương lai
     */
    public static function validate(?string $recorded_at, Cycle $cycle): void
    {
        if (!$recorded_at) return;

        $rec_date = substr($recorded_at, 0, 10);

        if ($rec_date < $cycle->start_date) {
            throw new InvalidArgumentException(
                'Ngày ghi (' . $rec_date . ') không thể trước ngày bắt đầu chu kỳ (' . $cycle->start_date . ')'
            );
        }

        if ($rec_date > date('Y-m-d')) {
            throw new InvalidArgumentException('Ngày ghi không thể ở tương lai');
        }
    }
}
