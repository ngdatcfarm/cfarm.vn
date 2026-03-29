<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Sync;

use PDO;

/**
 * SyncController - Nhận/gửi dữ liệu đồng bộ giữa cloud và local server.
 *
 * Endpoints:
 *   POST /api/sync/receive       - Nhận record changes từ local
 *   GET  /api/sync/changes       - Trả về thay đổi cho local pull
 *   POST /api/sync/sensor-data   - Nhận sensor summary từ local
 *   POST /api/sync/device-states - Nhận trạng thái thiết bị từ local
 *   POST /api/sync/command       - Gửi lệnh IoT xuống local
 *   GET  /api/sync/status        - Trạng thái sync
 */
class SyncController
{
    public function __construct(private PDO $pdo) {}

    // ── Helper ──────────────────────────────────────

    private function json(bool $ok, array $data = [], int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    }

    private function get_json_body(): array
    {
        $body = file_get_contents('php://input');
        return json_decode($body ?: '{}', true) ?: [];
    }

    /**
     * Xác thực Bearer token từ local server.
     * Token lưu trong bảng sync_config với key = 'local_token'.
     */
    private function verify_token(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }
        $token = substr($header, 7);
        if (empty($token)) return false;

        $stmt = $this->pdo->prepare(
            "SELECT value FROM sync_config WHERE `key` = 'local_token'"
        );
        $stmt->execute();
        $row = $stmt->fetch();

        return $row && !empty($row['value']) && hash_equals($row['value'], $token);
    }

    // ── 1. POST /api/sync/receive ───────────────────
    // Local đẩy data lên cloud (care records, cycles, devices...)

    public function receive(array $vars): void
    {
        if (!$this->verify_token()) {
            $this->json(false, ['message' => 'Unauthorized'], 401);
            return;
        }

        $body = $this->get_json_body();
        $items = $body['items'] ?? [];
        $received = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $this->apply_change($item);
                $received++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'table' => $item['table'] ?? '?',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Ghi log
        $this->log_sync('receive', $received, empty($errors) ? 'ok' : 'partial',
            empty($errors) ? null : json_encode($errors));

        $this->json(true, ['received' => $received, 'errors' => $errors]);
    }

    // ── 2. GET /api/sync/changes ────────────────────
    // Local pull config data từ cloud

    public function changes(array $vars): void
    {
        if (!$this->verify_token()) {
            $this->json(false, ['message' => 'Unauthorized'], 401);
            return;
        }

        $since = $_GET['since'] ?? '2000-01-01T00:00:00';
        // Chuyển ISO format sang MySQL datetime
        $since_dt = date('Y-m-d H:i:s', strtotime($since));

        // Các bảng config mà cloud là master
        $config_tables = [
            'feed_brands'          => 'updated_at',
            'feed_types'           => 'updated_at',
            'medications'          => 'updated_at',
            'suppliers'            => 'updated_at',
            'vaccine_programs'     => 'updated_at',
            'vaccine_program_items'=> 'updated_at',
            'notification_rules'   => 'updated_at',
        ];

        $items = [];
        foreach ($config_tables as $table => $time_col) {
            try {
                // Kiểm tra bảng có tồn tại không
                $check = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($check->rowCount() === 0) continue;

                // Kiểm tra cột thời gian có tồn tại không
                $col_check = $this->pdo->query(
                    "SHOW COLUMNS FROM `{$table}` LIKE '{$time_col}'"
                );
                if ($col_check->rowCount() === 0) {
                    // Dùng created_at nếu không có updated_at
                    $time_col = 'created_at';
                    $col_check2 = $this->pdo->query(
                        "SHOW COLUMNS FROM `{$table}` LIKE 'created_at'"
                    );
                    if ($col_check2->rowCount() === 0) continue;
                }

                $stmt = $this->pdo->prepare(
                    "SELECT * FROM `{$table}` WHERE `{$time_col}` > :since ORDER BY `{$time_col}` ASC"
                );
                $stmt->execute([':since' => $since_dt]);
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    // Chuyển datetime → ISO
                    foreach ($row as $k => $v) {
                        if ($v instanceof \DateTime || (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) && strlen($v) > 10)) {
                            $row[$k] = date('c', strtotime($v));
                        }
                    }
                    $items[] = [
                        'table'   => $table,
                        'action'  => 'update',
                        'payload' => $row,
                    ];
                }
            } catch (\Throwable $e) {
                error_log("Sync changes error ({$table}): " . $e->getMessage());
            }
        }

        $this->log_sync('changes', count($items), 'ok');

        $this->json(true, [
            'items'     => $items,
            'timestamp' => date('c'),
        ]);
    }

    // ── 3. POST /api/sync/sensor-data ───────────────
    // Nhận sensor summary từ local (hourly averages)

    public function sensor_data(array $vars): void
    {
        if (!$this->verify_token()) {
            $this->json(false, ['message' => 'Unauthorized'], 401);
            return;
        }

        $body = $this->get_json_body();
        $items = $body['items'] ?? [];
        $received = 0;

        foreach ($items as $item) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO sensor_data_summary
                        (device_code, sensor_type, hour, avg_value, min_value, max_value, sample_count)
                    VALUES
                        (:device_code, :sensor_type, :hour, :avg_value, :min_value, :max_value, :sample_count)
                    ON DUPLICATE KEY UPDATE
                        avg_value = VALUES(avg_value),
                        min_value = VALUES(min_value),
                        max_value = VALUES(max_value),
                        sample_count = VALUES(sample_count)
                ");
                $stmt->execute([
                    ':device_code'  => $item['device_code'],
                    ':sensor_type'  => $item['sensor_type'],
                    ':hour'         => date('Y-m-d H:i:s', strtotime($item['hour'])),
                    ':avg_value'    => $item['avg_value'],
                    ':min_value'    => $item['min_value'],
                    ':max_value'    => $item['max_value'],
                    ':sample_count' => $item['sample_count'],
                ]);
                $received++;
            } catch (\Throwable $e) {
                error_log("Sensor data error: " . $e->getMessage());
            }
        }

        $this->json(true, ['received' => $received]);
    }

    // ── 4. POST /api/sync/device-states ─────────────
    // Nhận trạng thái online/offline thiết bị

    public function device_states(array $vars): void
    {
        if (!$this->verify_token()) {
            $this->json(false, ['message' => 'Unauthorized'], 401);
            return;
        }

        $body = $this->get_json_body();
        $items = $body['items'] ?? [];
        $received = 0;

        foreach ($items as $item) {
            try {
                $stmt = $this->pdo->prepare("
                    UPDATE devices SET
                        is_online = :is_online,
                        last_seen = :last_seen,
                        ip_address = :ip_address,
                        firmware_version = :firmware_version
                    WHERE device_code = :device_code
                ");
                $stmt->execute([
                    ':device_code'      => $item['device_code'],
                    ':is_online'        => $item['is_online'] ? 1 : 0,
                    ':last_seen'        => $item['last_seen'] ? date('Y-m-d H:i:s', strtotime($item['last_seen'])) : null,
                    ':ip_address'       => $item['ip_address'] ?? null,
                    ':firmware_version' => $item['firmware_version'] ?? null,
                ]);
                if ($stmt->rowCount() > 0) $received++;
            } catch (\Throwable $e) {
                error_log("Device state error: " . $e->getMessage());
            }
        }

        $this->json(true, ['received' => $received]);
    }

    // ── 5. POST /api/sync/command ───────────────────
    // Cloud gửi lệnh IoT xuống local

    public function send_command(array $vars): void
    {
        // Endpoint này cloud gọi local, không phải ngược lại
        // Lưu ở đây để tham khảo cách gửi
        $body = $this->get_json_body();
        $local_ip = $body['local_ip'] ?? null;
        $command = $body['command'] ?? [];

        if (!$local_ip || !$command) {
            $this->json(false, ['message' => 'Missing local_ip or command'], 400);
            return;
        }

        // Lấy local_token để gọi local server
        $stmt = $this->pdo->prepare("SELECT value FROM sync_config WHERE `key` = 'api_token'");
        $stmt->execute();
        $row = $stmt->fetch();
        $token = $row['value'] ?? '';

        try {
            $ch = curl_init("http://{$local_ip}:8000/api/sync/command");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($command),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$token}",
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->json(false, ['message' => "Connection failed: {$error}"], 502);
                return;
            }

            $result = json_decode($response, true) ?: [];
            $this->json($http_code === 200, $result, $http_code ?: 500);

        } catch (\Throwable $e) {
            $this->json(false, ['message' => $e->getMessage()], 500);
        }
    }

    // ── 6. GET /api/sync/status ─────────────────────

    public function status(array $vars): void
    {
        if (!$this->verify_token()) {
            $this->json(false, ['message' => 'Unauthorized'], 401);
            return;
        }

        // Lấy config
        $stmt = $this->pdo->query("SELECT `key`, value FROM sync_config");
        $config = [];
        while ($row = $stmt->fetch()) {
            $config[$row['key']] = $row['value'];
        }

        // Lấy sync logs gần nhất
        $logs = $this->pdo->query(
            "SELECT * FROM sync_log ORDER BY created_at DESC LIMIT 10"
        )->fetchAll();

        // Đếm queue
        $queue_count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM sync_queue WHERE synced = 0"
        )->fetchColumn();

        $this->json(true, [
            'enabled'     => ($config['enabled'] ?? 'false') === 'true',
            'local_ip'    => $config['local_ip'] ?? null,
            'queue_count' => $queue_count,
            'recent_logs' => $logs,
        ]);
    }

    // ── Apply a single change to local DB ───────────

    private function apply_change(array $item): void
    {
        $table  = $item['table'] ?? '';
        $action = $item['action'] ?? '';
        $payload = $item['payload'] ?? [];

        if (empty($table) || empty($payload)) {
            throw new \InvalidArgumentException("Missing table or payload");
        }

        // Whitelist các bảng được phép sync
        $allowed_tables = [
            'barns', 'cycles',
            'feed_records', 'death_records', 'medication_records',
            'weight_sessions', 'weight_details', 'sale_records',
            'health_notes', 'vaccine_schedules',
            'devices', 'alerts',
            'feed_brands', 'feed_types', 'medications', 'suppliers',
            'vaccine_programs', 'vaccine_program_items',
            'notification_rules',
        ];

        if (!in_array($table, $allowed_tables)) {
            throw new \InvalidArgumentException("Table not allowed: {$table}");
        }

        // Kiểm tra bảng tồn tại
        $check = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($check->rowCount() === 0) {
            throw new \InvalidArgumentException("Table does not exist: {$table}");
        }

        if ($action === 'delete') {
            $id = $payload['id'] ?? null;
            if ($id) {
                $stmt = $this->pdo->prepare("DELETE FROM `{$table}` WHERE id = :id");
                $stmt->execute([':id' => $id]);
            }
            return;
        }

        // INSERT or UPDATE (upsert)
        $id = $payload['id'] ?? null;
        if (!$id) {
            throw new \InvalidArgumentException("Missing id in payload");
        }

        // Lấy danh sách cột thực tế của bảng
        $col_stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        $existing_cols = array_column($col_stmt->fetchAll(), 'Field');

        // Lọc payload chỉ giữ cột tồn tại
        $filtered = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, $existing_cols)) {
                $filtered[$key] = $value;
            }
        }

        if (empty($filtered)) return;

        // Build UPSERT query
        $columns = array_keys($filtered);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);
        $updates = array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $columns);
        $col_list = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $ph_list  = implode(', ', $placeholders);
        $upd_list = implode(', ', $updates);

        $sql = "INSERT INTO `{$table}` ({$col_list}) VALUES ({$ph_list})
                ON DUPLICATE KEY UPDATE {$upd_list}";

        $stmt = $this->pdo->prepare($sql);
        $params = [];
        foreach ($filtered as $key => $value) {
            $params[":{$key}"] = $value;
        }
        $stmt->execute($params);
    }

    // ── Sync Log ────────────────────────────────────

    private function log_sync(string $direction, int $count, string $status, ?string $error = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sync_log (direction, items_count, status, error_msg, created_at)
                 VALUES (:dir, :cnt, :st, :err, NOW())"
            );
            $stmt->execute([
                ':dir' => $direction,
                ':cnt' => $count,
                ':st'  => $status,
                ':err' => $error,
            ]);
        } catch (\Throwable $e) {
            error_log("Sync log error: " . $e->getMessage());
        }
    }
}
