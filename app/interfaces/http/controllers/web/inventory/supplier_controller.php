<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Inventory;

use App\Infrastructure\Persistence\Mysql\Repositories\InventoryRepository;
use PDO;

class SupplierController
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
        $suppliers = $this->repo->list_suppliers();
        $title = 'Nhà Cung Cấp';
        extract(compact('suppliers'));
        require view_path('inventory/suppliers.php');
    }
    public function store(array $vars): void
    {
        try {
            $name = trim($_POST['name'] ?? '');
            if (!$name) throw new \InvalidArgumentException('Nhập tên nhà cung cấp');
            $id = $this->repo->create_supplier([
                'name'    => $name,
                'phone'   => !empty($_POST['phone'])   ? trim($_POST['phone'])   : null,
                'address' => !empty($_POST['address']) ? trim($_POST['address']) : null,
                'note'    => !empty($_POST['note'])    ? trim($_POST['note'])    : null,
            ]);
            $this->json(true, 'Đã thêm nhà cung cấp', ['id'=>$id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }
    public function update(array $vars): void
    {
        try {
            $id   = (int)$vars['id'];
            $name = trim($_POST['name'] ?? '');
            if (!$name) throw new \InvalidArgumentException('Nhập tên nhà cung cấp');
            $this->repo->update_supplier($id, [
                'name'    => $name,
                'phone'   => !empty($_POST['phone'])   ? trim($_POST['phone'])   : null,
                'address' => !empty($_POST['address']) ? trim($_POST['address']) : null,
                'note'    => !empty($_POST['note'])    ? trim($_POST['note'])    : null,
                'status'  => $_POST['status'] ?? 'active',
            ]);
            $this->json(true, 'Đã cập nhật');
        } catch (\InvalidArgumentException $e) {
            $this->json(false, $e->getMessage());
        }
    }
}
