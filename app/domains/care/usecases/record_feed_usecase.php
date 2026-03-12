<?php
/**
 * app/domains/care/usecases/record_feed_usecase.php
 * Ghi chép cho ăn.
 * remaining_pct: % cám còn lại trong máng (0-100), null nếu không ghi.
 */
declare(strict_types=1);
namespace App\Domains\Care\Usecases;

use App\Domains\Care\Contracts\CareRepositoryInterface;
use App\Domains\Care\Entities\CareFeed;
use App\Domains\FeedBrand\Contracts\FeedTypeRepositoryInterface;
use App\Domains\Cycle\Contracts\CycleRepositoryInterface;
use InvalidArgumentException;

class RecordFeedUsecase
{
    public function __construct(
        private CareRepositoryInterface     $care_repository,
        private FeedTypeRepositoryInterface $feed_type_repository,
        private CycleRepositoryInterface    $cycle_repository,
    ) {}

    public function execute(int $cycle_id, array $input): int
    {
        $cycle = $this->cycle_repository->find_by_id($cycle_id);
        if (!$cycle || !$cycle->is_active()) {
            throw new InvalidArgumentException('Cycle không hợp lệ hoặc đã đóng');
        }

        if (empty($input['feed_type_id'])) {
            throw new InvalidArgumentException('Thiếu mã cám');
        }

        if (empty($input['bags']) || (float)$input['bags'] <= 0) {
            throw new InvalidArgumentException('Số bao phải lớn hơn 0');
        }

        $feed_type = $this->feed_type_repository->find_by_id((int)$input['feed_type_id']);
        if (!$feed_type) {
            throw new InvalidArgumentException('Không tìm thấy mã cám');
        }

        $bags      = (float) $input['bags'];
        $kg_actual = !empty($input['kg_actual'])
            ? (float) $input['kg_actual']
            : $bags * (float) $input['kg_per_bag'];

        // remaining_pct: null nếu không nhập hoặc rỗng
        $remaining_pct = null;
        if (isset($input['remaining_pct']) && $input['remaining_pct'] !== '') {
            $pct = (int) $input['remaining_pct'];
            if ($pct < 0 || $pct > 100) {
                throw new InvalidArgumentException('Phần trăm cám còn lại phải từ 0 đến 100');
            }
            $remaining_pct = $pct;
        }

        $feed = new CareFeed(
            cycle_id:      $cycle_id,
            feed_type_id:  (int) $input['feed_type_id'],
            bags:          $bags,
            kg_actual:     $kg_actual,
            session:       $input['session'] ?? 'morning',
            recorded_at:   $input['recorded_at'] ?? date('Y-m-d H:i:s'),
            remaining_pct: $remaining_pct,
            note:          !empty($input['note']) ? $input['note'] : null,
        );

        return $this->care_repository->create_feed($feed);
    }
}
