# Router Documentation - app.cfarm.vn

> Tổng hợp tất cả routes của ứng dụng. Cập nhật: 2026-03-13

---

## 📁 Auth - Xác thực

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/login` | LoginController | show |
| POST | `/login` | LoginController | login |
| GET | `/logout` | LoginController | logout |
| GET | `/account` | LoginController | account |
| POST | `/account/change-password` | LoginController | change_password |

---

## 🏠 Home - Trang chủ

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/` | HomeController | index |
| GET | `/notifications` | HomeController | notifications |

---

## 🏚️ Barns - Quản lý chuồng trại

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/barns` | BarnController | index |
| GET | `/barns/create` | BarnController | create |
| POST | `/barns` | BarnController | store |
| GET | `/barns/{id}` | BarnController | show |
| GET | `/barns/{id}/edit` | BarnController | edit |
| POST | `/barns/{id}` | BarnController | update |
| POST | `/barns/{id}/delete` | BarnController | destroy |

---

## 🔄 Cycle - Quản lý chu kỳ nuôi

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/barns/{barn_id}/cycles/create` | CycleController | create |
| POST | `/barns/{barn_id}/cycles` | CycleController | store |
| GET | `/cycles/{id}` | CycleController | show |
| GET | `/cycles/{id}/edit` | CycleController | edit |
| POST | `/cycles/{id}` | CycleController | update |
| GET | `/cycles/{id}/close` | CycleController | close_form |
| POST | `/cycles/{id}/close` | CycleController | close |
| GET | `/cycles/{id}/split` | CycleController | split_form |
| POST | `/cycles/{id}/split` | CycleController | split |
| POST | `/cycles/{id}/apply-vaccine-program` | CycleController | apply_vaccine_program |

### Feed Program

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/cycles/{id}/feed-program` | CycleController | feed_program_form |
| POST | `/cycles/{id}/feed-program` | CycleController | feed_program_store |
| GET | `/cycles/{id}/feed-stages` | CycleController | feed_stages_form |
| POST | `/cycles/{id}/feed-stages` | CycleController | feed_stages_store |
| GET | `/cycles/{id}/feed-chart-data` | CycleController | feed_chart_data |

---

## 🐔 Care - Quản lý chăm sóc

### Feed (Cho ăn)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/care/feed` | CareController | store_feed |
| GET | `/care/feed/{id}` | CareController | get_feed |
| POST | `/care/feed/{id}/update` | CareController | update_feed |
| POST | `/care/feed/{id}/delete` | CareController | delete_feed |

### Death (Chết)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/care/death` | CareController | store_death |
| GET | `/care/death/{id}` | CareController | get_death |
| POST | `/care/death/{id}/update` | CareController | update_death |
| POST | `/care/death/{id}/delete` | CareController | delete_death |

### Medication (Thuốc)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/care/medication` | CareController | store_medication |
| GET | `/care/medication/{id}` | CareController | get_medication |
| POST | `/care/medication/{id}/update` | CareController | update_medication |
| POST | `/care/medication/{id}/delete` | CareController | delete_medication |

### Sale (Bán)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/care/sale` | CareController | store_sale |
| GET | `/care/sale/{id}` | CareController | get_sale |
| POST | `/care/sale/{id}/update` | CareController | update_sale |
| POST | `/care/sale/{id}/delete` | CareController | delete_sale |

### Trough Check (Kiểm tra máng)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/care/trough-check` | CareController | store_trough_check |
| POST | `/care/trough-check/{id}/delete` | CareController | delete_trough_check |

---

## ⚖️ Weight - Cân trọng lượng

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/weight/session` | WeightController | store_session |
| POST | `/weight/session/{id}/sample` | WeightController | add_sample |
| POST | `/weight/session/{id}/update` | WeightController | update_session |
| POST | `/weight/session/{id}/delete` | WeightController | delete_session |
| POST | `/weight/sample/{id}/delete` | WeightController | delete_sample |
| POST | `/weight/sample/{id}/update` | WeightController | update_sample |
| GET | `/weight/cycle/{id}/chart-data` | WeightController | chart_data |
| GET | `/weight/session/{id}/samples` | WeightController | get_samples |

---

## 🌡️ ENV - Môi trường

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/env` | EnvController | index |
| GET | `/env/barn/{id}` | EnvController | barn_show |
| GET | `/env/api/barn/{id}` | EnvController | api_latest |
| POST | `/env/barn/{id}/interval` | EnvController | update_interval |

---

## 💊 Vaccine - Tiêm phòng

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/vaccine/store` | VaccineController | store |
| GET | `/vaccine/{id}` | VaccineController | show |
| POST | `/vaccine/{id}/done` | VaccineController | done |
| POST | `/vaccine/{id}/update` | VaccineController | update |
| POST | `/vaccine/{id}/delete` | VaccineController | delete |

---

## ❤️ Health - Sức khỏe

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/health/store` | HealthController | store |
| GET | `/health/{id}` | HealthController | show |
| POST | `/health/{id}/update` | HealthController | update |
| POST | `/health/{id}/resolve` | HealthController | resolve |
| POST | `/health/{id}/delete` | HealthController | delete |

---

## 📦 Inventory - Kho vật tư

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/inventory` | InventoryController | index |
| GET | `/inventory/production` | InventoryController | production |
| GET | `/inventory/consumable` | InventoryController | consumable |
| POST | `/inventory/purchase` | InventoryController | store_purchase |
| POST | `/inventory/transfer` | InventoryController | store_transfer |
| POST | `/inventory/sell` | InventoryController | store_sale |
| POST | `/inventory/adjust` | InventoryController | store_adjust |
| POST | `/inventory/litter` | InventoryController | store_litter |
| POST | `/inventory/assets/{id}/status` | InventoryController | update_asset_status |
| GET | `/inventory/items/{id}/stock` | InventoryController | get_item_stock |
| POST | `/inventory/items` | InventoryController | store_item |

### Inventory - Edit

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/inventory/items/{id}` | InventoryEditController | getItem |
| POST | `/inventory/items/{id}/update` | InventoryEditController | updateItem |
| POST | `/inventory/items/{id}/delete` | InventoryEditController | deleteItem |
| GET | `/inventory/purchases/{id}` | InventoryEditController | getPurchase |
| POST | `/inventory/purchases/{id}/update` | InventoryEditController | updatePurchase |
| POST | `/inventory/purchases/{id}/delete` | InventoryEditController | deletePurchase |
| GET | `/inventory/transactions/{id}` | InventoryEditController | getTransaction |
| POST | `/inventory/transactions/{id}/update` | InventoryEditController | updateTransaction |
| POST | `/inventory/transactions/{id}/delete` | InventoryEditController | deleteTransaction |
| GET | `/inventory/sales/{id}` | InventoryEditController | getSale |
| POST | `/inventory/sales/{id}/update` | InventoryEditController | updateSale |
| POST | `/inventory/sales/{id}/delete` | InventoryEditController | deleteSale |

---

## 📊 Report - Báo cáo

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/reports` | ReportController | index |
| GET | `/reports/{id}` | ReportController | show |

---

## 📤 Export - Xuất dữ liệu

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/export` | ExportController | index |
| GET | `/export/download` | ExportController | download |

---

## 📅 Event - Sự kiện

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/events/create` | EventController | create |

---

## 💰 Expense - Chi phí

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/expenses/store` | ExpenseController | store |
| POST | `/expenses/{id}/delete` | ExpenseController | delete |

---

## 📡 Push Notification

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/push/vapid-public-key` | PushController | vapid_key |
| POST | `/push/subscribe` | PushController | subscribe |
| POST | `/push/unsubscribe` | PushController | unsubscribe |
| POST | `/push/test` | PushController | test_push |

---

## 📟 IoT - Thiết bị thông minh

### Device Control

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/iot/devices` | DeviceController | index |
| GET | `/iot/sensor/{id}` | DeviceController | sensor_show |
| GET | `/iot/control` | DeviceController | control_all |
| GET | `/iot/control/{barn_id}` | DeviceController | control_page |
| GET | `/iot/barn/{barn_id}/curtains` | DeviceController | barn_curtains |
| POST | `/iot/curtain/{id}/move` | DeviceController | curtain_move |
| POST | `/iot/curtain/{id}/stop` | DeviceController | curtain_stop |

### IoT Settings

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings/iot` | IoTSettingsController | index |
| POST | `/settings/iot/curtain/store` | IoTSettingsController | curtain_store |
| POST | `/settings/iot/curtain/{id}/update` | IoTSettingsController | curtain_update |
| POST | `/settings/iot/curtain/{id}/delete` | IoTSettingsController | curtain_delete |
| GET | `/settings/iot/firmware/{device_id}` | IoTSettingsController | firmware_code |
| POST | `/settings/iot/device/store` | IoTSettingsController | device_store |
| POST | `/settings/iot/device/{id}/update` | IoTSettingsController | device_update |
| POST | `/settings/iot/device/{id}/delete` | IoTSettingsController | device_delete |
| POST | `/settings/iot/device/{id}/toggle-alert` | IoTSettingsController | device_toggle_alert |

### IoT Node

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/iot/nodes/create` | IoTSettingsController | node_create |
| GET | `/iot/nodes/{id}/edit` | IoTSettingsController | node_edit |
| POST | `/iot/nodes/store` | IoTSettingsController | node_store |
| POST | `/iot/nodes/{id}/update` | IoTSettingsController | node_update |
| POST | `/iot/nodes/{id}/delete` | IoTSettingsController | node_delete |

### IoT Type

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings/iot/types` | IoTSettingsController | types_index |
| GET | `/settings/iot/types/{id}` | IoTSettingsController | type_show |
| POST | `/settings/iot/types/{id}/save` | IoTSettingsController | type_save |
| POST | `/settings/iot/type/store` | IoTSettingsController | type_store |
| POST | `/settings/iot/type/{id}/update` | IoTSettingsController | type_update |
| POST | `/settings/iot/type/{id}/delete` | IoTSettingsController | type_delete |

### Curtain Setup

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/iot/curtains/setup` | CurtainSetupController | setup |
| POST | `/iot/curtains/store` | CurtainSetupController | store |
| GET | `/iot/curtains/{id}/edit` | CurtainSetupController | edit |
| POST | `/iot/curtains/{id}/update` | CurtainSetupController | update |
| POST | `/iot/curtains/{id}/delete` | CurtainSetupController | delete |

---

## ⚙️ Settings - Cài đặt

### General

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings` | SettingsController | index |
| GET | `/settings/notifications` | SettingsController | notifications |
| POST | `/settings/notifications/update` | SettingsController | notifications_update |

### Feed Brands

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings/feed-brands` | SettingsController | feed_brands |
| GET | `/settings/feed-brands/create` | SettingsController | feed_brand_create |
| POST | `/settings/feed-brands` | SettingsController | feed_brand_store |
| GET | `/settings/feed-brands/{id}` | SettingsController | feed_brand_show |
| GET | `/settings/feed-brands/{id}/edit` | SettingsController | feed_brand_edit |
| POST | `/settings/feed-brands/{id}` | SettingsController | feed_brand_update |
| POST | `/settings/feed-brands/{id}/types` | SettingsController | feed_type_store |
| POST | `/settings/feed-types/{id}/delete` | SettingsController | feed_type_delete |
| POST | `/settings/feed-types/{id}/update` | SettingsController | feed_type_update |

### Medications

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings/medications` | SettingsController | medications |
| POST | `/settings/medications` | SettingsController | medication_store |
| GET | `/settings/medications/{id}/edit` | SettingsController | medication_edit |
| POST | `/settings/medications/{id}/update` | SettingsController | medication_update |
| POST | `/settings/medications/{id}/delete` | SettingsController | medication_delete |
| POST | `/settings/medications/{id}/toggle` | SettingsController | medication_toggle |

### Vaccine Programs

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings/vaccine-programs` | SettingsController | vaccine_programs |
| POST | `/settings/vaccine-programs/store` | SettingsController | vaccine_program_store |
| GET | `/settings/vaccine-programs/{id}` | SettingsController | vaccine_program_show |
| POST | `/settings/vaccine-programs/{id}/update` | SettingsController | vaccine_program_update |
| POST | `/settings/vaccine-programs/{id}/delete` | SettingsController | vaccine_program_delete |
| POST | `/settings/vaccine-programs/{id}/item/store` | SettingsController | vaccine_item_store |
| POST | `/settings/vaccine-programs/item/{id}/delete` | SettingsController | vaccine_item_delete |

### Vaccine Brands

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings/vaccine-brands` | SettingsController | vaccine_brands |
| POST | `/settings/vaccine-brands/store` | SettingsController | vaccine_brand_store |
| POST | `/settings/vaccine-brands/{id}/delete` | SettingsController | vaccine_brand_delete |

### Suppliers

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/settings/suppliers` | SupplierController | index |
| POST | `/settings/suppliers` | SupplierController | store |
| POST | `/settings/suppliers/{id}/update` | SupplierController | update |

---

## 📝 Tổng kết

- **Tổng số routes**: ~100+
- **Domains chính**: Auth, Home, Barn, Cycle, Care, Weight, ENV, Vaccine, Health, Inventory, Report, Export, Event, Expense, Push, IoT, Settings
