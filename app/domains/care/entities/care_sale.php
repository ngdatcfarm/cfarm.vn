<?php
/**
 * app/domains/care/entities/care_sale.php
 * Entity ghi chép bán gà.
 */
declare(strict_types=1);
namespace App\Domains\Care\Entities;

class CareSale
{
    public function __construct(
        public readonly int     $cycle_id,
        public readonly float   $weight_kg,
        public readonly float   $price_per_kg,
        public readonly float   $total_amount,
        public readonly string  $recorded_at,
        public readonly ?int    $quantity = null,
        public readonly ?string $gender   = null,
        public readonly ?string $note     = null,
        public readonly ?int    $id       = null,
    ) {}
}
