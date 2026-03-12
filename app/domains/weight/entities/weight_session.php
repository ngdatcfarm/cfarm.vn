<?php
declare(strict_types=1);
namespace App\Domains\Weight\Entities;

class WeightSession
{
    public function __construct(
        public readonly int     $cycle_id,
        public readonly int     $day_age,
        public readonly string  $weighed_at,
        public readonly ?string $note         = null,
        public readonly int     $sample_count = 0,
        public readonly ?float  $avg_weight_g = null,
        public readonly ?int    $id           = null,
    ) {}
}
