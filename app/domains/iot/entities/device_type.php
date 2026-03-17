<?php
declare(strict_types=1);
namespace App\Domains\IoT\Entities;

/**
 * DeviceType Entity
 */
class DeviceType
{
    public int $id;
    public string $name;
    public ?string $description;
    public string $device_class; // relay, sensor, mixed
    public int $total_channels;
    public ?array $mqtt_protocol;
    public string $created_at;
    public string $updated_at;
}
