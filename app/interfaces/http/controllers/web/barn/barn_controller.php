<?php
/**
 * app/interfaces/http/controllers/web/barn/barn_controller.php
 *
 * Controller xử lý các request HTTP liên quan đến barn.
 * Nhận request, gọi use case tương ứng, trả về view hoặc redirect.
 */

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Web\Barn;

use App\Domains\Barn\Usecases\CreateBarnUsecase;
use App\Domains\Barn\Usecases\UpdateBarnUsecase;
use App\Domains\Barn\Usecases\DeleteBarnUsecase;
use App\Domains\Barn\Usecases\ListBarnUsecase;
use App\Infrastructure\Persistence\Mysql\Repositories\BarnRepository;
use App\Infrastructure\Persistence\Mysql\Repositories\CycleRepository;
use InvalidArgumentException;
use PDO;

class BarnController
{
    private BarnRepository  $barn_repository;
    private CycleRepository $cycle_repository;

    public function __construct(private PDO $pdo)
    {
        $this->barn_repository  = new BarnRepository($pdo);
        $this->cycle_repository = new CycleRepository($pdo);
    }

    // GET /barns — danh sách tất cả barn
    public function index(array $vars): void
    {
        $usecase = new ListBarnUsecase($this->barn_repository);
        $barns   = $usecase->execute();

        require view_path('barn/barn_list.php');
    }

    // GET /barns/create — form tạo barn mới
    public function create(array $vars): void
    {
        require view_path('barn/barn_create.php');
    }

    // POST /barns — lưu barn mới
    public function store(array $vars): void
    {
        try {
            $usecase = new CreateBarnUsecase($this->barn_repository);
            $usecase->execute($_POST);
            redirect('/barns');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            require view_path('barn/barn_create.php');
        }
    }

    // GET /barns/{id} — chi tiết barn + cycle active + lịch sử
    public function show(array $vars): void
    {
        $barn = $this->barn_repository->find_by_id((int) $vars['id']);

        if (!$barn) {
            http_response_code(404);
            echo '404 — không tìm thấy chuồng';
            return;
        }

        $active_cycle = $this->cycle_repository->find_active_by_barn($barn->id);
        $all_cycles   = $this->cycle_repository->find_by_barn($barn->id);
        $past_cycles  = array_filter($all_cycles, fn($c) => $c->status === 'closed');

        $barn_devices = $this->pdo->prepare("SELECT d.*, dt.name as type_name, dt.device_class FROM devices d LEFT JOIN device_types dt ON dt.id = d.device_type_id WHERE d.barn_id = :id ORDER BY d.device_code");
        $barn_devices->execute([':id' => $barn->id]);
        $barn_devices = $barn_devices->fetchAll(\PDO::FETCH_OBJ);

        $barn_curtains = $this->pdo->prepare("SELECT cc.*, uc.channel_number as up_ch, dc.channel_number as down_ch, ud.device_code as up_device FROM curtain_configs cc JOIN device_channels uc ON uc.id = cc.up_channel_id JOIN device_channels dc ON dc.id = cc.down_channel_id JOIN devices ud ON ud.id = uc.device_id WHERE cc.barn_id = :id");
        $barn_curtains->execute([':id' => $barn->id]);
        $barn_curtains = $barn_curtains->fetchAll(\PDO::FETCH_OBJ);

        require view_path('barn/barn_show.php');
    }

    // GET /barns/{id}/edit — form chỉnh sửa barn
    public function edit(array $vars): void
    {
        $barn = $this->barn_repository->find_by_id((int) $vars['id']);

        if (!$barn) {
            http_response_code(404);
            echo '404 — không tìm thấy chuồng';
            return;
        }

        require view_path('barn/barn_edit.php');
    }

    // POST /barns/{id} — cập nhật barn
    public function update(array $vars): void
    {
        try {
            $usecase = new UpdateBarnUsecase($this->barn_repository);
            $usecase->execute((int) $vars['id'], $_POST);
            redirect('/barns');
        } catch (InvalidArgumentException $e) {
            $barn  = $this->barn_repository->find_by_id((int) $vars['id']);
            $error = $e->getMessage();
            require view_path('barn/barn_edit.php');
        }
    }

    // POST /barns/{id}/delete — xóa barn
    public function destroy(array $vars): void
    {
        try {
            $usecase = new DeleteBarnUsecase($this->barn_repository);
            $usecase->execute((int) $vars['id']);
        } catch (InvalidArgumentException $e) {
            // barn không tồn tại — bỏ qua
        }

        redirect('/barns');
    }
}
