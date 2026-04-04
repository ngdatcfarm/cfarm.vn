<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Sync;

use PDO;

/**
 * SyncController - Bidirectional sync between cloud and local server.
 *
 * Data Flow:
 *   LOCAL → CLOUD: care_feeds, care_deaths, care_medications, care_sales,
 *                 care_litters, care_expenses, care_weights, cycles, devices,
 *                 sensor_data, inventory_transactions, equipment, etc.
 *   CLOUD → LOCAL: reference data (farms, barns, warehouses, products,
 *                 suppliers, feed_brands, feed_types, medications,
 *                 vaccine_programs, device_types, equipment_types, sensor_types)
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

    /**
     * Chuyển đổi datetime MySQL sang ISO 8601 cho JSON response.
     */
    private function to_iso(string $value): string
    {
        return date('c', strtotime($value));
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

        $this->log_sync('receive', $received, empty($errors) ? 'ok' : 'partial',
            empty($errors) ? null : json_encode($errors));

        $this->json(true, ['received' => $received, 'errors' => $errors]);
    }

    // ── 2. GET /api/sync/changes ────────────────────
    // Local pull config data từ cloud (reference data - cloud là master)

    public function changes(array $vars): void
    {
        if (!$this->verify_token()) {
            $this->json(false, ['message' => 'Unauthorized'], 401);
            return;
        }

        $since = $_GET['since'] ?? '2000-01-01T00:00:00';
        $since_dt = date('Y-m-d H:i:s', strtotime($since));

        // Reference data: cloud là master, local pull về
        // Tier 1: Core catalogs
        // Tier 2: Infrastructure
        // Tier 3: Operational config
        $config_tables = [
            // Tier 1: Reference catalogs (Cloud master → Local)
            'farms'                  => 'updated_at',
            'suppliers'              => 'updated_at',
            'products'               => 'updated_at',
            'feed_brands'            => 'updated_at',
            'feed_types'             => 'updated_at',
            'medications'            => 'updated_at',
            'vaccine_programs'       => 'updated_at',
            'vaccine_program_items'  => 'updated_at',
            'device_types'           => 'updated_at',
            'equipment_types'        => 'updated_at',
            'sensor_types'           => 'updated_at',
            // Tier 2: Infrastructure (Cloud master → Local)
            'barns'                  => 'updated_at',
            'warehouses'             => 'updated_at',
            'warehouse_zones'        => 'updated_at',
            'devices'                => 'updated_at',
            'device_channels'        => 'updated_at',
            'equipment'              => 'updated_at',
            'sensors'                => 'updated_at',
            // Tier 3: Operational config
            'cycles'                 => 'updated_at',
            'cycle_splits'           => 'created_at',
            'cycle_feed_programs'    => 'updated_at',
            'cycle_feed_program_items'=> 'updated_at',
            'cycle_feed_stages'      => 'updated_at',
            'curtain_configs'        => 'updated_at',
            'care_litters'           => 'updated_at',
            'care_expenses'          => 'updated_at',
            'vaccine_schedules'     => 'updated_at',
            'feed_trough_checks'     => 'updated_at',
            'weight_reminders'       => 'updated_at',
            // Legacy
            'notification_rules'     => 'updated_at',
            'firmwares'              => 'created_at',
        ];

        $items = [];
        foreach ($config_tables as $table => $time_col) {
            try {
                if (!$this->table_exists($table)) continue;
                if (!$this->column_exists($table, $time_col)) {
                    $time_col = 'created_at';
                    if (!$this->column_exists($table, $time_col)) continue;
                }

                $stmt = $this->pdo->prepare(
                    "SELECT * FROM `{$table}` WHERE `{$time_col}` > :since ORDER BY `{$time_col}` ASC"
                );
                $stmt->execute([':since' => $since_dt]);
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $row = $this->convert_datetimes($row);
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
    // Nhận trạng thái thiết bị từ local - TỰ TẠO device nếu chưa có

    public function device_states(array $vars): void
    {
        if (!$this->verify_token()) {
            $this->json(false, ['message' => 'Unauthorized'], 401);
            return;
        }

        $body = $this->get_json_body();
        $items = $body['items'] ?? [];
        $received = 0;
        $created = 0;

        foreach ($items as $item) {
            try {
                $device_code = $item['device_code'] ?? null;
                if (!$device_code) continue;

                $check = $this->pdo->prepare("SELECT id FROM devices WHERE device_code = ?");
                $check->execute([$device_code]);
                $exists = $check->fetch() !== false;

                if (!$exists) {
                    $mqtt_topic = 'cfarm/' . $device_code;
                    $stmt = $this->pdo->prepare("
                        INSERT INTO devices (device_code, name, device_type_id, mqtt_topic, is_online, last_seen, ip_address, firmware_version, created_at, updated_at)
                        VALUES (:device_code, :name, :type_id, :mqtt_topic, :is_online, :last_seen, :ip_address, :firmware_version, NOW(), NOW())
                    ");
                    $stmt->execute([
                        ':device_code'      => $device_code,
                        ':name'            => $item['name'] ?? $device_code,
                        ':type_id'         => $item['device_type_id'] ?? 1,
                        ':mqtt_topic'      => $item['mqtt_topic'] ?? $mqtt_topic,
                        ':is_online'       => $item['is_online'] ? 1 : 0,
                        ':last_seen'       => $item['last_seen'] ? date('Y-m-d H:i:s', strtotime($item['last_seen'])) : null,
                        ':ip_address'      => $item['ip_address'] ?? null,
                        ':firmware_version'=> $item['firmware_version'] ?? null,
                    ]);
                    $created++;
                    $received++;
                    error_log("[Sync] Auto-created device {$device_code} on cloud");
                } else {
                    $stmt = $this->pdo->prepare("
                        UPDATE devices SET
                            is_online = :is_online,
                            last_seen = :last_seen,
                            ip_address = :ip_address,
                            firmware_version = :firmware_version,
                            wifi_rssi = :wifi_rssi,
                            uptime_seconds = :uptime_seconds,
                            updated_at = NOW()
                        WHERE device_code = :device_code
                    ");
                    $stmt->execute([
                        ':device_code'      => $device_code,
                        ':is_online'        => $item['is_online'] ? 1 : 0,
                        ':last_seen'        => $item['last_seen'] ? date('Y-m-d H:i:s', strtotime($item['last_seen'])) : null,
                        ':ip_address'       => $item['ip_address'] ?? null,
                        ':firmware_version' => $item['firmware_version'] ?? null,
                        ':wifi_rssi'        => $item['wifi_rssi'] ?? null,
                        ':uptime_seconds'   => $item['uptime_seconds'] ?? null,
                    ]);
                    if ($stmt->rowCount() > 0) $received++;
                }

                if (!empty($item['channels']) && is_array($item['channels'])) {
                    $this->sync_device_channels($device_code, $item['channels']);
                }
            } catch (\Throwable $e) {
                error_log("Device state error: " . $e->getMessage());
            }
        }

        $this->json(true, ['received' => $received, 'created' => $created]);
    }

    private function sync_device_channels(string $device_code, array $channels): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM devices WHERE device_code = ?");
            $stmt->execute([$device_code]);
            $row = $stmt->fetch();
            if (!$row) return;
            $device_id = $row['id'];

            foreach ($channels as $ch) {
                $channel_number = $ch['channel_number'] ?? null;
                if (!$channel_number) continue;

                $stmt = $this->pdo->prepare("
                    INSERT INTO device_channels (device_id, channel_number, name, channel_type, gpio_pin, is_active, sort_order)
                    VALUES (:device_id, :channel_number, :name, :channel_type, :gpio_pin, :is_active, :sort_order)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        channel_type = VALUES(channel_type),
                        gpio_pin = VALUES(gpio_pin),
                        is_active = VALUES(is_active),
                        sort_order = VALUES(sort_order)
                ");
                $stmt->execute([
                    ':device_id'     => $device_id,
                    ':channel_number'=> $channel_number,
                    ':name'         => $ch['name'] ?? 'Kênh ' . $channel_number,
                    ':channel_type'  => $ch['channel_type'] ?? 'other',
                    ':gpio_pin'      => $ch['gpio_pin'] ?? null,
                    ':is_active'    => $ch['is_active'] ?? 1,
                    ':sort_order'    => $channel_number,
                ]);
            }
        } catch (\Throwable $e) {
            error_log("Sync channels error: " . $e->getMessage());
        }
    }

    // ── 5. POST /api/sync/command ───────────────────
    // Cloud gửi lệnh IoT xuống local

    public function send_command(array $vars): void
    {
        $body = $this->get_json_body();
        $local_ip = $body['local_ip'] ?? null;
        $command = $body['command'] ?? [];

        if (!$local_ip || !$command) {
            $this->json(false, ['message' => 'Missing local_ip or command'], 400);
            return;
        }

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

        $stmt = $this->pdo->query("SELECT `key`, value FROM sync_config");
        $config = [];
        while ($row = $stmt->fetch()) {
            $config[$row['key']] = $row['value'];
        }

        $logs = $this->pdo->query(
            "SELECT * FROM sync_log ORDER BY created_at DESC LIMIT 10"
        )->fetchAll();

        $queue_count = 0;
        try {
            $queue_count = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM sync_queue WHERE synced = 0"
            )->fetchColumn();
        } catch (\Throwable $e) {
            // sync_queue might not exist in old cloud DB
        }

        $this->json(true, [
            'enabled'     => ($config['enabled'] ?? 'false') === 'true',
            'local_ip'    => $config['local_ip'] ?? null,
            'queue_count' => $queue_count,
            'recent_logs' => $logs,
        ]);
    }

    // ── Helper: Check table/column existence ─────────

    private function table_exists(string $table): bool
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            $check = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
            $cache[$table] = $check->rowCount() > 0;
        }
        return $cache[$table];
    }

    private function column_exists(string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";
        if (!isset($cache[$key])) {
            $check = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            $cache[$key] = $check->rowCount() > 0;
        }
        return $cache[$key];
    }

    private function convert_datetimes(array $row): array
    {
        foreach ($row as $k => $v) {
            if ($v instanceof \DateTime || (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) && strlen((string)$v) > 10)) {
                $row[$k] = $this->to_iso((string)$v);
            }
        }
        return $row;
    }

    // ── Apply a single change from local to cloud DB ─

    private function apply_change(array $item): void
    {
        $table   = $item['table'] ?? '';
        $action  = $item['action'] ?? '';
        $payload = $item['payload'] ?? [];

        if (empty($table) || empty($payload)) {
            throw new \InvalidArgumentException("Missing table or payload");
        }

        // Whitelist: tất cả bảng local được phép push lên cloud
        // CLOUD ← LOCAL push tables
        $allowed_tables = [
            // Farm infrastructure
            'farms', 'barns',
            'warehouses', 'warehouse_zones',
            'devices', 'device_channels', 'device_states', 'device_state_log',
            'device_commands', 'device_telemetry', 'device_alerts',
            'device_config_versions', 'device_firmwares',
            'equipment', 'equipment_parts', 'equipment_readings',
            'equipment_performance', 'equipment_assignment_log', 'equipment_command_log',
            'sensors', 'sensor_types',
            // Operations
            'cycles', 'cycle_splits', 'cycle_daily_snapshots',
            'cycle_feed_programs', 'cycle_feed_program_items', 'cycle_feed_stages',
            'care_feeds', 'care_deaths', 'care_medications', 'care_sales',
            'care_litters', 'care_expenses', 'care_weights',
            'weight_samples', 'feed_trough_checks', 'weight_reminders',
            'health_notes', 'vaccine_schedules',
            // Inventory & Purchasing
            'inventory', 'inventory_transactions', 'inventory_alerts',
            'inventory_snapshots', 'stock_valuation',
            'purchase_orders', 'purchase_order_items',
            // Reference data (bidirectional - local can also push)
            'products', 'suppliers',
            'feed_brands', 'feed_types', 'medications',
            'vaccine_programs', 'vaccine_program_items',
            'device_types', 'equipment_types', 'sensor_types',
            // Utilities
            'curtain_configs', 'notification_rules', 'firmwares',
        ];

        if (!in_array($table, $allowed_tables)) {
            throw new \InvalidArgumentException("Table not allowed: {$table}");
        }

        if (!$this->table_exists($table)) {
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

        $id = $payload['id'] ?? null;
        if (!$id) {
            throw new \InvalidArgumentException("Missing id in payload");
        }

        // Lấy danh sách cột thực tế của bảng (để filter payload)
        $col_stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        $existing_cols = array_column($col_stmt->fetchAll(), 'Field');

        $filtered = [];
        foreach ($payload as $key => $value) {
            if (!in_array($key, $existing_cols)) continue;

            // Convert ISO 8601 datetime strings to MySQL format
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $value)) {
                $value = preg_replace('/[T-Z]/', ' ', $value);
                $value = trim(substr($value, 0, 19));
            }

            $filtered[$key] = $value;
        }

        if (empty($filtered)) return;

        // Build UPSERT query
        $columns    = array_keys($filtered);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);
        $updates    = array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $columns);
        $col_list   = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $ph_list    = implode(', ', $placeholders);
        $upd_list   = implode(', ', $updates);

        $sql = "INSERT INTO `{$table}` ({$col_list}) VALUES ({$ph_list})
                ON DUPLICATE KEY UPDATE {$upd_list}";

        $stmt = $this->pdo->prepare($sql);
        $params = [];
        foreach ($filtered as $key => $value) {
            $params[":{$key}"] = $value;
        }
        $stmt->execute($params);
    }

    // ── Sync Log ───────────────────────────────────

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
