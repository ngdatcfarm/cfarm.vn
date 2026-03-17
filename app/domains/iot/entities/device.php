<?php
declare(strict_types=1);
namespace App\Domains\IoT\Entities;

/**
 * Device Entity
 */
class Device
{
    public int $id;
    public string $device_code;
    public string $name;
    public ?int $barn_id;
    public int $device_type_id;
    public string $mqtt_topic;
    public bool $is_online;
    public ?string $last_heartbeat_at;
    public ?int $wifi_rssi;
    public ?string $ip_address;
    public ?int $uptime_seconds;
    public ?int $free_heap_bytes;
    public bool $alert_offline;
    public ?string $last_offline_alert_at;
    public ?string $notes;
    public string $created_at;
    public string $updated_at;
    
    // Relations
    public ?string $barn_name;
    public ?string $device_type_name;
    public array $channels = [];
}
