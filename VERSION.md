# Version History - cfarm.vn

## [v0.1.11] - 2026-03-16
### Added
- IoT Help/Guide Page
  - New route: /settings/iot/help
  - Comprehensive documentation for IoT system
  - Covers: adding devices, device types, curtain setup, control, data flow, MQTT protocol, OTA
  - Troubleshooting section
  - Quick links to all IoT pages

### Changed
- settings_hub.php - add link to help page
- iot_settings_controller.php - add iot_help method
- router.php - add /settings/iot/help route

---

## [v0.1.10] - 2026-03-16
### Added
- Phase 3: Complete OTA (ESP32 Auto-Update)
  - OTA code snippet in firmware view with step-by-step instructions
  - ESPhttpUpdate integration for automatic firmware updates
  - Direct bin redirect endpoint: /api/firmware/{device_type}/bin
  - Full URL in ota_check response for easier parsing

### Changed
- firmware.php - add OTA instructions section

---

## [v0.1.9] - 2026-03-16
### Added
- Phase 2: OTA Foundation
  - Add device_firmwares table for storing firmware binaries
  - New Firmware Library page (/settings/iot/firmwares)
  - Upload firmware with version, checksum, notes
  - Download firmware files
  - OTA endpoints for ESP32:
    - GET /api/firmware/{device_type}/latest - check for updates
    - GET /api/firmware/download/{id} - download firmware
  - Show available firmwares in device firmware page

### Changed
- 4 files added, 3 files changed

---

## [v0.1.8] - 2026-03-16
### Added
- Phase 1: Firmware Version Control
  - Add firmware_version and base_firmware to device_types
  - Create device_firmware_allocations table
  - Add firmware allocation history
  - New UI for version and base firmware editing

### Changed
- 5 files changed

---

## [v0.1.7] - 2026-03-15
### Added
- Inventory Stock by Barn view
  - New route: /inventory/stock
  - Show feed stock by barn and central warehouse
  - Show litter stock by barn
  - Warning when stock < 10

### Fixed
- CycleRepository: removed feed_waste_pct column (doesn't exist in DB)
- EventController: added null checks for $cycle before accessing properties
- stock_by_barn.php: fixed layout pattern

### Changed
- 3 files changed

---

## [v0.1.6] - 2026-03-14
### Added
- CareEditPermission service
  - New file: app/domains/care/services/care_edit_permission.php
  - Check if care record can be edited (3 days) or deleted (2 days)
  - Override password feature for admin

### Fixed
- Delete event JSON error
  - CareEditPermission class autoload issue
  - Run `composer dump-autoload` after adding new PHP classes

### Changed
- 1 file added

---

## [v0.1.5] - 2026-03-14
### Added
- Cycle Feed Program Items feature
  - New table: cycle_feed_program_items
  - Cycle can select inventory_items for each growth stage (chick/grower/adult)
  - Form in cycle_feed_program shows inventory_items by stage
  - event_controller prioritizes cycle's selected inventory_items

### Fixed
- EventController: use inventory_stock table for quantity check (not inventory_items.quantity)

### Changed
- 4 files changed

---

## [v0.1.4] - 2026-03-14
### Added
- Feed Brand Auto-Generate feature
  - When creating feed_brand -> automatically create feed_types (3 stages: chick/grower/adult)
  - Automatically create inventory_items for each feed_type
  - Add column `ref_feed_type_id` to inventory_items table
  - Created FeedBrandService.php

### Fixed
- **InventoryStockService** - Stock validation before deducting
  - Check stock before deducting from inventory
  - Error message: "Tồn kho không đủ! Hiện có: X bao, cần: Y bao"
  - Priority lookup via ref_feed_type_id (more accurate than ref_feed_brand_id)

### Changed
- **CareController** - Added validation: cycle must have feed_program before recording care_feeds
- **EventController** - Added feed_inventory_items loading
- **event_create.php** - Added inventory dropdown showing current stock
- **SQLADD.md** - Fixed: inventory_items must link to feed_types (not feed_brands), include code in name
- **FeedBrandService** - Added syncInventoryFromFeedTypes() method
- **feed_brands.php** - Added "🔄 Sync Kho" button

### Database Changes
- See SQLADD.md for SQL commands
- **Important**: inventory_items name format: "CODE - Brand - Type Name"

### Changed
- 10 files changed

---

## [v0.1.3] - 2026-03-13
### Fixed
- **Root cause found!** Inventory delete not working
  - list_items was showing ALL items including inactive ones
  - When deleteItem sets status='inactive', item still displays in list
  - Added status='active' filter to list_items query

### Changed
- 1 file changed

---

## [v0.1.2] - 2026-03-13
### Fixed
- Inventory delete item - improved logic
  - Check if item exists before deleting
  - Return success if item already inactive
  - Fix UPDATE: only update if status='active'
  - Verify rowCount() to ensure update affected a row
  - Remove debug logging

### Changed
- 2 files changed

---

## [v0.1.1] - 2026-03-13
### Fixed
- Inventory delete item functionality
  - Add debug logging to deleteItem function for troubleshooting
  - Fix stock check query: only count positive quantities (quantity > 0)
  - Improve frontend to show alert message after delete attempt

### Changed
- 2 files changed

---

## [v0.1.0] - 2026-03-13
### Added
- Add care_litters feature to cycle detail page
- Integrate care_litters with inventory - add item selection dropdown and stock validation
  - Dropdown to select litter item from inventory in cycle detail form
  - Stock validation before deducting inventory
  - Error message if stock is insufficient: "Tồn kho không đủ! Hiện có: X bao, cần: Y bao"

### Changes
- 3 files changed in code + 1 docs file

---

## [v0.0.1] - 2026-03-13
### Added
- Initial project structure with Clean Architecture + DDD
- Vertical Slice #1: Barns (CRUD)
- Basic routing system with FastRoute
- MySQL database connection
- Layout with Dark/Light theme toggle
- 44 database tables defined
- ~100+ routes documented in Docs/ROUTERS.md

---

## [v0.0.0] - 2026-02-28
### Added
- Project initialization
- Database schema for cfarm_app_raw
