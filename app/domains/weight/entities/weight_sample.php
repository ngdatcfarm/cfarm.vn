<?php
declare(strict_types=1);
namespace App\Domains\Weight\Entities;

class WeightSample
{
    public function __construct(
        public readonly int    $session_id,
        public readonly float  $weight_g,
        public readonly string $gender = 'unknown',
        public readonly ?int   $id     = null,
    ) {}
}
