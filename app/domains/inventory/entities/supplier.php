<?php
declare(strict_types=1);
namespace App\Domains\Inventory\Entities;
class Supplier {
    public function __construct(
        public readonly string  $name,
        public readonly ?string $phone   = null,
        public readonly ?string $address = null,
        public readonly ?string $note    = null,
        public readonly string  $status  = 'active',
        public readonly ?int    $id      = null,
    ) {}
}
