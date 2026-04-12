<?php
/**
 * app/bootstrap.php
 *
 * Khởi động ứng dụng: load helpers, kết nối database,
 * load global data (active cycles cho FAB), dispatch router.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use App\Domains\Cycle\Entities\Cycle;

// --- load helpers ---
require_once ROOT_PATH . '/app/shared/utils/helpers.php';

// --- load new controllers (classmap not rebuilt after git pull) ---
require_once ROOT_PATH . '/app/interfaces/http/controllers/web/IoT/bat_controller.php';
require_once ROOT_PATH . '/app/interfaces/http/controllers/web/IoT/bat_control_controller.php';

// --- load database ---
$pdo = require_once ROOT_PATH . '/app/shared/database/mysql.php';

// --- global data cho layout (FAB cycle list) ---
$active_cycles_for_fab = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, b.name AS barn_name, b.number AS barn_number
        FROM cycles c
        JOIN barns b ON c.barn_id = b.id
        WHERE c.status = 'active'
        ORDER BY b.number ASC
    ");
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $active_cycles_for_fab[] = new Cycle(
            barn_id:          (int)   $row['barn_id'],
            code:                     $row['code'],
            initial_quantity: (int)   $row['initial_quantity'],
            male_quantity:    (int)   $row['male_quantity'],
            female_quantity:  (int)   $row['female_quantity'],
            purchase_price:   (float) $row['purchase_price'],
            current_quantity: (int)   $row['current_quantity'],
            start_date:               $row['start_date'],
            breed:                    $row['breed'],
            stage:                    $row['stage'],
            status:                   $row['status'],
            id:               (int)   $row['id'],
        );
    }
} catch (\Throwable $e) {
    error_log('FAB cycle load error: ' . $e->getMessage());
}

// --- load router ---
$dispatcher = FastRoute\simpleDispatcher(function (RouteCollector $r) {
    require_once ROOT_PATH . '/app/router.php';
});

// --- auth check ---
require_once ROOT_PATH . '/app/shared/auth/auth.php';
$public_routes = ['/login', '/logout'];
// API endpoints dùng Bearer token hoặc session auth
$api_prefixes = ['/api/sync/', '/api/iot/'];
$current_path  = strtok($_SERVER['REQUEST_URI'], '?');
$is_api_route = false;
foreach ($api_prefixes as $prefix) {
    if (str_starts_with($current_path, $prefix)) { $is_api_route = true; break; }
}
if (!$is_api_route && !in_array($current_path, $public_routes) && !auth_check($pdo)) {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /login'); exit;
}

// --- dispatch request ---
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];

if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($method, $uri);

switch ($routeInfo[0]) {

    case Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo '404 — không tìm thấy trang';
        break;

    case Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo '405 — phương thức không được phép';
        break;

    case Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars    = $routeInfo[2];
        [$controllerClass, $method] = $handler;
        $controller = new $controllerClass($pdo);
        $controller->$method($vars);
        break;
}
