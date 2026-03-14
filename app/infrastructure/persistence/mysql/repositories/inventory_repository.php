<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Mysql\Repositories;

use PDO;

class InventoryRepository
{
    public function __construct(private PDO $pdo) {}

    private function fetch(string $sql, array $p = []): ?array
    {
        $s = $this->pdo->prepare($sql); $s->execute($p);
        $r = $s->fetch(); return $r ?: null;
    }
    private function fetch_all(string $sql, array $p = []): array
    {
        $s = $this->pdo->prepare($sql); $s->execute($p); return $s->fetchAll();
    }
    private function insert(string $sql, array $p = []): int
    {
        $this->pdo->prepare($sql)->execute($p);
        return (int)$this->pdo->lastInsertId();
    }
    private function run(string $sql, array $p = []): void
    {
        $this->pdo->prepare($sql)->execute($p);
    }

    // SUPPLIERS
    public function list_suppliers(): array
    {
        return $this->fetch_all("SELECT * FROM suppliers WHERE status='active' ORDER BY name");
    }
    public function find_supplier(int $id): ?array
    {
        return $this->fetch("SELECT * FROM suppliers WHERE id=:id", [':id'=>$id]);
    }
    public function create_supplier(array $d): int
    {
        return $this->insert(
            "INSERT INTO suppliers (name,phone,address,note) VALUES (:name,:phone,:address,:note)",
            [':name'=>$d['name'],':phone'=>$d['phone']??null,':address'=>$d['address']??null,':note'=>$d['note']??null]
        );
    }
    public function update_supplier(int $id, array $d): void
    {
        $this->run(
            "UPDATE suppliers SET name=:name,phone=:phone,address=:address,note=:note,status=:status WHERE id=:id",
            [':name'=>$d['name'],':phone'=>$d['phone']??null,':address'=>$d['address']??null,
             ':note'=>$d['note']??null,':status'=>$d['status']??'active',':id'=>$id]
        );
    }

    // ITEMS
    public function list_items(?string $category = null): array
    {
        $clauses = ["ii.status='active'"];
        $params = [];
        if ($category) {
            $clauses[] = "ii.category=:cat";
            $params[':cat'] = $category;
        }
        $where = "WHERE " . implode(" AND ", $clauses);
        return $this->fetch_all("
            SELECT DISTINCT ii.*, s.name AS supplier_name,
                   m.name AS medication_name,
                   fb.name AS feed_brand_name,
                   ft.code AS feed_type_code
            FROM inventory_items ii
            LEFT JOIN suppliers s       ON ii.supplier_id       = s.id
            LEFT JOIN medications m     ON ii.ref_medication_id = m.id
            LEFT JOIN feed_brands fb    ON ii.ref_feed_brand_id = fb.id
            LEFT JOIN feed_types ft     ON ii.ref_feed_type_id  = ft.id
            {$where} ORDER BY ii.category, ii.sub_category, ii.name
        ", $params);
    }
    public function find_item(int $id): ?array
    {
        return $this->fetch("
            SELECT ii.*, s.name AS supplier_name
            FROM inventory_items ii
            LEFT JOIN suppliers s ON ii.supplier_id=s.id
            WHERE ii.id=:id
        ", [':id'=>$id]);
    }
    public function create_item(array $d): int
    {
        return $this->insert("
            INSERT INTO inventory_items
                (name,category,sub_category,unit,ref_medication_id,ref_feed_brand_id,min_stock_alert,supplier_id,note)
            VALUES (:name,:cat,:sub,:unit,:med,:feed,:min,:sup,:note)
        ", [
            ':name'=>$d['name'],':cat'=>$d['category'],':sub'=>$d['sub_category'],
            ':unit'=>$d['unit'],':med'=>$d['ref_medication_id']??null,
            ':feed'=>$d['ref_feed_brand_id']??null,':min'=>$d['min_stock_alert']??0,
            ':sup'=>$d['supplier_id']??null,':note'=>$d['note']??null,
        ]);
    }
    public function update_item(int $id, array $d): void
    {
        $this->run("
            UPDATE inventory_items
            SET name=:name,sub_category=:sub,unit=:unit,min_stock_alert=:min,
                supplier_id=:sup,ref_medication_id=:med,ref_feed_brand_id=:feed,
                note=:note,status=:status WHERE id=:id
        ", [
            ':name'=>$d['name'],':sub'=>$d['sub_category'],':unit'=>$d['unit'],
            ':min'=>$d['min_stock_alert']??0,':sup'=>$d['supplier_id']??null,
            ':med'=>$d['ref_medication_id']??null,':feed'=>$d['ref_feed_brand_id']??null,
            ':note'=>$d['note']??null,':status'=>$d['status']??'active',':id'=>$id,
        ]);
    }

    // STOCK
    public function get_stock(int $item_id, ?int $barn_id): float
    {
        $r = $this->fetch(
            "SELECT quantity FROM inventory_stock WHERE item_id=:item AND barn_id<=>:barn",
            [':item'=>$item_id,':barn'=>$barn_id]
        );
        return $r ? (float)$r['quantity'] : 0.0;
    }
    public function upsert_stock(int $item_id, ?int $barn_id, float $delta): void
    {
        $this->run("
            INSERT INTO inventory_stock (item_id,barn_id,quantity) VALUES (:item,:barn,:delta)
            ON DUPLICATE KEY UPDATE quantity=quantity+:delta2
        ", [':item'=>$item_id,':barn'=>$barn_id,':delta'=>$delta,':delta2'=>$delta]);
    }
    public function list_stock_for_item(int $item_id): array
    {
        return $this->fetch_all("
            SELECT s.*, b.name AS barn_name
            FROM inventory_stock s
            LEFT JOIN barns b ON s.barn_id=b.id
            WHERE s.item_id=:item ORDER BY s.barn_id IS NULL DESC, b.name
        ", [':item'=>$item_id]);
    }
    public function list_low_stock_items(): array
    {
        return $this->fetch_all("
            SELECT ii.*, COALESCE(c.quantity,0) AS central_stock
            FROM inventory_items ii
            LEFT JOIN inventory_stock c ON c.item_id=ii.id AND c.barn_id IS NULL
            WHERE ii.status='active' AND ii.min_stock_alert>0
              AND COALESCE(c.quantity,0)<=ii.min_stock_alert
            ORDER BY ii.category, ii.name
        ");
    }

    // PURCHASES
    public function create_purchase(array $d): int
    {
        return $this->insert("
            INSERT INTO inventory_purchases
                (item_id,supplier_id,quantity,unit_price,total_price,purchased_at,expiry_date,batch_no,storage_location,note)
            VALUES (:item,:sup,:qty,:up,:tp,:date,:exp,:batch,:loc,:note)
        ", [
            ':item'=>$d['item_id'],':sup'=>$d['supplier_id']??null,':qty'=>$d['quantity'],
            ':up'=>$d['unit_price'],':tp'=>$d['total_price'],':date'=>$d['purchased_at'],
            ':exp'=>$d['expiry_date']??null,':batch'=>$d['batch_no']??null,
            ':loc'=>$d['storage_location']??null,':note'=>$d['note']??null,
        ]);
    }
    public function list_purchases(?int $item_id = null, int $limit = 50): array
    {
        $where = $item_id ? "WHERE p.item_id=:item" : "";
        $params = $item_id ? [':item'=>$item_id,':limit'=>$limit] : [':limit'=>$limit];
        return $this->fetch_all("
            SELECT p.*, ii.name AS item_name, ii.unit, s.name AS supplier_name
            FROM inventory_purchases p
            JOIN inventory_items ii ON p.item_id=ii.id
            LEFT JOIN suppliers s   ON p.supplier_id=s.id
            {$where} ORDER BY p.purchased_at DESC LIMIT :limit
        ", $params);
    }
    public function find_purchase(int $id): ?array
    {
        return $this->fetch("SELECT * FROM inventory_purchases WHERE id=:id",[':id'=>$id]);
    }
    public function get_avg_cost(int $item_id): float
    {
        $r = $this->fetch("SELECT AVG(unit_price) AS avg FROM inventory_purchases WHERE item_id=:item",[':item'=>$item_id]);
        return $r ? (float)$r['avg'] : 0.0;
    }

    // TRANSACTIONS
    public function create_transaction(array $d): int
    {
        return $this->insert("
            INSERT INTO inventory_transactions
                (item_id,txn_type,from_barn_id,to_barn_id,quantity,unit_price,
                 ref_purchase_id,ref_care_feed_id,ref_care_medication_id,
                 cycle_id,install_location,note,recorded_at)
            VALUES (:item,:type,:from,:to,:qty,:up,:rp,:rf,:rm,:cycle,:loc,:note,:rec)
        ", [
            ':item'=>$d['item_id'],':type'=>$d['txn_type'],':from'=>$d['from_barn_id']??null,
            ':to'=>$d['to_barn_id']??null,':qty'=>$d['quantity'],':up'=>$d['unit_price']??null,
            ':rp'=>$d['ref_purchase_id']??null,':rf'=>$d['ref_care_feed_id']??null,
            ':rm'=>$d['ref_care_medication_id']??null,':cycle'=>$d['cycle_id']??null,
            ':loc'=>$d['install_location']??null,':note'=>$d['note']??null,
            ':rec'=>$d['recorded_at']??date('Y-m-d H:i:s'),
        ]);
    }
    public function list_transactions(int $item_id, int $limit = 30): array
    {
        return $this->fetch_all("
            SELECT t.*, fb.name AS from_barn_name, tb.name AS to_barn_name
            FROM inventory_transactions t
            LEFT JOIN barns fb ON t.from_barn_id=fb.id
            LEFT JOIN barns tb ON t.to_barn_id=tb.id
            WHERE t.item_id=:item ORDER BY t.recorded_at DESC LIMIT :limit
        ", [':item'=>$item_id,':limit'=>$limit]);
    }
    public function list_transactions_by_barn(int $barn_id, int $limit = 50): array
    {
        return $this->fetch_all("
            SELECT t.*, ii.name AS item_name, ii.unit
            FROM inventory_transactions t
            JOIN inventory_items ii ON t.item_id=ii.id
            WHERE t.from_barn_id=:barn OR t.to_barn_id=:barn2
            ORDER BY t.recorded_at DESC LIMIT :limit
        ", [':barn'=>$barn_id,':barn2'=>$barn_id,':limit'=>$limit]);
    }

    // SALES
    public function create_sale(array $d): int
    {
        return $this->insert("
            INSERT INTO inventory_sales
                (item_id,buyer_name,buyer_phone,quantity,unit_price,total_price,sold_at,note)
            VALUES (:item,:buyer,:phone,:qty,:up,:tp,:date,:note)
        ", [
            ':item'=>$d['item_id'],':buyer'=>$d['buyer_name'],':phone'=>$d['buyer_phone']??null,
            ':qty'=>$d['quantity'],':up'=>$d['unit_price'],':tp'=>$d['total_price'],
            ':date'=>$d['sold_at'],':note'=>$d['note']??null,
        ]);
    }
    public function list_sales(?int $item_id = null, int $limit = 50): array
    {
        $where = $item_id ? "WHERE s.item_id=:item" : "";
        $params = $item_id ? [':item'=>$item_id,':limit'=>$limit] : [':limit'=>$limit];
        return $this->fetch_all("
            SELECT s.*, ii.name AS item_name, ii.unit
            FROM inventory_sales s JOIN inventory_items ii ON s.item_id=ii.id
            {$where} ORDER BY s.sold_at DESC LIMIT :limit
        ", $params);
    }

    // ASSETS
    public function create_asset(array $d): int
    {
        return $this->insert("
            INSERT INTO inventory_consumable_assets
                (item_id,serial_no,status,barn_id,install_location,ref_device_id,installed_at,warranty_until,purchase_id,note)
            VALUES (:item,:serial,:status,:barn,:loc,:dev,:inst,:warranty,:pur,:note)
        ", [
            ':item'=>$d['item_id'],':serial'=>$d['serial_no']??null,':status'=>$d['status']??'stock',
            ':barn'=>$d['barn_id']??null,':loc'=>$d['install_location']??null,
            ':dev'=>$d['ref_device_id']??null,':inst'=>$d['installed_at']??null,
            ':warranty'=>$d['warranty_until']??null,':pur'=>$d['purchase_id']??null,':note'=>$d['note']??null,
        ]);
    }
    public function update_asset(int $id, array $d): void
    {
        $this->run("
            UPDATE inventory_consumable_assets
            SET status=:status,barn_id=:barn,install_location=:loc,
                ref_device_id=:dev,installed_at=:inst,warranty_until=:warranty,note=:note
            WHERE id=:id
        ", [
            ':status'=>$d['status'],':barn'=>$d['barn_id']??null,':loc'=>$d['install_location']??null,
            ':dev'=>$d['ref_device_id']??null,':inst'=>$d['installed_at']??null,
            ':warranty'=>$d['warranty_until']??null,':note'=>$d['note']??null,':id'=>$id,
        ]);
    }
    public function find_asset(int $id): ?array
    {
        return $this->fetch("
            SELECT a.*, ii.name AS item_name, ii.unit, b.name AS barn_name
            FROM inventory_consumable_assets a
            JOIN inventory_items ii ON a.item_id=ii.id
            LEFT JOIN barns b ON a.barn_id=b.id
            WHERE a.id=:id
        ", [':id'=>$id]);
    }
    public function list_assets(?string $status = null, ?int $item_id = null): array
    {
        $w = []; $p = [];
        if ($status)  { $w[] = "a.status=:status";   $p[':status']  = $status; }
        if ($item_id) { $w[] = "a.item_id=:item_id"; $p[':item_id'] = $item_id; }
        $where = $w ? "WHERE ".implode(' AND ',$w) : "";
        return $this->fetch_all("
            SELECT a.*, ii.name AS item_name, ii.sub_category,
                   b.name AS barn_name, d.name AS device_name
            FROM inventory_consumable_assets a
            JOIN inventory_items ii ON a.item_id=ii.id
            LEFT JOIN barns b   ON a.barn_id=b.id
            LEFT JOIN devices d ON a.ref_device_id=d.id
            {$where} ORDER BY a.status, ii.name
        ", $p);
    }
    public function list_expiring_warranties(int $days = 30): array
    {
        return $this->fetch_all("
            SELECT a.*, ii.name AS item_name, b.name AS barn_name
            FROM inventory_consumable_assets a
            JOIN inventory_items ii ON a.item_id=ii.id
            LEFT JOIN barns b ON a.barn_id=b.id
            WHERE a.warranty_until IS NOT NULL
              AND a.warranty_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND a.status != 'disposed'
            ORDER BY a.warranty_until
        ", [':days'=>$days]);
    }

    // LITTERS
    public function create_litter(array $d): int
    {
        return $this->insert("
            INSERT INTO care_litters (cycle_id,item_id,quantity,unit,note,recorded_at)
            VALUES (:cycle,:item,:qty,:unit,:note,:rec)
        ", [
            ':cycle'=>$d['cycle_id'],':item'=>$d['item_id']??null,':qty'=>$d['quantity'],
            ':unit'=>$d['unit']??'bao',':note'=>$d['note']??null,
            ':rec'=>$d['recorded_at']??date('Y-m-d H:i:s'),
        ]);
    }
    public function list_litters_by_cycle(int $cycle_id): array
    {
        return $this->fetch_all("
            SELECT l.*, ii.name AS item_name
            FROM care_litters l
            LEFT JOIN inventory_items ii ON l.item_id=ii.id
            WHERE l.cycle_id=:cycle ORDER BY l.recorded_at DESC
        ", [':cycle'=>$cycle_id]);
    }
}
