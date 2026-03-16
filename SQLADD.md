# SQL Changes for v0.1.x

## Add gpio_pin to device_channels (2026-03-16)

```sql
-- Thêm cột gpio_pin để lưu GPIO pin cho từng kênh relay
ALTER TABLE device_channels
ADD COLUMN gpio_pin INT NULL AFTER channel_number;

-- Cập nhật pins mặc định cho các kênh relay (CH1-8 = GPIO 32,33,25,26,27,14,12,13)
UPDATE device_channels SET gpio_pin = 32 WHERE channel_number = 1;
UPDATE device_channels SET gpio_pin = 33 WHERE channel_number = 2;
UPDATE device_channels SET gpio_pin = 25 WHERE channel_number = 3;
UPDATE device_channels SET gpio_pin = 26 WHERE channel_number = 4;
UPDATE device_channels SET gpio_pin = 27 WHERE channel_number = 5;
UPDATE device_channels SET gpio_pin = 14 WHERE channel_number = 6;
UPDATE device_channels SET gpio_pin = 12 WHERE channel_number = 7;
UPDATE device_channels SET gpio_pin = 13 WHERE channel_number = 8;
```

---

## Phase 2: OTA Foundation (2026-03-16)

### Create device_firmwares table - lưu trữ firmware binaries

```sql
CREATE TABLE IF NOT EXISTS device_firmwares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_type_id INT NOT NULL,
    version VARCHAR(20) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT DEFAULT 0,
    checksum VARCHAR(64) DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(100) DEFAULT 'system',
    notes TEXT,
    INDEX idx_type_version (device_type_id, version),
    INDEX idx_uploaded (uploaded_at)
);
```

---

## Phase 1: Firmware Version Control (2026-03-16)

### Add firmware_version and base_firmware to device_types

```sql
ALTER TABLE device_types
ADD COLUMN firmware_version VARCHAR(20) DEFAULT '1.0.0' AFTER firmware_template,
ADD COLUMN base_firmware LONGTEXT AFTER firmware_version;
```

### Create device_firmware_allocations table

```sql
CREATE TABLE IF NOT EXISTS device_firmware_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    device_type_id INT NOT NULL,
    firmware_version VARCHAR(20) NOT NULL,
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    allocated_by VARCHAR(100) DEFAULT 'system',
    config JSON,
    notes TEXT,
    INDEX idx_device (device_id),
    INDEX idx_allocated_at (allocated_at)
);
```

---

## CLEAN ALL DATA (Keep Users)

Xóa tất cả dữ liệu trong database, giữ lại bảng users:

```sql
-- Bắt buộc chạy từng bước một!

-- Bước 1: Tắt kiểm tra FK
SET FOREIGN_KEY_CHECKS = 0;

-- Bước 2: Xóa TẤT CẢ các bảng có FK đến cycles TRƯỚC
DELETE FROM weight_samples;
DELETE FROM weight_sessions;
DELETE FROM feed_trough_checks;
DELETE FROM env_readings;
DELETE FROM device_commands;
DELETE FROM care_sales;
DELETE FROM care_medications;
DELETE FROM care_litters;
DELETE FROM care_feeds;
DELETE FROM care_deaths;
DELETE FROM care_expenses;
DELETE FROM cycle_splits;
DELETE FROM cycle_feed_stages;
DELETE FROM cycle_feed_program_items;
DELETE FROM cycle_daily_snapshots;
DELETE FROM cycle_feed_programs;

-- Bước 3: Bây giờ mới xóa cycles
DELETE FROM cycles;

-- Bước 4: Xóa các bảng inventory
DELETE FROM inventory_consumable_assets;
DELETE FROM inventory_transactions;
DELETE FROM inventory_sales;
DELETE FROM inventory_stock;
DELETE FROM inventory_purchases;
DELETE FROM inventory_items;

-- Bước 5: Xóa các bảng master còn lại
DELETE FROM feed_types;
DELETE FROM feed_brands;
DELETE FROM medications;
DELETE FROM vaccine_schedules;
DELETE FROM vaccine_program_items;
DELETE FROM vaccine_brands;
DELETE FROM vaccine_programs;
DELETE FROM health_notes;
DELETE FROM devices;
DELETE FROM device_types;
DELETE FROM device_channels;
DELETE FROM device_states;
DELETE FROM device_state_log;
DELETE FROM curtain_configs;
DELETE FROM sensor_readings;
DELETE FROM barns;
DELETE FROM suppliers;
DELETE FROM push_notifications_log;
DELETE FROM notification_settings;

-- Bước 6: Bật lại kiểm tra FK
SET FOREIGN_KEY_CHECKS = 1;
```

**Thứ tự quan trọng:** Phải xóa tất cả bảng có FK đến `cycles` TRƯỚC khi xóa `cycles`:

- weight_sessions
- feed_trough_checks
- env_readings
- device_commands
- care_sales, care_medications, care_litters, care_feeds, care_deaths, care_expenses
- cycle_splits, cycle_feed_stages, cycle_feed_program_items, cycle_feed_programs

**Nếu vẫn lỗi**, chạy từng dòng một để xem dòng nào lỗi:

Sau khi clean, cần thêm lại dữ liệu mẫu:

```sql
-- 1. Thêm barn mẫu
INSERT INTO barns (id, number, name, length_m, width_m, height_m, status, note, created_at) VALUES
(1, 1, 'Chuồng 1', 60, 12, 3.5, 'active', 'Chuồng nuôi gà thịt', NOW()),
(2, 2, 'Chuồng 2', 60, 12, 3.5, 'active', 'Chuồng nuôi gà thịt', NOW());

-- 2. Thêm feed_brands
INSERT INTO feed_brands (id, name, kg_per_bag, status, note, created_at) VALUES
(1, 'Cám Con Cò', 25, 'active', 'Cám Con Cò', NOW()),
(2, 'Cám Đại Bàng', 25, 'active', 'Cám Đại Bàng', NOW());

-- 3. Thêm feed_types (sẽ tự tạo inventory_items)
-- Sẽ được tạo qua FeedBrandService khi tạo feed_brand mới

-- 4. Thêm inventory_items cho trấu (litter)
INSERT INTO inventory_items (name, category, sub_category, unit, status) VALUES
('Trấu rơm', 'production', 'litter', 'bao', 'active'),
('Mùn cưa', 'production', 'litter', 'bao', 'active');
```

## v0.1.5 - Cycle Feed Program Items (2026-03-14)

### Create table cycle_feed_program_items

```sql
-- Bỏ FK tạm thời nếu gặp lỗi kiểu dữ liệu
CREATE TABLE IF NOT EXISTS cycle_feed_program_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_feed_program_id INT NOT NULL,
    inventory_item_id INT NOT NULL,
    stage ENUM('chick','grower','adult') NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cfpi_program (cycle_feed_program_id),
    INDEX idx_cfpi_stage (stage)
);
```

## v0.1.4 - Feed Brand Auto-Generate (2026-03-14)

### Add column `ref_feed_type_id` to `inventory_items`

```sql
ALTER TABLE inventory_items
ADD COLUMN ref_feed_type_id INT NULL AFTER ref_feed_brand_id,
ADD INDEX idx_ref_feed_type_id (ref_feed_type_id);
```

### Fix: Re-create inventory_items from feed_types (NOT feed_brands)

**Important**: inventory_items cần liên kết với feed_types (từng loại cám theo giai đoạn), KHÔNG phải feed_brands.

```sql
-- 1. Xóa inventory_items feed cũ (nếu có)
DELETE FROM inventory_items
WHERE category = 'production' AND sub_category = 'feed';

-- 2. Tạo mới inventory_items từ feed_types (mỗi feed_type = 1 inventory_item)
INSERT INTO inventory_items (name, category, sub_category, unit, ref_feed_brand_id, ref_feed_type_id, status)
SELECT
    CONCAT(ft.code, ' - ', fb.name, ' - ', ft.name) AS name,
    'production' AS category,
    'feed' AS sub_category,
    'bao' AS unit,
    ft.feed_brand_id,
    ft.id AS ref_feed_type_id,
    'active' AS status
FROM feed_types ft
JOIN feed_brands fb ON ft.feed_brand_id = fb.id
WHERE ft.status = 'active';
```

### Fix: Cleanup duplicate inventory_items (nếu có)

Nếu bị double items, chạy SQL này để xóa duplicates:

```sql
-- Tìm và xóa duplicate inventory_items (giữ lại bản gới đầu tiên)
DELETE FROM inventory_items
WHERE id NOT IN (
    SELECT * FROM (
        SELECT MIN(id)
        FROM inventory_items
        WHERE category = 'production' AND sub_category = 'feed'
        GROUP BY ref_feed_type_id, ref_feed_brand_id
    ) AS keep
) AND category = 'production' AND sub_category = 'feed';
```

### Fix: Re-create inventory_items (với FK constraint)

```sql
-- Disable FK checks
SET FOREIGN_KEY_CHECKS = 0;

-- Xóa tất cả các bảng liên quan đến feed inventory
DELETE FROM inventory_purchases WHERE item_id IN (SELECT id FROM inventory_items WHERE category = 'production' AND sub_category = 'feed');
DELETE FROM inventory_transactions WHERE item_id IN (SELECT id FROM inventory_items WHERE category = 'production' AND sub_category = 'feed');
DELETE FROM inventory_stock WHERE item_id IN (SELECT id FROM inventory_items WHERE category = 'production' AND sub_category = 'feed');
DELETE FROM inventory_sales WHERE item_id IN (SELECT id FROM inventory_items WHERE category = 'production' AND sub_category = 'feed');

-- Xóa tất cả feed trong inventory
DELETE FROM inventory_items WHERE category = 'production' AND sub_category = 'feed';

-- Re-sync từ feed_types
INSERT INTO inventory_items (name, category, sub_category, unit, ref_feed_brand_id, ref_feed_type_id, status)
SELECT
    CONCAT(ft.code, ' - ', fb.name, ' - ', ft.name) AS name,
    'production', 'feed', 'bao',
    ft.feed_brand_id, ft.id, 'active'
FROM feed_types ft
JOIN feed_brands fb ON ft.feed_brand_id = fb.id
WHERE ft.status = 'active';

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;
```
