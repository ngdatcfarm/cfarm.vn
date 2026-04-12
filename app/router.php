<?php
/**
 * app/router.php - Simplified Cloud Router
 *
 * Cloud's role: Remote control via MQTT + View synced data
 * NOT a full farm management system
 */

declare(strict_types=1);

// =============================================================================
// PUSH NOTIFICATIONS
// =============================================================================
use App\Interfaces\Http\Controllers\Web\Push\PushController;

$r->addRoute('GET',  '/push/vapid-public-key', [PushController::class, 'vapid_key']);
$r->addRoute('POST', '/push/subscribe',        [PushController::class, 'subscribe']);
$r->addRoute('POST', '/push/unsubscribe',      [PushController::class, 'unsubscribe']);
$r->addRoute('POST', '/push/test',            [PushController::class, 'test_push']);
$r->addRoute('POST', '/push/acknowledge',     [PushController::class, 'acknowledge']);

// =============================================================================
// SETTINGS (minimal - only notifications needed)
// =============================================================================
use App\Interfaces\Http\Controllers\Web\Settings\SettingsController;

$r->addRoute('GET',  '/settings/notifications',        [SettingsController::class, 'notifications']);
$r->addRoute('POST', '/settings/notifications/update',  [SettingsController::class, 'notifications_update']);

// Bat Settings
$r->addRoute('POST', '/settings/iot/bat/set-device',      [SettingsController::class, 'bat_set_device']);
$r->addRoute('POST', '/settings/iot/bat/update-channel',  [SettingsController::class, 'bat_update_channel']);

// =============================================================================
// AUTH
// =============================================================================
use App\Interfaces\Http\Controllers\Web\Auth\LoginController;

$r->addRoute('GET',  '/account',                [LoginController::class, 'account']);
$r->addRoute('POST', '/account/change-password', [LoginController::class, 'change_password']);
$r->addRoute('GET',  '/login',  [LoginController::class, 'show']);
$r->addRoute('POST', '/login',  [LoginController::class, 'login']);
$r->addRoute('GET',  '/logout', [LoginController::class, 'logout']);

// =============================================================================
// HOME / DASHBOARD
// =============================================================================
use App\Interfaces\Http\Controllers\Web\Home\HomeController;

$r->addRoute('GET', '/',                       [HomeController::class, 'index']);
$r->addRoute('GET', '/notifications',          [HomeController::class, 'notifications']);

// =============================================================================
// ENV - Xem sensor data từ local sync
// =============================================================================
use App\Interfaces\Http\Controllers\Web\Env\EnvController;

$r->addRoute('GET',  '/env',                      [EnvController::class, 'index']);
$r->addRoute('GET',  '/env/barn/{id:\d+}',        [EnvController::class, 'barn_show']);
$r->addRoute('POST', '/env/barn/{id:\d+}/interval',[EnvController::class, 'update_interval']);
$r->addRoute('GET',  '/env/api/barn/{id:\d+}',    [EnvController::class, 'api_latest']);

// =============================================================================
// IoT - REMOTE CONTROL
// =============================================================================
use App\Interfaces\Http\Controllers\Web\IoT\DeviceController;
use App\Interfaces\Http\Controllers\Web\IoT\CurtainController;
use App\Interfaces\Http\Controllers\Web\IoT\CurtainSetupController;
use App\Interfaces\Http\Controllers\Web\IoT\BatController;
use App\Interfaces\Http\Controllers\Web\IoT\FirmwareController;
use App\Interfaces\Http\Controllers\Web\IoT\Commands\DirectCommandController;

// Device Management
$r->addRoute('GET',  '/settings/iot',                        [DeviceController::class, 'settings']);
$r->addRoute('GET',  '/iot/devices',                         [DeviceController::class, 'index']);
$r->addRoute('POST', '/settings/iot/device/store',           [DeviceController::class, 'device_store']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/update',[DeviceController::class, 'device_update']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/delete',[DeviceController::class, 'device_delete']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/pins',  [DeviceController::class, 'device_pins_save']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/channels',[DeviceController::class, 'device_channels_save']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/test', [DeviceController::class, 'device_test']);
$r->addRoute('GET', '/settings/iot/device/{id:\d+}/json', [DeviceController::class, 'device_json']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/ota', [DeviceController::class, 'device_ota']);

// Device Type
$r->addRoute('POST', '/settings/iot/type/store',            [DeviceController::class, 'type_store']);
$r->addRoute('POST', '/settings/iot/type/{id:\d+}/toggle', [DeviceController::class, 'type_toggle']);
$r->addRoute('POST', '/settings/iot/type/{id:\d+}/update', [DeviceController::class, 'type_update']);
$r->addRoute('POST', '/settings/iot/type/{id:\d+}/delete', [DeviceController::class, 'type_delete']);

// Curtain Control
$r->addRoute('GET',  '/iot/control',                       [CurtainController::class, 'control_all']);
$r->addRoute('GET',  '/iot/control/{barn_id}',            [CurtainController::class, 'control_page']);
$r->addRoute('POST', '/iot/curtain/{id:\d+}/move',         [CurtainController::class, 'curtain_move']);
$r->addRoute('POST', '/iot/curtain/{id:\d+}/stop',         [CurtainController::class, 'curtain_stop']);
$r->addRoute('GET',  '/iot/curtain/{id:\d+}/status',       [CurtainController::class, 'curtain_status']);

// Bat Control (Cloud reads synced bats data, commands go to local)
$r->addRoute('GET',  '/iot/bat/{id:\d+}',             [BatController::class, 'status']);
$r->addRoute('POST', '/iot/bat/{id:\d+}/up',          [BatController::class, 'move_up']);
$r->addRoute('POST', '/iot/bat/{id:\d+}/down',        [BatController::class, 'move_down']);
$r->addRoute('POST', '/iot/bat/{id:\d+}/stop',        [BatController::class, 'stop']);

// Curtain Setup
$r->addRoute('GET',  '/settings/iot/curtain/setup',        [CurtainSetupController::class, 'setup']);
$r->addRoute('POST', '/settings/iot/curtain/store',         [CurtainSetupController::class, 'store']);
$r->addRoute('POST', '/settings/iot/curtain/visual-save',  [CurtainSetupController::class, 'visual_save']);
$r->addRoute('POST', '/settings/iot/curtain/{id:\d+}/delete', [CurtainSetupController::class, 'delete']);

// Firmware
$r->addRoute('GET',  '/settings/iot/firmwares',            [FirmwareController::class, 'index']);
$r->addRoute('POST', '/settings/iot/firmware/store',       [FirmwareController::class, 'store']);
$r->addRoute('GET',  '/settings/iot/firmware/{id:\d+}/edit', [FirmwareController::class, 'edit']);
$r->addRoute('POST', '/settings/iot/firmware/{id:\d+}/update', [FirmwareController::class, 'update']);
$r->addRoute('POST', '/settings/iot/firmware/{id:\d+}/delete', [FirmwareController::class, 'delete']);
$r->addRoute('POST', '/settings/iot/firmware/{id:\d+}/toggle', [FirmwareController::class, 'toggle']);

// Direct Command (Cloud MQTT - Dual-Subscribe ESP32)
$r->addRoute('GET',  '/api/iot/direct/devices',              [DirectCommandController::class, 'devices']);
$r->addRoute('POST', '/api/iot/direct/relay',               [DirectCommandController::class, 'relay']);
$r->addRoute('POST', '/api/iot/direct/relay-timed',         [DirectCommandController::class, 'relayTimed']);
$r->addRoute('POST', '/api/iot/direct/curtain',             [DirectCommandController::class, 'curtain']);
$r->addRoute('POST', '/api/iot/direct/ping',                [DirectCommandController::class, 'ping']);

// Device Status API
$r->addRoute('GET', '/api/iot/devices/status',           [DeviceController::class, 'devices_status']);
$r->addRoute('GET', '/api/iot/device/{id:\d+}/status',   [DeviceController::class, 'device_status']);

// Relay Control API (Cloud MQTT)
$r->addRoute('POST', '/api/iot/device/{id:\d+}/relay',       [DeviceController::class, 'device_relay']);
$r->addRoute('POST', '/api/iot/device/{id:\d+}/relay-all',   [DeviceController::class, 'device_relay_all']);

// OTA Endpoints
$r->addRoute('GET', '/api/firmware/{device_type:\d+}/latest', [FirmwareController::class, 'ota_check']);
$r->addRoute('GET', '/api/firmware/download/{id:\d+}', [FirmwareController::class, 'ota_download']);

// =============================================================================
// SYNC API - Local Server → Cloud (data sync)
// =============================================================================
use App\Interfaces\Http\Controllers\Web\Sync\SyncController;

$r->addRoute('POST', '/api/sync/receive',       [SyncController::class, 'receive']);
$r->addRoute('GET',  '/api/sync/changes',        [SyncController::class, 'changes']);
$r->addRoute('POST', '/api/sync/sensor-data',    [SyncController::class, 'sensor_data']);
$r->addRoute('POST', '/api/sync/device-states',  [SyncController::class, 'device_states']);
$r->addRoute('POST', '/api/sync/farm-data',     [SyncController::class, 'farm_data']);
$r->addRoute('POST', '/api/sync/command',        [SyncController::class, 'send_command']);
$r->addRoute('GET',  '/api/sync/status',          [SyncController::class, 'status']);
