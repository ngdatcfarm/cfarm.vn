<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Inventory;
use PDO;
class InventoryEditController
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    private function json(bool $ok, mixed $data = null, string $message = ''): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($ok ? ['ok'=>true,'data'=>$data] : ['ok'=>false,'message'=>$message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    private function p(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }
    private function adjustStock(int $itemId, ?int $barnId, float $delta): void
    {
        if ($barnId !== null) {
            $this->db->prepare("INSERT INTO inventory_stock (item_id,barn_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")->execute([$itemId,$barnId,$delta]);
        } else {
            $this->db->prepare("INSERT INTO inventory_stock (item_id,barn_id,quantity) VALUES (?,NULL,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")->execute([$itemId,$delta]);
        }
    }
    private function getStock(int $itemId, ?int $barnId): float
    {
        if ($barnId !== null) {
            $s = $this->db->prepare("SELECT quantity FROM inventory_stock WHERE item_id=? AND barn_id=?");
            $s->execute([$itemId,$barnId]);
        } else {
            $s = $this->db->prepare("SELECT quantity FROM inventory_stock WHERE item_id=? AND barn_id IS NULL");
            $s->execute([$itemId]);
        }
        return (float)($s->fetchColumn() ?: 0);
    }

    // ── ITEMS ──
    public function getItem(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $s = $this->db->prepare("SELECT * FROM inventory_items WHERE id=?");
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $row ? $this->json(true,$row) : $this->json(false,null,'Không tìm thấy');
    }
    public function updateItem(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $name=$this->p('name'); $sub=$this->p('sub_category'); $unit=$this->p('unit');
        $min=(float)($this->p('min_stock_alert')??0); $suppId=$this->p('supplier_id')?:null; $note=$this->p('note');
        if (!$name) $this->json(false,null,'Tên không được để trống');
        if (!$unit) $this->json(false,null,'Đơn vị không được để trống');
        $this->db->prepare("UPDATE inventory_items SET name=?,sub_category=?,unit=?,min_stock_alert=?,supplier_id=?,note=? WHERE id=?")->execute([$name,$sub,$unit,$min,$suppId,$note,$id]);
        $this->json(true);
    }
    public function deleteItem(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);

        // Check if item exists
        $check = $this->db->prepare("SELECT id, name, status FROM inventory_items WHERE id=?");
        $check->execute([$id]);
        $item = $check->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $this->json(false, null, 'Không tìm thấy vật tư');
        }

        // If already inactive, consider it deleted
        if ($item['status'] === 'inactive') {
            $this->json(true);
        }

        // Check stock - only count positive quantities
        $s = $this->db->prepare("SELECT COALESCE(SUM(quantity),0) FROM inventory_stock WHERE item_id=? AND quantity > 0");
        $s->execute([$id]);
        $total = (float)$s->fetchColumn();

        if ($total > 0) {
            $this->json(false, null, "Không thể xóa: còn {$total} trong kho.");
        }

        // Update status to inactive
        $stmt = $this->db->prepare("UPDATE inventory_items SET status='inactive' WHERE id=? AND status='active'");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->json(false, null, 'Không thể xóa vật tư');
        }

        $this->json(true);
    }

    // ── PURCHASES ──
    public function getPurchase(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $s = $this->db->prepare("SELECT p.*,t.to_barn_id AS barn_id,t.id AS txn_id FROM inventory_purchases p LEFT JOIN inventory_transactions t ON t.ref_purchase_id=p.id AND t.txn_type='purchase' WHERE p.id=? LIMIT 1");
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $row ? $this->json(true,$row) : $this->json(false,null,'Không tìm thấy');
    }
    public function updatePurchase(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $qty=(float)($this->p('quantity')??0); $price=(int)($this->p('unit_price')??0);
        $date=$this->p('purchased_at')?:date('Y-m-d'); $expiry=$this->p('expiry_date')?:null;
        $suppId=$this->p('supplier_id')?:null; $batch=$this->p('batch_no'); $storage=$this->p('storage_location'); $note=$this->p('note');
        if ($qty<=0) $this->json(false,null,'Số lượng phải lớn hơn 0');
        $s=$this->db->prepare("SELECT * FROM inventory_purchases WHERE id=?"); $s->execute([$id]);
        $old=$s->fetch(PDO::FETCH_ASSOC);
        if (!$old) $this->json(false,null,'Không tìm thấy');
        $ts=$this->db->prepare("SELECT id,item_id,to_barn_id FROM inventory_transactions WHERE ref_purchase_id=? AND txn_type='purchase' LIMIT 1"); $ts->execute([$id]);
        $txn=$ts->fetch(PDO::FETCH_ASSOC);
        $delta=$qty-(float)$old['quantity'];
        if ($delta<0 && $txn) {
            $barnId=$txn['to_barn_id']?(int)$txn['to_barn_id']:null;
            $current=$this->getStock((int)$txn['item_id'],$barnId);
            if ($current+$delta<0) $this->json(false,null,"Tồn kho hiện tại ({$current}) không đủ để giảm.");
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE inventory_purchases SET quantity=?,unit_price=?,total_price=?,purchased_at=?,expiry_date=?,supplier_id=?,batch_no=?,storage_location=?,note=? WHERE id=?")->execute([$qty,$price,$qty*$price,$date,$expiry,$suppId,$batch,$storage,$note,$id]);
            if ($txn) {
                $this->db->prepare("UPDATE inventory_transactions SET quantity=?,unit_price=?,note=? WHERE id=?")->execute([$qty,$price,$note,$txn['id']]);
                if ($delta!=0) { $barnId=$txn['to_barn_id']?(int)$txn['to_barn_id']:null; $this->adjustStock((int)$txn['item_id'],$barnId,$delta); }
            }
            $this->db->commit(); $this->json(true);
        } catch (\Throwable $e) { $this->db->rollBack(); $this->json(false,null,'Lỗi: '.$e->getMessage()); }
    }
    public function deletePurchase(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $ts=$this->db->prepare("SELECT id,item_id,to_barn_id,quantity FROM inventory_transactions WHERE ref_purchase_id=? AND txn_type='purchase' LIMIT 1"); $ts->execute([$id]);
        $txn=$ts->fetch(PDO::FETCH_ASSOC);
        if (!$txn) $this->json(false,null,'Không tìm thấy giao dịch');
        $itemId=(int)$txn['item_id']; $barnId=$txn['to_barn_id']?(int)$txn['to_barn_id']:null; $qty=(float)$txn['quantity'];
        $current=$this->getStock($itemId,$barnId);
        if ($current<$qty) $this->json(false,null,"Tồn kho ({$current}) thấp hơn số đã nhập ({$qty}). Hàng có thể đã xuất.");
        $this->db->beginTransaction();
        try {
            $this->adjustStock($itemId,$barnId,-$qty);
            $this->db->prepare("DELETE FROM inventory_transactions WHERE ref_purchase_id=? AND txn_type='purchase'")->execute([$id]);
            $this->db->prepare("DELETE FROM inventory_purchases WHERE id=?")->execute([$id]);
            $this->db->commit(); $this->json(true);
        } catch (\Throwable $e) { $this->db->rollBack(); $this->json(false,null,'Lỗi: '.$e->getMessage()); }
    }

    // ── TRANSACTIONS (transfer_out) ──
    public function getTransaction(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $s=$this->db->prepare("SELECT t.*,b.name AS barn_name FROM inventory_transactions t LEFT JOIN barns b ON b.id=t.to_barn_id WHERE t.id=? AND t.txn_type='transfer_out'");
        $s->execute([$id]);
        $row=$s->fetch(PDO::FETCH_ASSOC);
        $row ? $this->json(true,$row) : $this->json(false,null,'Không tìm thấy');
    }
    public function updateTransaction(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $qty=(float)($this->p('quantity')??0); $newBarnId=(int)($this->p('barn_id')??0)?:null; $note=$this->p('note');
        if ($qty<=0) $this->json(false,null,'Số lượng phải lớn hơn 0');
        $s=$this->db->prepare("SELECT * FROM inventory_transactions WHERE id=? AND txn_type='transfer_out'"); $s->execute([$id]);
        $old=$s->fetch(PDO::FETCH_ASSOC);
        if (!$old) $this->json(false,null,'Không tìm thấy');
        $itemId=(int)$old['item_id']; $oldQty=(float)$old['quantity']; $oldBarnId=$old['to_barn_id']?(int)$old['to_barn_id']:null;
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE inventory_transactions SET quantity=?,to_barn_id=?,note=? WHERE id=?")->execute([$qty,$newBarnId,$note,$id]);
            $this->adjustStock($itemId,null,$oldQty);
            $this->adjustStock($itemId,$oldBarnId,-$oldQty);
            $this->adjustStock($itemId,null,-$qty);
            $this->adjustStock($itemId,$newBarnId,$qty);
            $this->db->commit(); $this->json(true);
        } catch (\Throwable $e) { $this->db->rollBack(); $this->json(false,null,'Lỗi: '.$e->getMessage()); }
    }
    public function deleteTransaction(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $s=$this->db->prepare("SELECT * FROM inventory_transactions WHERE id=? AND txn_type='transfer_out'"); $s->execute([$id]);
        $txn=$s->fetch(PDO::FETCH_ASSOC);
        if (!$txn) $this->json(false,null,'Không tìm thấy');
        $itemId=(int)$txn['item_id']; $barnId=$txn['to_barn_id']?(int)$txn['to_barn_id']:null; $qty=(float)$txn['quantity'];
        $barnStock=$this->getStock($itemId,$barnId);
        if ($barnStock<$qty) $this->json(false,null,"Kho barn ({$barnStock}) thấp hơn số đã xuất ({$qty}). Có thể đã dùng.");
        $this->db->beginTransaction();
        try {
            $this->adjustStock($itemId,$barnId,-$qty);
            $this->adjustStock($itemId,null,$qty);
            $this->db->prepare("DELETE FROM inventory_transactions WHERE id=?")->execute([$id]);
            $this->db->commit(); $this->json(true);
        } catch (\Throwable $e) { $this->db->rollBack(); $this->json(false,null,'Lỗi: '.$e->getMessage()); }
    }

    // ── SALES ──
    public function getSale(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $s=$this->db->prepare("SELECT * FROM inventory_sales WHERE id=?"); $s->execute([$id]);
        $row=$s->fetch(PDO::FETCH_ASSOC);
        $row ? $this->json(true,$row) : $this->json(false,null,'Không tìm thấy');
    }
    public function updateSale(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $qty=(float)($this->p('quantity')??0); $price=(int)($this->p('unit_price')??0);
        $buyerName=$this->p('buyer_name'); $buyerPhone=$this->p('buyer_phone');
        $soldAt=$this->p('sold_at')?:date('Y-m-d'); $note=$this->p('note');
        if ($qty<=0) $this->json(false,null,'Số lượng phải lớn hơn 0');
        if (!$buyerName) $this->json(false,null,'Tên người mua không được để trống');
        $s=$this->db->prepare("SELECT * FROM inventory_sales WHERE id=?"); $s->execute([$id]);
        $old=$s->fetch(PDO::FETCH_ASSOC);
        if (!$old) $this->json(false,null,'Không tìm thấy');
        $itemId=(int)$old['item_id']; $delta=$qty-(float)$old['quantity'];
        if ($delta>0) { $central=$this->getStock($itemId,null); if ($central<$delta) $this->json(false,null,"Không đủ tồn kho: còn {$central}, cần thêm {$delta}."); }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE inventory_sales SET quantity=?,unit_price=?,total_price=?,buyer_name=?,buyer_phone=?,sold_at=?,note=? WHERE id=?")->execute([$qty,$price,$qty*$price,$buyerName,$buyerPhone,$soldAt,$note,$id]);
            $this->db->prepare("UPDATE inventory_transactions SET quantity=?,unit_price=?,note=? WHERE install_location=? AND txn_type='sell' LIMIT 1")->execute([$qty,$price,$note,'sale:'.$id]);
            if ($delta!=0) $this->adjustStock($itemId,null,-$delta);
            $this->db->commit(); $this->json(true);
        } catch (\Throwable $e) { $this->db->rollBack(); $this->json(false,null,'Lỗi: '.$e->getMessage()); }
    }
    public function deleteSale(array $vars): never
    {
        $id = (int)($vars['id'] ?? 0);
        $s=$this->db->prepare("SELECT * FROM inventory_sales WHERE id=?"); $s->execute([$id]);
        $sale=$s->fetch(PDO::FETCH_ASSOC);
        if (!$sale) $this->json(false,null,'Không tìm thấy');
        $this->db->beginTransaction();
        try {
            $this->adjustStock((int)$sale['item_id'],null,(float)$sale['quantity']);
            $this->db->prepare("DELETE FROM inventory_transactions WHERE install_location=? AND txn_type='sell' LIMIT 1")->execute(['sale:'.$id]);
            $this->db->prepare("DELETE FROM inventory_sales WHERE id=?")->execute([$id]);
            $this->db->commit(); $this->json(true);
        } catch (\Throwable $e) { $this->db->rollBack(); $this->json(false,null,'Lỗi: '.$e->getMessage()); }
    }
}
