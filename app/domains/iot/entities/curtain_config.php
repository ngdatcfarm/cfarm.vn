<?php
declare(strict_types=1);
namespace App\Domains\IoT\Entities;

/**
 * CurtainConfig Entity
 */
class CurtainConfig
{
    public int $id;
    public string $name;
    public int $barn_id;
    public int $device_id;
    public int $up_channel_id;
    public int $down_channel_id;
    public float $full_up_seconds;
    public float $full_down_seconds;
    public int $current_position_pct;
    public string $moving_state; // idle, moving_up, moving_down
    public ?int $moving_target_pct;
    public ?string $moving_started_at;
    public ?float $moving_duration_seconds;
    public ?string $last_moved_at;
    public string $created_at;
    public string $updated_at;
    
    // Relations
    public ?string $barn_name;
    public ?int $up_channel;
    public ?int $down_channel;
    public ?string $up_mqtt_topic;
    public ?string $down_mqtt_topic;
    public ?bool $is_online;
}
