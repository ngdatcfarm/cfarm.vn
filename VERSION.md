# Version History - cfarm.vn

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
