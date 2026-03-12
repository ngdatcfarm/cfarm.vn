<?php
/**
 * app/domains/care/entities/care_death.php
 * Entity ghi chép gà chết.
 */
declare(strict_types=1);
namespace App\Domains\Care\Entities;

class CareDeath
{
    public function __construct(
        public readonly int     $cycle_id,
        public readonly int     $quantity,
        public readonly string  $recorded_at,
        public readonly ?string $reason     = null,
        public readonly ?string $symptoms   = null,
        public readonly ?string $image_path = null,
        public readonly ?string $note       = null,
        public readonly ?int    $id         = null,
    ) {}
}
