<?php
declare(strict_types=1);
namespace App\Domains\Care\Contracts;

use App\Domains\Care\Entities\CareFeed;
use App\Domains\Care\Entities\CareDeath;
use App\Domains\Care\Entities\CareMedication;
use App\Domains\Care\Entities\CareSale;
use App\Domains\Care\Entities\FeedTroughCheck;

interface CareRepositoryInterface
{
    // feeds
    public function create_feed(CareFeed $feed): int;
    public function find_feeds_by_cycle_and_date(int $cycle_id, string $date): array;
    public function find_feed_by_id(int $id): ?array;

    // trough checks
    public function create_trough_check(FeedTroughCheck $check): int;
    public function find_trough_checks_by_cycle_and_date(int $cycle_id, string $date): array;

    // deaths
    public function create_death(CareDeath $death): int;
    public function find_deaths_by_cycle_and_date(int $cycle_id, string $date): array;

    // medications
    public function create_medication(CareMedication $med): int;
    public function find_medications_by_cycle_and_date(int $cycle_id, string $date): array;

    // sales
    public function create_sale(CareSale $sale): int;
    public function find_sales_by_cycle_and_date(int $cycle_id, string $date): array;
}
