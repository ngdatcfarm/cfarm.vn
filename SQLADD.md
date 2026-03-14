# SQL Changes for v0.1.x

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
