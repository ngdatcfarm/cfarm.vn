<?php
/**
 * app/interfaces/http/controllers/web/settings/settings_controller.php
 *
 * Controller quản lý phần cài đặt:
 * - Hãng cám + mã cám
 * - Danh mục thuốc
 */
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Settings;

use App\Domains\FeedBrand\Entities\FeedBrand;
use App\Domains\FeedBrand\Entities\FeedType;
use App\Domains\FeedBrand\Services\FeedBrandService;
use App\Infrastructure\Persistence\Mysql\Repositories\FeedBrandRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\FeedTypeRepository;
use InvalidArgumentException;
use PDO;

class SettingsController
{
    private FeedBrandRepository $brand_repo;
    private FeedTypeRepository  $type_repo;
    private FeedBrandService   $feed_brand_service;

    public function __construct(private PDO $pdo)
    {
        $this->brand_repo = new FeedBrandRepository($pdo);
        $this->type_repo  = new FeedTypeRepository($pdo);
        $this->feed_brand_service = new FeedBrandService($pdo);
    }

    // GET /settings
    public function index(array $vars): void
    {
        require view_path('settings/settings_menu.php');
    }

    // ----------------------------------------------------------------
    // FEED BRANDS
    // ----------------------------------------------------------------

    // GET /settings/feed-brands
    public function feed_brands(array $vars): void
    {
        $brands = $this->brand_repo->find_all();
        require view_path('settings/feed_brands.php');
    }

    // GET /settings/feed-brands/create
    public function feed_brand_create(array $vars): void
    {
        require view_path('settings/feed_brand_create.php');
    }

    // POST /settings/feed-brands
    public function feed_brand_store(array $vars): void
    {
        try {
            $this->validate_brand($_POST);
            $brand = new FeedBrand(
                name:       trim($_POST['name']),
                kg_per_bag: (float) $_POST['kg_per_bag'],
                note:       !empty($_POST['note']) ? trim($_POST['note']) : null,
            );
            // Sử dụng service để tự động sinh feed_types + inventory_items
            $this->feed_brand_service->createWithAutoGenerate($brand, (float) $_POST['kg_per_bag']);
            require view_path('settings/settings_menu.php');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('settings/feed_brand_create.php');
        }
    }

    // GET /settings/feed-brands/{id}
    public function feed_brand_show(array $vars): void
    {
        $brand = $this->brand_repo->find_with_types((int) $vars['id']);
        if (!$brand) { http_response_code(404); echo '404'; return; }
        require view_path('settings/feed_brand_show.php');
    }

    // GET /settings/feed-brands/{id}/edit
    public function feed_brand_edit(array $vars): void
    {
        $brand = $this->brand_repo->find_by_id((int) $vars['id']);
        if (!$brand) { http_response_code(404); echo '404'; return; }
        require view_path('settings/feed_brand_edit.php');
    }

    // POST /settings/feed-brands/{id}
    public function feed_brand_update(array $vars): void
    {
        $brand = $this->brand_repo->find_by_id((int) $vars['id']);
        try {
            $this->validate_brand($_POST);
            $updated = new FeedBrand(
                name:       trim($_POST['name']),
                kg_per_bag: (float) $_POST['kg_per_bag'],
                status:     $_POST['status'] ?? 'active',
                note:       !empty($_POST['note']) ? trim($_POST['note']) : null,
            );
            $this->brand_repo->update((int) $vars['id'], $updated);
            redirect('/settings/feed-brands/' . $vars['id']);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('settings/feed_brand_edit.php');
        }
    }

    // POST /settings/feed-brands/{id}/types
    public function feed_type_store(array $vars): void
    {
        $brand = $this->brand_repo->find_with_types((int) $vars['id']);
        try {
            if (empty($_POST['code'])) throw new InvalidArgumentException('Thiếu mã cám');
            $type = new FeedType(
                feed_brand_id:   (int) $vars['id'],
                code:            $_POST['code'],
                suggested_stage: $_POST['suggested_stage'] ?? 'all',
                name:            !empty($_POST['name']) ? trim($_POST['name']) : null,
                note:            !empty($_POST['note']) ? trim($_POST['note']) : null,
                price_per_bag:   !empty($_POST['price_per_bag']) ? (int)$_POST['price_per_bag'] : null,
            );
            $this->type_repo->create($type);
            redirect('/settings/feed-brands/' . $vars['id']);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('settings/feed_brand_show.php');
        }
    }


    // POST /settings/feed-types/{id}/update
    public function feed_type_update(array $vars): void
    {
        $type = $this->type_repo->find_by_id((int)$vars['id']);
        if (!$type) { redirect('/settings/feed-brands'); return; }

        $this->pdo->prepare("
            UPDATE feed_types SET
                code            = :code,
                name            = :name,
                suggested_stage = :suggested_stage,
                price_per_bag   = :price_per_bag
            WHERE id = :id
        ")->execute([
            ':code'            => trim($_POST['code']),
            ':name'            => !empty($_POST['name']) ? trim($_POST['name']) : null,
            ':suggested_stage' => $_POST['suggested_stage'] ?? 'all',
            ':price_per_bag'   => !empty($_POST['price_per_bag']) ? (int)$_POST['price_per_bag'] : null,
            ':id'              => (int)$vars['id'],
        ]);
        redirect('/settings/feed-brands/' . $type->feed_brand_id);
    }

    // POST /settings/feed-types/{id}/delete
    public function feed_type_delete(array $vars): void
    {
        $type     = $this->type_repo->find_by_id((int) $vars['id']);
        $brand_id = $type?->feed_brand_id ?? null;
        $this->type_repo->delete((int) $vars['id']);
        redirect('/settings/feed-brands/' . $brand_id);
    }

    // ----------------------------------------------------------------
    // MEDICATIONS
    // ----------------------------------------------------------------

    // GET /settings/medications
    public function medications(array $vars): void
    {
        $stmt        = $this->pdo->query("SELECT * FROM medications ORDER BY name ASC");
        $medications = $stmt->fetchAll();
        require view_path('settings/medications.php');
    }

    // POST /settings/medications
    public function medication_store(array $vars): void
    {
        $stmt        = $this->pdo->query("SELECT * FROM medications ORDER BY name ASC");
        $medications = $stmt->fetchAll();
        try {
            if (empty($_POST['name'])) throw new InvalidArgumentException('Thiếu tên thuốc');
            if (empty($_POST['unit'])) throw new InvalidArgumentException('Thiếu đơn vị');
            $stmt = $this->pdo->prepare("
                INSERT INTO medications (name, unit, category, manufacturer, price_per_unit, recommended_dose, note)
                VALUES (:name, :unit, :category, :manufacturer, :price_per_unit, :recommended_dose, :note)
            ");
            $stmt->execute([
                ':name'             => trim($_POST['name']),
                ':unit'             => trim($_POST['unit']),
                ':category'         => $_POST['category'] ?? 'other',
                ':manufacturer'     => !empty($_POST['manufacturer'])     ? trim($_POST['manufacturer'])     : null,
                ':price_per_unit'   => !empty($_POST['price_per_unit'])   ? (int)$_POST['price_per_unit']   : null,
                ':recommended_dose' => !empty($_POST['recommended_dose']) ? trim($_POST['recommended_dose']) : null,
                ':note'             => !empty($_POST['note'])             ? trim($_POST['note'])             : null,
            ]);
            redirect('/settings/medications');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('settings/medications.php');
        }
    }


    // GET /settings/medications/{id}/edit
    public function medication_edit(array $vars): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM medications WHERE id = :id");
        $stmt->execute([':id' => (int)$vars['id']]);
        $medication = $stmt->fetch();
        if (!$medication) { http_response_code(404); echo '404'; return; }
        $stmt2       = $this->pdo->query("SELECT * FROM medications ORDER BY name ASC");
        $medications = $stmt2->fetchAll();
        require view_path('settings/medications.php');
    }

    // POST /settings/medications/{id}/update
    public function medication_update(array $vars): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM medications WHERE id = :id");
        $stmt->execute([':id' => (int)$vars['id']]);
        $medication  = $stmt->fetch();
        $stmt2       = $this->pdo->query("SELECT * FROM medications ORDER BY name ASC");
        $medications = $stmt2->fetchAll();
        try {
            if (empty($_POST['name'])) throw new \InvalidArgumentException('Thiếu tên thuốc');
            if (empty($_POST['unit'])) throw new \InvalidArgumentException('Thiếu đơn vị');
            $this->pdo->prepare("
                UPDATE medications SET
                    name             = :name,
                    unit             = :unit,
                    category         = :category,
                    manufacturer     = :manufacturer,
                    price_per_unit   = :price_per_unit,
                    recommended_dose = :recommended_dose,
                    note             = :note
                WHERE id = :id
            ")->execute([
                ':name'             => trim($_POST['name']),
                ':unit'             => trim($_POST['unit']),
                ':category'         => $_POST['category'] ?? 'other',
                ':manufacturer'     => !empty($_POST['manufacturer'])     ? trim($_POST['manufacturer'])     : null,
                ':price_per_unit'   => !empty($_POST['price_per_unit'])   ? (int)$_POST['price_per_unit']   : null,
                ':recommended_dose' => !empty($_POST['recommended_dose']) ? trim($_POST['recommended_dose']) : null,
                ':note'             => !empty($_POST['note'])             ? trim($_POST['note'])             : null,
                ':id'               => (int)$vars['id'],
            ]);
            redirect('/settings/medications');
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('settings/medications.php');
        }
    }

    // POST /settings/medications/{id}/delete
    public function medication_delete(array $vars): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM medications WHERE id = :id");
        $stmt->execute([':id' => (int) $vars['id']]);
        redirect('/settings/medications');
    }

    // POST /settings/medications/{id}/toggle
    public function medication_toggle(array $vars): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE medications
            SET status = IF(status='active','inactive','active')
            WHERE id = :id
        ");
        $stmt->execute([':id' => (int) $vars['id']]);
        redirect('/settings/medications');
    }


    // POST /settings/feed-brands/{id}/update-price
    public function feed_brand_update_price(array $vars): void
    {
        $price = !empty($_POST['price_per_bag']) ? (int)$_POST['price_per_bag'] : null;
        $this->pdo->prepare("UPDATE feed_brands SET price_per_bag = :price WHERE id = :id")
            ->execute([':price' => $price, ':id' => (int)$vars['id']]);
        redirect('/settings/feed-brands/' . $vars['id']);
    }

    // POST /settings/feed-brands/sync-inventory
    public function feed_brand_sync_inventory(array $vars): void
    {
        $count = $this->feed_brand_service->syncInventoryFromFeedTypes();
        header('Location: /settings/feed-brands?synced=' . $count);
        exit;
    }

    private function validate_brand(array $input): void
    {
        if (empty($input['name']))       throw new InvalidArgumentException('Thiếu tên hãng cám');
        if (empty($input['kg_per_bag'])) throw new InvalidArgumentException('Thiếu trọng lượng/bao');
        if ((float)$input['kg_per_bag'] <= 0) throw new InvalidArgumentException('Trọng lượng phải lớn hơn 0');
    }

    // GET /settings/notifications
    public function notifications(array $vars): void
    {
        $settings = $this->pdo->query("SELECT * FROM notification_settings ORDER BY level, code")->fetchAll();
        require view_path('settings/notifications.php');
    }

    // POST /settings/notifications/update
    public function notifications_update(array $vars): void
    {
        foreach ($_POST['settings'] ?? [] as $id => $data) {
            $this->pdo->prepare("
                UPDATE notification_settings
                SET enabled      = :enabled,
                    level        = :level,
                    interval_min = :interval_min,
                    send_at_hour = :send_at_hour
                WHERE id = :id
            ")->execute([
                ':id'           => (int)$id,
                ':enabled'      => isset($data['enabled']) ? 1 : 0,
                ':level'        => in_array($data['level'], ['red','orange','blue']) ? $data['level'] : 'blue',
                ':interval_min' => (int)($data['interval_min'] ?? 1440),
                ':send_at_hour' => $data['send_at_hour'] !== '' ? (int)$data['send_at_hour'] : null,
            ]);
        }
        header('Location: /settings/notifications?saved=1');
        exit;
    }



    // VACCINE BRANDS
    public function vaccine_brands(array $vars): void
    {
        $brands = $this->pdo->query("SELECT * FROM vaccine_brands ORDER BY name")->fetchAll();
        require view_path('settings/vaccine_brands.php');
    }

    public function vaccine_brand_store(array $vars): void
    {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $this->pdo->prepare("INSERT INTO vaccine_brands (name) VALUES (:name)")
                ->execute([':name' => $name]);
        }
        header('Location: /settings/vaccine-brands');
        exit;
    }

    public function vaccine_brand_delete(array $vars): void
    {
        $this->pdo->prepare("DELETE FROM vaccine_brands WHERE id=:id")
            ->execute([':id' => (int)$vars['id']]);
        header('Location: /settings/vaccine-brands');
        exit;
    }

    // VACCINE PROGRAMS
    public function vaccine_programs(array $vars): void
    {
        $programs = $this->pdo->query("
            SELECT vp.*, COUNT(vpi.id) AS item_count
            FROM vaccine_programs vp
            LEFT JOIN vaccine_program_items vpi ON vpi.program_id = vp.id
            GROUP BY vp.id ORDER BY vp.name
        ")->fetchAll();
        require view_path('settings/vaccine_programs.php');
    }

    public function vaccine_program_store(array $vars): void
    {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $this->pdo->prepare("INSERT INTO vaccine_programs (name, note) VALUES (:name, :note)")
                ->execute([':name' => $name, ':note' => trim($_POST['note'] ?? '') ?: null]);
        }
        header('Location: /settings/vaccine-programs');
        exit;
    }

    public function vaccine_program_show(array $vars): void
    {
        $program = $this->pdo->prepare("SELECT * FROM vaccine_programs WHERE id=:id");
        $program->execute([':id' => (int)$vars['id']]);
        $program = $program->fetch();
        if (!$program) { http_response_code(404); exit; }

        $items = $this->pdo->prepare("
            SELECT vpi.*, vb.name AS brand_name
            FROM vaccine_program_items vpi
            LEFT JOIN vaccine_brands vb ON vb.id = vpi.vaccine_brand_id
            WHERE vpi.program_id = :id
            ORDER BY vpi.day_age, vpi.sort_order
        ");
        $items->execute([':id' => $program['id']]);
        $items = $items->fetchAll();

        $brands = $this->pdo->query("SELECT * FROM vaccine_brands ORDER BY name")->fetchAll();
        require view_path('settings/vaccine_program_show.php');
    }

    public function vaccine_program_update(array $vars): void
    {
        $this->pdo->prepare("UPDATE vaccine_programs SET name=:name, note=:note WHERE id=:id")
            ->execute([
                ':id'   => (int)$vars['id'],
                ':name' => trim($_POST['name'] ?? ''),
                ':note' => trim($_POST['note'] ?? '') ?: null,
            ]);
        header('Location: /settings/vaccine-programs/' . (int)$vars['id']);
        exit;
    }

    public function vaccine_program_delete(array $vars): void
    {
        $id = (int)$vars['id'];
        $this->pdo->prepare("DELETE FROM vaccine_program_items WHERE program_id=:id")->execute([':id' => $id]);
        $this->pdo->prepare("DELETE FROM vaccine_programs WHERE id=:id")->execute([':id' => $id]);
        header('Location: /settings/vaccine-programs');
        exit;
    }

    public function vaccine_item_store(array $vars): void
    {
        $program_id = (int)$vars['id'];
        $max_sort = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM vaccine_program_items WHERE program_id=:id");
        $max_sort->execute([':id' => $program_id]);
        $sort = (int)$max_sort->fetchColumn();

        $this->pdo->prepare("
            INSERT INTO vaccine_program_items
                (program_id, vaccine_brand_id, vaccine_name, day_age, method, remind_days, sort_order)
            VALUES (:program_id, :brand_id, :name, :day_age, :method, :remind_days, :sort)
        ")->execute([
            ':program_id' => $program_id,
            ':brand_id'   => !empty($_POST['vaccine_brand_id']) ? (int)$_POST['vaccine_brand_id'] : null,
            ':name'       => trim($_POST['vaccine_name'] ?? ''),
            ':day_age'    => (int)($_POST['day_age'] ?? 1),
            ':method'     => $_POST['method'] ?? 'drink',
            ':remind_days'=> (int)($_POST['remind_days'] ?? 1),
            ':sort'       => $sort,
        ]);
        header('Location: /settings/vaccine-programs/' . $program_id);
        exit;
    }

    public function vaccine_item_delete(array $vars): void
    {
        $item = $this->pdo->prepare("SELECT * FROM vaccine_program_items WHERE id=:id");
        $item->execute([':id' => (int)$vars['id']]);
        $item = $item->fetch();
        if ($item) {
            $this->pdo->prepare("DELETE FROM vaccine_program_items WHERE id=:id")
                ->execute([':id' => $item['id']]);
            header('Location: /settings/vaccine-programs/' . $item['program_id']);
        } else {
            header('Location: /settings/vaccine-programs');
        }
        exit;
    }

}