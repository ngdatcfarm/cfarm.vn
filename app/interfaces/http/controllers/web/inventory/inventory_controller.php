<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Inventory;

use App\Infrastructure\Persistence\Mysql\Repositories\InventoryRepository;
use PDO;

class InventoryController
{
    private InventoryRepository $repo;
    public function __construct(private PDO $pdo)
    {
        $this->repo = new InventoryRepository($pdo);
    }

    private function json(bool $ok, string $msg, array $data = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok'=>$ok,'message'=>$msg], $data));
    }

    public function index(array $vars): void
    {
        $low_stock   = $this->repo->list_low_stock_items();
        $expiring    = $this->repo->list_expiring_warranties(30);
        $recent_txns = $this->pdo->query("
            SELECT t.*, ii.name AS item_name, ii.unit,
                   fb.name AS from_barn, tb.name AS to_barn
            FROM inventory_transactions t
            JOIN inventory_items ii ON t.item_id=ii.id
            LEFT JOIN barns fb ON t.from_barn_id=fb.id
            LEFT JOIN barns tb ON t.to_barn_id=tb.id
            ORDER BY t.created_at DESC LIMIT 10
        ")->fetchAll();
        $stats = $this->pdo->query("
            SELECT
              (SELECT COUNT(*) FROM inventory_items WHERE status='active') AS total_items,
              (SELECT COUNT(*) FROM inventory_consumable_assets WHERE status='installed') AS installed_assets,
              (SELECT COUNT(*) FROM inventory_consumable_assets WHERE status='broken') AS broken_assets,
              (SELECT COUNT(*) FROM inventory_purchases WHERE purchased_at>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)) AS recent_purchases
        ")->fetch();
        $title = 'Kho Vật Tư';
        extract(compact('low_stock','expiring','recent_txns','stats'));
        require view_path('inventory/index.php');
    }

    public function production(array $vars): void
    {
        $items     = $this->repo->list_items('production');
        $barns     = $this->pdo->query("SELECT id,name FROM barns ORDER BY name")->fetchAll();
        $suppliers = $this->repo->list_suppliers();
        foreach ($items as &$item) {
            $item['central_stock'] = $this->repo->get_stock((int)$item['id'], null);
            $item['barn_stocks']   = $this->repo->list_stock_for_item((int)$item['id']);
        }
        $expiring_meds = $this->pdo->query("
            SELECT p.*, ii.name AS item_name
            FROM inventory_purchases p
            JOIN inventory_items ii ON p.item_id=ii.id
            WHERE p.expiry_date IS NOT NULL
              AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
              AND p.expiry_date >= CURDATE()
            ORDER BY p.expiry_date
        ")->fetchAll();
        $title = 'Vật Tư Sản Xuất';
        extract(compact('items','barns','suppliers','expiring_meds'));
        require view_path('inventory/production.php');
    }

    public function consumable(array $vars): void
    {
        $items   = $this->repo->list_items('consumable');
        $assets  = $this->repo->list_assets();
        $barns   = $this->pdo->query("SELECT id,name FROM barns ORDER BY name")->fetchAll();
        $devices = $this->pdo->query("SELECT id,name,device_code FROM devices ORDER BY name")->fetchAll();
        foreach ($items as &$item) {
            $item['central_stock'] = $this->repo->get_stock((int)$item['id'], null);
        }
        $title = 'Vật Tư Tiêu Hao';
        extract(compact('items','assets','barns','devices'));
        require view_path('inventory/consumable.php');
    }

    public function store_purchase(array $vars): void
    {
        try {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $qty     = (float)($_POST['quantity'] ?? 0);
            $up      = (int)($_POST['unit_price'] ?? 0);
            if (!$item_id) throw new \InvalidArgumentException('Chọn vật tư');
            if ($qty <= 0) throw new \InvalidArgumentException('Số lượng phải > 0');
            if ($up <= 0)  throw new \InvalidArgumentException('Đơn giá phải > 0');
            $item = $this->repo->find_item($item_id);
            if (!$item) throw new \InvalidArgumentException('Vật tư không tồn tại');

            $data = [
                'item_id'          => $item_id,
                'supplier_id'      => !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
                'quantity'         => $qty,
                'unit_price'       => $up,
                'total_price'      => (int)($qty * $up),
                'purchased_at'     => $_POST['purchased_at'] ?? date('Y-m-d'),
                'expiry_date'      => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                'batch_no'         => !empty($_POST['batch_no']) ? trim($_POST['batch_no']) : null,
                'storage_location' => !empty($_POST['storage_location']) ? trim($_POST['storage_location']) : null,
                'note'             => !empty($_POST['note']) ? trim($_POST['note']) : null,
            ];
            $purchase_id = $this->repo->create_purchase($data);

            $barn_id = null;
            if (in_array($item['sub_category'], ['feed','litter']) && !empty($_POST['barn_id'])) {
                $barn_id = (int)$_POST['barn_id'];
            }
            $this->repo->upsert_stock($item_id, $barn_id, $qty);
            $this->repo->create_transaction([
                'item_id'         => $item_id,
                'txn_type'        => 'purchase',
                'to_barn_id'      => $barn_id,
                'quantity'        => $qty,
                'unit_price'      => $up,
                'ref_purchase_id' => $purchase_id,
                'note'            => $data['note'],
                'recorded_at'     => $data['purchased_at'].' 00:00:00',
            ]);
            if ($item['category'] === 'consumable' && !empty($_POST['create_assets'])) {
                for ($i = 0; $i < (int)$qty; $i++) {
                    $this->repo->create_asset(['item_id'=>$item_id,'status'=>'stock','purchase_id'=>$purchase_id]);
                }
            }
            $this->json(true, 'Đã nhập kho thành công', ['purchase_id'=>$purchase_id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_transfer(array $vars): void
    {
        try {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $barn_id = (int)($_POST['barn_id'] ?? 0);
            $qty     = (float)($_POST['quantity'] ?? 0);
            if (!$item_id) throw new \InvalidArgumentException('Chọn vật tư');
            if (!$barn_id) throw new \InvalidArgumentException('Chọn chuồng nhận');
            if ($qty <= 0) throw new \InvalidArgumentException('Số lượng phải > 0');
            $central = $this->repo->get_stock($item_id, null);
            if ($central < $qty) throw new \InvalidArgumentException("Kho trung tâm chỉ còn {$central} — không đủ");
            $this->repo->upsert_stock($item_id, null, -$qty);
            $this->repo->upsert_stock($item_id, $barn_id, $qty);
            $this->repo->create_transaction([
                'item_id'      => $item_id,
                'txn_type'     => 'transfer_out',
                'from_barn_id' => null,
                'to_barn_id'   => $barn_id,
                'quantity'     => $qty,
                'note'         => !empty($_POST['note']) ? trim($_POST['note']) : null,
                'recorded_at'  => date('Y-m-d H:i:s'),
            ]);
            $this->json(true, 'Đã xuất về chuồng thành công');
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_sale(array $vars): void
    {
        try {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $qty     = (float)($_POST['quantity'] ?? 0);
            $up      = (int)($_POST['unit_price'] ?? 0);
            $buyer   = trim($_POST['buyer_name'] ?? '');
            if (!$item_id) throw new \InvalidArgumentException('Chọn vật tư');
            if ($qty <= 0) throw new \InvalidArgumentException('Số lượng phải > 0');
            if (!$buyer)   throw new \InvalidArgumentException('Nhập tên người mua');
            $central = $this->repo->get_stock($item_id, null);
            if ($central < $qty) throw new \InvalidArgumentException("Tồn kho không đủ (còn {$central})");
            $sale_id = $this->repo->create_sale([
                'item_id'     => $item_id,
                'buyer_name'  => $buyer,
                'buyer_phone' => !empty($_POST['buyer_phone']) ? trim($_POST['buyer_phone']) : null,
                'quantity'    => $qty,
                'unit_price'  => $up,
                'total_price' => (int)($qty * $up),
                'sold_at'     => $_POST['sold_at'] ?? date('Y-m-d'),
                'note'        => !empty($_POST['note']) ? trim($_POST['note']) : null,
            ]);
            $this->repo->upsert_stock($item_id, null, -$qty);
            $this->repo->create_transaction([
                'item_id'     => $item_id,
                'txn_type'    => 'sell',
                'quantity'    => $qty,
                'unit_price'  => $up,
                'note'        => "Bán cho {$buyer}",
                'install_location' => 'sale:'.$sale_id,
                'recorded_at' => ($_POST['sold_at'] ?? date('Y-m-d')).' 00:00:00',
            ]);
            $this->json(true, 'Đã ghi bán hàng', ['sale_id'=>$sale_id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_adjust(array $vars): void
    {
        try {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $barn_id = !empty($_POST['barn_id']) ? (int)$_POST['barn_id'] : null;
            $new_qty = (float)($_POST['new_quantity'] ?? -1);
            if (!$item_id) throw new \InvalidArgumentException('Chọn vật tư');
            if ($new_qty < 0) throw new \InvalidArgumentException('Số lượng không hợp lệ');
            $current = $this->repo->get_stock($item_id, $barn_id);
            $delta   = $new_qty - $current;
            $this->repo->upsert_stock($item_id, $barn_id, $delta);
            $this->repo->create_transaction([
                'item_id'      => $item_id,
                'txn_type'     => 'adjust',
                'from_barn_id' => $barn_id,
                'quantity'     => abs($delta),
                'note'         => "Điều chỉnh: {$current} → {$new_qty}. ".($_POST['note']??''),
                'recorded_at'  => date('Y-m-d H:i:s'),
            ]);
            $this->json(true, 'Đã điều chỉnh tồn kho');
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function update_asset_status(array $vars): void
    {
        try {
            $id    = (int)$vars['id'];
            $asset = $this->repo->find_asset($id);
            if (!$asset) throw new \InvalidArgumentException('Không tìm thấy thiết bị');
            $status = $_POST['status'] ?? $asset['status'];
            if (!in_array($status, ['stock','installed','broken','disposed']))
                throw new \InvalidArgumentException('Trạng thái không hợp lệ');
            $this->repo->update_asset($id, [
                'status'           => $status,
                'barn_id'          => !empty($_POST['barn_id']) ? (int)$_POST['barn_id'] : null,
                'install_location' => $_POST['install_location'] ?? null,
                'ref_device_id'    => !empty($_POST['ref_device_id']) ? (int)$_POST['ref_device_id'] : null,
                'installed_at'     => !empty($_POST['installed_at']) ? $_POST['installed_at'] : null,
                'warranty_until'   => !empty($_POST['warranty_until']) ? $_POST['warranty_until'] : null,
                'note'             => $_POST['note'] ?? null,
            ]);
            $this->json(true, 'Đã cập nhật trạng thái thiết bị');
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_litter(array $vars): void
    {
        try {
            $cycle_id = (int)($_POST['cycle_id'] ?? 0);
            $qty      = (float)($_POST['quantity'] ?? 0);
            $item_id  = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : null;
            if (!$cycle_id) throw new \InvalidArgumentException('Thiếu cycle');
            if ($qty <= 0)  throw new \InvalidArgumentException('Số lượng phải > 0');
            $stmt = $this->pdo->prepare("SELECT barn_id FROM cycles WHERE id=:id AND status='active'");
            $stmt->execute([':id'=>$cycle_id]);
            $c = $stmt->fetch();
            if (!$c) throw new \InvalidArgumentException('Cycle không hợp lệ hoặc đã đóng');
            $barn_id = (int)$c['barn_id'];
            $this->repo->create_litter([
                'cycle_id'   => $cycle_id,
                'item_id'    => $item_id,
                'quantity'   => $qty,
                'unit'       => $_POST['unit'] ?? 'bao',
                'note'       => !empty($_POST['note']) ? trim($_POST['note']) : null,
                'recorded_at'=> $_POST['recorded_at'] ?? date('Y-m-d H:i:s'),
            ]);
            if ($item_id) {
                $this->repo->upsert_stock($item_id, $barn_id, -$qty);
                $this->repo->create_transaction([
                    'item_id'      => $item_id,
                    'txn_type'     => 'use_litter',
                    'from_barn_id' => $barn_id,
                    'quantity'     => $qty,
                    'cycle_id'     => $cycle_id,
                    'note'         => "Trải trấu - cycle #{$cycle_id}",
                    'recorded_at'  => date('Y-m-d H:i:s'),
                ]);
            }
            $this->json(true, 'Đã ghi sử dụng trấu');
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function store_item(array $vars): void
    {
        try {
            $name = trim($_POST['name'] ?? '');
            $cat  = $_POST['category'] ?? '';
            $unit = trim($_POST['unit'] ?? '');
            $sub  = $_POST['sub_category'] ?? 'other';
            if (!$name) throw new \InvalidArgumentException('Nhập tên vật tư');
            if (!in_array($cat, ['production','consumable'])) throw new \InvalidArgumentException('Nhóm không hợp lệ');
            if (!$unit) throw new \InvalidArgumentException('Nhập đơn vị');
            $id = $this->repo->create_item([
                'name'              => $name,
                'category'          => $cat,
                'sub_category'      => $sub,
                'unit'              => $unit,
                'ref_medication_id' => !empty($_POST['ref_medication_id']) ? (int)$_POST['ref_medication_id'] : null,
                'ref_feed_brand_id' => !empty($_POST['ref_feed_brand_id']) ? (int)$_POST['ref_feed_brand_id'] : null,
                'min_stock_alert'   => (float)($_POST['min_stock_alert'] ?? 0),
                'supplier_id'       => !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
                'note'              => !empty($_POST['note']) ? trim($_POST['note']) : null,
            ]);
            $this->json(true, 'Đã thêm vật tư', ['id'=>$id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }

    public function get_item_stock(array $vars): void
    {
        $id   = (int)$vars['id'];
        $item = $this->repo->find_item($id);
        if (!$item) { $this->json(false, 'Không tìm thấy'); return; }
        $stocks = $this->repo->list_stock_for_item($id);
        $txns   = $this->repo->list_transactions($id, 20);
        $this->json(true, 'ok', ['item'=>$item,'stocks'=>$stocks,'transactions'=>$txns]);
    }
}
