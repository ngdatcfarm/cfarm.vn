<?php
declare(strict_types=1);
namespace App\Domains\IoT\Entities;

/**
 * DeviceChannel Entity
 */
class DeviceChannel
{
    public int $id;
    public int $device_id;
    public int $channel_number;
    public string $name;
    public string $channel_type; // curtain_up, curtain_down, fan, light, heater, water, other
    public ?int $gpio_pin;
    public int $max_on_seconds;
    public bool $is_active;
    public int $sort_order;
    public string $created_at;
}
