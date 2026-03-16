<?php
/**
 * app/router.php
 *
 * Định nghĩa tất cả routes của ứng dụng.
 * Tổ chức theo domain: barn, cycle, care, feed_program, env.
 * Convention: GET list/show, POST create, PUT/PATCH update, DELETE remove.
 */

declare(strict_types=1);

use App\Interfaces\Http\Controllers\Web\Barn\BarnController;
use App\Interfaces\Http\Controllers\Web\Home\HomeController;
use App\Interfaces\Http\Controllers\Web\Export\ExportController;
use App\Interfaces\Http\Controllers\Web\Env\EnvController;
use App\Interfaces\Http\Controllers\Web\Auth\LoginController;
use App\Interfaces\Http\Controllers\Web\Expense\ExpenseController;
use App\Interfaces\Http\Controllers\Web\Health\HealthController;
use App\Interfaces\Http\Controllers\Web\Vaccine\VaccineController;
use App\Interfaces\Http\Controllers\Web\Settings\SettingsController;
use App\Interfaces\Http\Controllers\Web\Push\PushController;
// các controller khác sẽ được thêm vào đây theo từng vertical slice

// ------------------------------------------------------------------
// PUSH
$r->addRoute('GET',  '/push/vapid-public-key', [PushController::class, 'vapid_key']);
$r->addRoute('POST', '/push/subscribe',        [PushController::class, 'subscribe']);
$r->addRoute('POST', '/push/unsubscribe',      [PushController::class, 'unsubscribe']);
$r->addRoute('POST', '/push/test',            [PushController::class, 'test_push']);

$r->addRoute('GET',  '/settings/notifications',        [SettingsController::class, 'notifications']);
$r->addRoute('POST', '/settings/notifications/update',  [SettingsController::class, 'notifications_update']);

// HEALTH
$r->addRoute('POST', '/health/store',          [HealthController::class,  'store']);
$r->addRoute('POST', '/health/{id}/resolve',   [HealthController::class,  'resolve']);
$r->addRoute('POST', '/health/{id}/delete',    [HealthController::class,  'delete']);
$r->addRoute('GET',  '/health/{id}',            [HealthController::class,  'show']);
$r->addRoute('POST', '/health/{id}/update',    [HealthController::class,  'update']);

// VACCINE
$r->addRoute('POST', '/vaccine/store',         [VaccineController::class, 'store']);
$r->addRoute('POST', '/vaccine/{id}/done',     [VaccineController::class, 'done']);
$r->addRoute('POST', '/vaccine/{id}/delete',   [VaccineController::class, 'delete']);
$r->addRoute('GET',  '/vaccine/{id}',            [VaccineController::class,  'show']);
$r->addRoute('POST', '/vaccine/{id}/update',    [VaccineController::class,  'update']);

// VACCINE PROGRAMS (settings)
$r->addRoute('GET',  '/settings/vaccine-programs',              [SettingsController::class, 'vaccine_programs']);
$r->addRoute('POST', '/settings/vaccine-programs/store',        [SettingsController::class, 'vaccine_program_store']);
$r->addRoute('GET',  '/settings/vaccine-programs/{id}',         [SettingsController::class, 'vaccine_program_show']);
$r->addRoute('POST', '/settings/vaccine-programs/{id}/update',  [SettingsController::class, 'vaccine_program_update']);
$r->addRoute('POST', '/settings/vaccine-programs/{id}/delete',  [SettingsController::class, 'vaccine_program_delete']);
$r->addRoute('POST', '/settings/vaccine-programs/{id}/item/store',         [SettingsController::class, 'vaccine_item_store']);
$r->addRoute('POST', '/settings/vaccine-programs/item/{id}/delete',        [SettingsController::class, 'vaccine_item_delete']);
$r->addRoute('GET',  '/settings/vaccine-brands',                [SettingsController::class, 'vaccine_brands']);
$r->addRoute('POST', '/settings/vaccine-brands/store',          [SettingsController::class, 'vaccine_brand_store']);
$r->addRoute('POST', '/settings/vaccine-brands/{id}/delete',    [SettingsController::class, 'vaccine_brand_delete']);


// EXPENSES
$r->addRoute('POST', '/expenses/store',        [ExpenseController::class, 'store']);
$r->addRoute('POST', '/expenses/{id}/delete',  [ExpenseController::class, 'delete']);

// HOME


$r->addRoute('GET',  '/account',                [LoginController::class, 'account']);
$r->addRoute('POST', '/account/change-password', [LoginController::class, 'change_password']);
$r->addRoute('GET',  '/login',  [LoginController::class, 'show']);
$r->addRoute('POST', '/login',  [LoginController::class, 'login']);
$r->addRoute('GET',  '/logout', [LoginController::class, 'logout']);

$r->addRoute('GET',  '/export',              [ExportController::class, 'index']);
$r->addRoute('GET',  '/export/download',     [ExportController::class, 'download']);
$r->addRoute('GET', '/', [HomeController::class, 'index']);
$r->addRoute('GET', '/notifications', [HomeController::class, 'notifications']);

// BARN
// ------------------------------------------------------------------
$r->addRoute('GET',  '/barns',              [BarnController::class, 'index']);
$r->addRoute('GET',  '/barns/create',       [BarnController::class, 'create']);
$r->addRoute('POST', '/barns',              [BarnController::class, 'store']);
$r->addRoute('GET',  '/barns/{id:\d+}',     [BarnController::class, 'show']);
$r->addRoute('GET',  '/barns/{id:\d+}/edit',[BarnController::class, 'edit']);
$r->addRoute('POST', '/barns/{id:\d+}',     [BarnController::class, 'update']);
$r->addRoute('POST', '/barns/{id:\d+}/delete', [BarnController::class, 'destroy']);

// ------------------------------------------------------------------
// CYCLE (sẽ mở khoá sau khi tạo cycle_controller)// ------------------------------------------------------------------
// $r->addRoute('GET',  '/barns/{barn_id:\d+}/cycles',        [cycle_controller::class, 'index']);
// $r->addRoute('GET',  '/barns/{barn_id:\d+}/cycles/create', [cycle_controller::class, 'create']);
// $r->addRoute('POST', '/barns/{barn_id:\d+}/cycles',        [cycle_controller::class, 'store']);
// $r->addRoute('POST', '/cycles/{id:\d+}/close',             [cycle_controller::class, 'close']);

// ------------------------------------------------------------------
// CARE — feed, death, sale, medication
// (sẽ mở khoá theo từng vertical slice)
// ------------------------------------------------------------------
// $r->addRoute('POST', '/cycles/{cycle_id:\d+}/feeds',       [care_feed_controller::class, 'store']);
// $r->addRoute('POST', '/cycles/{cycle_id:\d+}/deaths',      [care_death_controller::class, 'store']);
// $r->addRoute('POST', '/cycles/{cycle_id:\d+}/sales',       [care_sale_controller::class, 'store']);
// $r->addRoute('POST', '/cycles/{cycle_id:\d+}/medications', [care_medication_controller::class, 'store']);

// ------------------------------------------------------------------
// FEED PROGRAM
// (sẽ mở khoá theo từng vertical slice)
// ------------------------------------------------------------------
// $r->addRoute('GET',  '/feed-programs',             [feed_program_controller::class, 'index']);
// $r->addRoute('GET',  '/feed-programs/create',      [feed_program_controller::class, 'create']);
// $r->addRoute('POST', '/feed-programs',             [feed_program_controller::class, 'store']);

// ------------------------------------------------------------------
// ENV — chỉ có ingest API, không có web view
// (sẽ mở khoá theo từng vertical slice)
// ------------------------------------------------------------------
// $r->addRoute('POST', '/api/env/ingest', [env_ingest_controller::class, 'ingest']);
// ------------------------------------------------------------------
// CYCLE
// ------------------------------------------------------------------
use App\Interfaces\Http\Controllers\Web\Cycle\CycleController;

$r->addRoute('GET',  '/barns/{barn_id:\d+}/cycles/create', [CycleController::class, 'create']);
$r->addRoute('POST', '/barns/{barn_id:\d+}/cycles',        [CycleController::class, 'store']);
$r->addRoute('GET',  '/cycles/{id:\d+}',                   [CycleController::class, 'show']);
$r->addRoute('GET',  '/cycles/{id:\d+}/edit',              [CycleController::class, 'edit']);
$r->addRoute('POST', '/cycles/{id:\d+}',                   [CycleController::class, 'update']);
$r->addRoute('GET',  '/cycles/{id:\d+}/close',             [CycleController::class, 'close_form']);
$r->addRoute('POST', '/cycles/{id:\d+}/close',             [CycleController::class, 'close']);
$r->addRoute('GET',  '/cycles/{id:\d+}/split',             [CycleController::class, 'split_form']);
$r->addRoute('POST', '/cycles/{id:\d+}/split',             [CycleController::class, 'split']);
$r->addRoute('POST', '/cycles/{id:\d+}/apply-vaccine-program', [CycleController::class, 'apply_vaccine_program']);

// ------------------------------------------------------------------
// EVENTS (ghi chép sự kiện hằng ngày)
// ------------------------------------------------------------------
use App\Interfaces\Http\Controllers\Web\Event\EventController;

$r->addRoute('GET', '/events/create', [EventController::class, 'create']);

// ------------------------------------------------------------------
// SETTINGS
// ------------------------------------------------------------------

$r->addRoute('GET',  '/settings',                               [SettingsController::class, 'index']);
$r->addRoute('GET',  '/settings/feed-brands',                   [SettingsController::class, 'feed_brands']);
$r->addRoute('GET',  '/settings/feed-brands/create',            [SettingsController::class, 'feed_brand_create']);
$r->addRoute('POST', '/settings/feed-brands',                   [SettingsController::class, 'feed_brand_store']);
$r->addRoute('GET',  '/settings/feed-brands/{id:\d+}',          [SettingsController::class, 'feed_brand_show']);
$r->addRoute('GET',  '/settings/feed-brands/{id:\d+}/edit',     [SettingsController::class, 'feed_brand_edit']);
$r->addRoute('POST', '/settings/feed-brands/{id:\d+}',          [SettingsController::class, 'feed_brand_update']);
$r->addRoute('POST', '/settings/feed-brands/{id:\d+}/types',    [SettingsController::class, 'feed_type_store']);
$r->addRoute('POST', '/settings/feed-types/{id:\d+}/delete',    [SettingsController::class, 'feed_type_delete']);
$r->addRoute('POST', '/settings/feed-types/{id:\d+}/update',    [SettingsController::class, 'feed_type_update']);
$r->addRoute('POST', '/settings/feed-brands/sync-inventory',   [SettingsController::class, 'feed_brand_sync_inventory']);

// ------------------------------------------------------------------
// CARE (AJAX JSON endpoints)
// ------------------------------------------------------------------
use App\Interfaces\Http\Controllers\Web\Care\CareController;
use App\Interfaces\Http\Controllers\Web\Weight\WeightController;
use App\Interfaces\Http\Controllers\Web\Report\ReportController;

$r->addRoute('POST', '/care/feed',       [CareController::class, 'store_feed']);
$r->addRoute('POST', '/care/death',      [CareController::class, 'store_death']);
$r->addRoute('POST', '/care/medication', [CareController::class, 'store_medication']);
$r->addRoute('POST', '/care/sale',       [CareController::class, 'store_sale']);

$r->addRoute('GET',  '/cycles/{id:\d+}/feed-program', [CycleController::class, 'feed_program_form']);
$r->addRoute('POST', '/cycles/{id:\d+}/feed-program', [CycleController::class, 'feed_program_store']);

$r->addRoute('GET',  '/settings/medications',              [SettingsController::class, 'medications']);
$r->addRoute('POST', '/settings/medications',              [SettingsController::class, 'medication_store']);
$r->addRoute('POST', '/settings/medications/{id:\d+}/delete', [SettingsController::class, 'medication_delete']);
$r->addRoute('POST', '/settings/medications/{id:\d+}/toggle', [SettingsController::class, 'medication_toggle']);

$r->addRoute('GET',  '/cycles/{id:\d+}/feed-stages', [CycleController::class, 'feed_stages_form']);
$r->addRoute('POST', '/cycles/{id:\d+}/feed-stages', [CycleController::class, 'feed_stages_store']);

$r->addRoute('POST', '/care/trough-check', [CareController::class, 'store_trough_check']);

// Care — edit & delete
$r->addRoute('GET',    '/care/feed/{id}',        [CareController::class, 'get_feed']);
$r->addRoute('POST',   '/care/feed/{id}/update',  [CareController::class, 'update_feed']);
$r->addRoute('POST',   '/care/feed/{id}/delete',  [CareController::class, 'delete_feed']);

$r->addRoute('GET',    '/care/death/{id}',        [CareController::class, 'get_death']);
$r->addRoute('POST',   '/care/death/{id}/update', [CareController::class, 'update_death']);
$r->addRoute('POST',   '/care/death/{id}/delete', [CareController::class, 'delete_death']);

$r->addRoute('GET',    '/care/medication/{id}',        [CareController::class, 'get_medication']);
$r->addRoute('POST',   '/care/medication/{id}/update', [CareController::class, 'update_medication']);
$r->addRoute('POST',   '/care/medication/{id}/delete', [CareController::class, 'delete_medication']);

$r->addRoute('GET',    '/care/sale/{id}',        [CareController::class, 'get_sale']);
$r->addRoute('POST',   '/care/sale/{id}/update', [CareController::class, 'update_sale']);
$r->addRoute('POST',   '/care/sale/{id}/delete', [CareController::class, 'delete_sale']);

$r->addRoute('POST',   '/care/trough-check/{id}/delete', [CareController::class, 'delete_trough_check']);
$r->addRoute('GET', '/cycles/{id}/feed-chart-data', [CycleController::class, 'feed_chart_data']);

$r->addRoute('GET',  '/settings/medications/{id}/edit',   [SettingsController::class, 'medication_edit']);
$r->addRoute('POST', '/settings/medications/{id}/update', [SettingsController::class, 'medication_update']);

// Weight
$r->addRoute('POST', '/weight/session',                  [WeightController::class, 'store_session']);
$r->addRoute('POST', '/weight/session/{id}/sample',      [WeightController::class, 'add_sample']);
$r->addRoute('POST', '/weight/session/{id}/update',      [WeightController::class, 'update_session']);
$r->addRoute('POST', '/weight/sample/{id}/delete',       [WeightController::class, 'delete_sample']);
$r->addRoute('POST', '/weight/sample/{id}/update',       [WeightController::class, 'update_sample']);
$r->addRoute('POST', '/weight/session/{id}/delete',      [WeightController::class, 'delete_session']);
$r->addRoute('GET',  '/weight/cycle/{id}/chart-data',    [WeightController::class, 'chart_data']);
$r->addRoute('GET',  '/weight/session/{id}/samples',       [WeightController::class, 'get_samples']);
// Reports
$r->addRoute('GET', '/reports',      [ReportController::class, 'index']);
$r->addRoute('GET', '/reports/{id}',  [ReportController::class, 'show']);

// IoT - Device Control
use App\Interfaces\Http\Controllers\Web\IoT\DeviceController;
$r->addRoute('GET', '/iot/sensor/{id:\d+}', [DeviceController::class, 'sensor_show']);
$r->addRoute('GET',  '/iot/devices',                    [DeviceController::class, 'index']);
$r->addRoute('GET', '/iot/control', [DeviceController::class, 'control_all']);
$r->addRoute('GET',  '/iot/control/{barn_id:\d+}',     [DeviceController::class, 'control_page']);
$r->addRoute('GET',  '/iot/barn/{barn_id:\d+}/curtains',[DeviceController::class, 'barn_curtains']);
$r->addRoute('POST', '/iot/curtain/{id:\d+}/move',     [DeviceController::class, 'curtain_move']);
$r->addRoute('POST', '/iot/curtain/{id:\d+}/stop',     [DeviceController::class, 'curtain_stop']);

// IoT Settings
use App\Interfaces\Http\Controllers\Web\IoT\IoTSettingsController;
use App\Interfaces\Http\Controllers\Web\IoT\CurtainSetupController;
$r->addRoute('GET',  '/settings/iot',                        [IoTSettingsController::class, 'index']);
$r->addRoute('GET',  '/settings/iot/help',                   [IoTSettingsController::class, 'iot_help']);
$r->addRoute('POST', '/settings/iot/curtain/store',          [IoTSettingsController::class, 'curtain_store']);
$r->addRoute('POST', '/settings/iot/curtain/{id:\d+}/update',[IoTSettingsController::class, 'curtain_update']);
$r->addRoute('POST', '/settings/iot/curtain/{id:\d+}/delete',[IoTSettingsController::class, 'curtain_delete']);

// IoT Firmware
$r->addRoute('GET', '/settings/iot/firmware/{device_id:\d+}', [IoTSettingsController::class, 'firmware_code']);
$r->addRoute('GET', '/settings/iot/firmware/{device_id:\d+}/raw', [IoTSettingsController::class, 'firmware_raw']);
$r->addRoute('GET', '/settings/iot/firmwares', [IoTSettingsController::class, 'firmwares_index']);
$r->addRoute('POST', '/settings/iot/firmwares/upload', [IoTSettingsController::class, 'firmware_upload']);
$r->addRoute('POST', '/settings/iot/firmware/{id:\d+}/delete', [IoTSettingsController::class, 'firmware_delete']);

// OTA Endpoints (for ESP32)
$r->addRoute('GET', '/api/firmware/{device_type:\d+}/latest', [IoTSettingsController::class, 'ota_check']);
$r->addRoute('GET', '/api/firmware/{device_type:\d+}/bin', [IoTSettingsController::class, 'ota_redirect']); // Direct redirect to bin
$r->addRoute('GET', '/api/firmware/download/{id:\d+}', [IoTSettingsController::class, 'ota_download']);

// IoT Device Management
$r->addRoute('POST', '/settings/iot/device/store',               [IoTSettingsController::class, 'device_store']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/update',   [IoTSettingsController::class, 'device_update']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/delete',   [IoTSettingsController::class, 'device_delete']);


// IoT Device Type Editor
$r->addRoute('GET',  '/settings/iot/types',                    [IoTSettingsController::class, 'types_index']);
$r->addRoute('GET',  '/settings/iot/types/{id:\d+}',          [IoTSettingsController::class, 'type_show']);
$r->addRoute('POST', '/settings/iot/types/{id:\d+}/save',     [IoTSettingsController::class, 'type_save']);

$r->addRoute('POST', '/settings/iot/device/{id:\d+}/toggle-alert', [IoTSettingsController::class, 'device_toggle_alert']);
$r->addRoute('POST', '/settings/iot/device/{id:\d+}/allocate-firmware', [IoTSettingsController::class, 'allocate_firmware']);
$r->addRoute('GET', '/settings/iot/device/{id:\d+}/allocations', [IoTSettingsController::class, 'device_allocations']);

// IoT Device Types
$r->addRoute('POST', '/settings/iot/type/store',                 [IoTSettingsController::class, 'type_store']);
$r->addRoute('POST', '/settings/iot/type/{id:\d+}/update',     [IoTSettingsController::class, 'type_update']);
$r->addRoute('POST', '/settings/iot/type/{id:\d+}/delete',     [IoTSettingsController::class, 'type_delete']);

// ENV Dashboard
$r->addRoute('GET', '/env',               [EnvController::class, 'index']);
$r->addRoute('GET', '/env/barn/{id:\d+}', [EnvController::class, 'barn_show']);
$r->addRoute('GET', '/env/api/barn/{id:\d+}', [EnvController::class, 'api_latest']);

// ENV interval config
$r->addRoute('POST', '/env/barn/{id:\d+}/interval', [EnvController::class, 'update_interval']);

// IoT Node creation
$r->addRoute('GET',  '/iot/nodes/create', [IoTSettingsController::class, 'node_create']);
$r->addRoute('GET',  '/iot/nodes/{id:\d+}/edit',   [IoTSettingsController::class, 'node_edit']);
$r->addRoute('POST', '/iot/nodes/{id:\d+}/update', [IoTSettingsController::class, 'node_update']);
$r->addRoute('POST', '/iot/nodes/{id:\d+}/delete', [IoTSettingsController::class, 'node_delete']);
$r->addRoute('POST', '/iot/nodes/store',  [IoTSettingsController::class, 'node_store']);

// Curtain Setup Wizard
$r->addRoute('GET',  '/iot/curtains/setup',        [CurtainSetupController::class, 'setup']);
$r->addRoute('POST', '/iot/curtains/store',         [CurtainSetupController::class, 'store']);
$r->addRoute('POST', '/iot/curtains/visual-save',   [CurtainSetupController::class, 'visual_save']);
$r->addRoute('GET',  '/iot/curtains/{id:\d+}/edit', [CurtainSetupController::class, 'edit']);
$r->addRoute('POST', '/iot/curtains/{id:\d+}/update',[CurtainSetupController::class, 'update']);
$r->addRoute('POST', '/iot/curtains/{id:\d+}/delete',[CurtainSetupController::class, 'delete']);

// ------------------------------------------------------------------
// INVENTORY
// ------------------------------------------------------------------
use App\Interfaces\Http\Controllers\Web\Inventory\InventoryController;
use App\Interfaces\Http\Controllers\Web\Inventory\InventoryEditController;
use App\Interfaces\Http\Controllers\Web\Inventory\SupplierController;

$r->addRoute('GET',  '/inventory',                          [InventoryController::class, 'index']);
$r->addRoute('GET',  '/inventory/stock',                    [InventoryController::class, 'stock_by_barn']);
$r->addRoute('GET',  '/inventory/production',               [InventoryController::class, 'production']);
$r->addRoute('GET',  '/inventory/consumable',               [InventoryController::class, 'consumable']);
$r->addRoute('GET',  '/inventory/transactions',             [InventoryController::class, 'transactions']);
$r->addRoute('POST', '/inventory/purchase',                 [InventoryController::class, 'store_purchase']);
$r->addRoute('POST', '/inventory/transfer',                 [InventoryController::class, 'store_transfer']);
$r->addRoute('POST', '/inventory/sell',                     [InventoryController::class, 'store_sale']);
$r->addRoute('POST', '/inventory/adjust',                   [InventoryController::class, 'store_adjust']);
$r->addRoute('POST', '/inventory/assets/{id:\d+}/status',   [InventoryController::class, 'update_asset_status']);
$r->addRoute('POST', '/inventory/litter',                   [InventoryController::class, 'store_litter']);

// Litter routes - list & delete by cycle
$r->addRoute('GET',  '/cycles/{id:\d+}/litters',            [CycleController::class, 'list_litters']);
$r->addRoute('POST', '/cycles/{id:\d+}/litters/{litter_id:\d+}/delete', [CycleController::class, 'delete_litter']);

$r->addRoute('GET',  '/inventory/items/{id:\d+}/stock',     [InventoryController::class, 'get_item_stock']);
$r->addRoute('POST', '/inventory/items',                    [InventoryController::class, 'store_item']);
$r->addRoute('GET',  '/inventory/items/{id:\d+}',                  [InventoryEditController::class, 'getItem']);
$r->addRoute('POST', '/inventory/items/{id:\d+}/update',           [InventoryEditController::class, 'updateItem']);
$r->addRoute('POST', '/inventory/items/{id:\d+}/delete',           [InventoryEditController::class, 'deleteItem']);
$r->addRoute('GET',  '/inventory/purchases/{id:\d+}',              [InventoryEditController::class, 'getPurchase']);
$r->addRoute('POST', '/inventory/purchases/{id:\d+}/update',       [InventoryEditController::class, 'updatePurchase']);
$r->addRoute('POST', '/inventory/purchases/{id:\d+}/delete',       [InventoryEditController::class, 'deletePurchase']);
$r->addRoute('GET',  '/inventory/transactions/{id:\d+}',           [InventoryEditController::class, 'getTransaction']);
$r->addRoute('POST', '/inventory/transactions/{id:\d+}/update',    [InventoryEditController::class, 'updateTransaction']);
$r->addRoute('POST', '/inventory/transactions/{id:\d+}/delete',    [InventoryEditController::class, 'deleteTransaction']);
$r->addRoute('GET',  '/inventory/sales/{id:\d+}',                  [InventoryEditController::class, 'getSale']);
$r->addRoute('POST', '/inventory/sales/{id:\d+}/update',           [InventoryEditController::class, 'updateSale']);
$r->addRoute('POST', '/inventory/sales/{id:\d+}/delete',           [InventoryEditController::class, 'deleteSale']);
$r->addRoute('GET',  '/settings/suppliers',                 [SupplierController::class,  'index']);
$r->addRoute('POST', '/settings/suppliers',                 [SupplierController::class,  'store']);
$r->addRoute('POST', '/settings/suppliers/{id:\d+}/update', [SupplierController::class,  'update']);
