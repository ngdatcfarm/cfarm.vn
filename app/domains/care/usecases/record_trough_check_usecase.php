<?php
/**
 * app/domains/care/usecases/record_trough_check_usecase.php
 *
 * Ghi nhận kiểm tra cám còn lại trong máng.
 * remaining_pct phản ánh sức ăn từ lần cho ăn ref_feed_id.
 */
declare(strict_types=1);
namespace App\Domains\Care\Usecases;

use App\Domains\Care\Contracts\CareRepositoryInterface;
use App\Domains\Care\Entities\FeedTroughCheck;
use InvalidArgumentException;

class RecordTroughCheckUsecase
{
    public function __construct(private CareRepositoryInterface $care_repository) {}

    public function execute(int $cycle_id, array $input): int
    {
        if (empty($input['ref_feed_id'])) {
            throw new InvalidArgumentException('Vui lòng chọn lần cho ăn đang kiểm tra');
        }

        $pct = isset($input['remaining_pct']) ? (int)$input['remaining_pct'] : null;
        if ($pct === null || $pct < 0 || $pct > 100) {
            throw new InvalidArgumentException('Phần trăm còn lại phải từ 0 đến 100');
        }

        // verify feed thuộc đúng cycle
        $feed = $this->care_repository->find_feed_by_id((int)$input['ref_feed_id']);
        if (!$feed || (int)$feed['cycle_id'] !== $cycle_id) {
            throw new InvalidArgumentException('Lần cho ăn không hợp lệ');
        }

        $check = new FeedTroughCheck(
            cycle_id:      $cycle_id,
            ref_feed_id:   (int) $input['ref_feed_id'],
            remaining_pct: $pct,
            checked_at:    $input['checked_at'] ?? date('Y-m-d H:i:s'),
            note:          !empty($input['note']) ? $input['note'] : null,
        );

        return $this->care_repository->create_trough_check($check);
    }
}
