<?php
/**
 * app/domains/barn/entities/barn.php
 *
 * Entity đại diện cho một chuồng trại vật lý.
 * Chứa thông tin cơ bản của barn: số thứ tự, kích thước, trạng thái.
 */

declare(strict_types=1);

namespace App\Domains\Barn\Entities;

class Barn
{
    public function __construct(
        public readonly string  $name,
        public readonly int     $number,
        public readonly float   $length_m,
        public readonly float   $width_m,
        public readonly float   $height_m,
        public readonly string  $status = 'active',
        public readonly ?string $note   = null,
        public readonly ?int    $id     = null,
    ) {}

    public function area(): float
    {
        return $this->length_m * $this->width_m;
    }

    public function volume(): float
    {
        return $this->length_m * $this->width_m * $this->height_m;
    }
}
