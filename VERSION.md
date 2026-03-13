# Version History - cfarm.vn

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
