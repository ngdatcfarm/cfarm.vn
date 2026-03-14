# SQL Changes for v0.1.x

## v0.1.4 - Feed Brand Auto-Generate (2026-03-14)

### Add column `ref_feed_type_id` to `inventory_items`

```sql
ALTER TABLE inventory_items
ADD COLUMN ref_feed_type_id INT NULL AFTER ref_feed_brand_id,
ADD INDEX idx_ref_feed_type_id (ref_feed_type_id);
```

### Optional: Update existing data to link feed_brands to inventory_items

```sql
-- Link existing feed_brands to inventory_items (run if needed)
INSERT INTO inventory_items (name, category, sub_category, unit, ref_feed_brand_id, status)
SELECT
    fb.name,
    'production',
    'feed',
    'bao',
    fb.id,
    'active'
FROM feed_brands fb
WHERE NOT EXISTS (
    SELECT 1 FROM inventory_items ii
    WHERE ii.ref_feed_brand_id = fb.id
    AND ii.category = 'production'
    AND ii.sub_category = 'feed'
);
```
