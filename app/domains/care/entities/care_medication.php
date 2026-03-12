<?php
/**
 * app/domains/care/entities/care_medication.php
 * Entity ghi chép thuốc.
 */
declare(strict_types=1);
namespace App\Domains\Care\Entities;

class CareMedication
{
    public function __construct(
        public readonly int     $cycle_id,
        public readonly string  $medication_name,
        public readonly float   $dosage,
        public readonly string  $unit,
        public readonly string  $method,
        public readonly string  $recorded_at,
        public readonly ?int    $medication_id = null,
        public readonly ?string $note          = null,
        public readonly ?int    $id            = null,
    ) {}

    public function method_label(): string
    {
        return match($this->method) {
            'water'    => 'Uống nước',
            'inject'   => 'Tiêm',
            'feed_mix' => 'Trộn cám',
            default    => 'Khác',
        };
    }
}
