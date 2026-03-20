<?php
/**
 * MQTT Listener - Nhận message từ ESP32 devices
 *
 * Chạy như daemon, subscribe topic cfarm/# và xử lý:
 * - heartbeat/status: cập nhật trạng thái online/offline
 * - LWT (Last Will): đánh dấu device offline khi mất kết nối
 * - ping response: log phản hồi ping
 *
 * Tự động reconnect khi mất kết nối.
 *
 * Chạy: php app/domains/iot/mqtt_listener.php
 * Hoặc: systemctl start cfarm-mqtt-listener
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../shared/database/mysql.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// ============== CONFIG ==============
const MQTT_HOST           = '103.166.183.215';
const MQTT_PORT           = 1883;
const MQTT_USER           = 'cfarm_server';
const MQTT_PASS           = 'Abc@@123';
const MQTT_TOPIC          = 'cfarm/#';
const OFFLINE_TIMEOUT     = 90;   // seconds - đánh dấu offline sau bao lâu không heartbeat
const CLEANUP_INTERVAL    = 30;   // seconds - kiểm tra cleanup mỗi 30s
const PING_INTERVAL       = 60;   // seconds - gửi ping mỗi 60s
const PING_TIMEOUT        = 30;   // seconds - nếu ping không pong sau 30s → tăng fail count
const MAX_RECONNECT_DELAY = 60;   // seconds - max delay giữa các lần reconnect

// ============== MAIN ==============

$lastCleanup = 0;
$lastPing    = 0;

function logMsg(string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$msg}\n";
}

/**
 * Xử lý message nhận từ MQTT
 */
function processMessage(PDO $pdo, string $topic, string $message): void
{
    $parts = explode('/', $topic);
    // Expect: cfarm/{device_code}/{msg_type}
    if (count($parts) < 3) return;

    $msgType = end($parts);

    // Xây dựng mqtt_topic = tất cả phần trước msg_type
    $baseParts = array_slice($parts, 0, count($parts) - 1);
    $mqttTopic = implode('/', $baseParts);

    // Parse payload để lấy device_code - BẮT BUỘC phải có
    $data = json_decode($message, true);
    $payloadDeviceCode = $data['device'] ?? null;

    // Bỏ qua message không có device_code (retained rỗng, message lỗi, v.v.)
    if (!$payloadDeviceCode) return;

    // Tìm device CHỈ theo device_code (chính xác 1:1, không dùng mqtt_topic)
    // Tránh device cũ cùng mqtt_topic ảnh hưởng device mới
    $stmt = $pdo->prepare("SELECT id, device_code, name, barn_id FROM devices WHERE device_code = ?");
    $stmt->execute([$payloadDeviceCode]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) return;

    $deviceId = (int)$device['id'];

    switch ($msgType) {
        case 'heartbeat':
        case 'status':
            handleHeartbeat($pdo, $deviceId, $message);
            break;

        case 'lwt':
            handleLwt($pdo, $deviceId, $device['name']);
            break;

        case 'pong':
            handlePong($pdo, $deviceId);
            break;

        case 'env':
            handleEnvData($pdo, $deviceId, $device, $message);
            break;

        default:
            // Log unknown message types for debugging
            break;
    }
}

/**
 * Xử lý heartbeat/status - device đang online
 */
function handleHeartbeat(PDO $pdo, int $deviceId, string $message): void
{
    $data = json_decode($message, true);
    if (!$data) return;

    $status = $data['status'] ?? 'online';

    if ($status === 'offline') {
        // LWT message qua heartbeat topic
        $pdo->prepare("UPDATE devices SET is_online = 0 WHERE id = ?")->execute([$deviceId]);
        return;
    }

    $pdo->prepare("
        UPDATE devices SET
            is_online           = 1,
            last_heartbeat_at   = NOW(),
            wifi_rssi           = :rssi,
            ip_address          = :ip,
            uptime_seconds      = :uptime,
            free_heap_bytes     = :heap,
            ping_fail_count     = 0
        WHERE id = :id
    ")->execute([
        ':rssi'   => $data['wifi_rssi'] ?? $data['rssi'] ?? null,
        ':ip'     => $data['ip'] ?? null,
        ':uptime' => $data['uptime'] ?? null,
        ':heap'   => $data['heap'] ?? $data['free_heap'] ?? null,
        ':id'     => $deviceId,
    ]);
}

/**
 * Xử lý LWT (Last Will and Testament) - device mất kết nối đột ngột
 */
function handleLwt(PDO $pdo, int $deviceId, string $deviceName): void
{
    logMsg("LWT received for [{$deviceName}] - marking offline");
    $pdo->prepare("UPDATE devices SET is_online = 0 WHERE id = ?")->execute([$deviceId]);
}

/**
 * Xử lý pong - device phản hồi ping
 */
function handlePong(PDO $pdo, int $deviceId): void
{
    $pdo->prepare("
        UPDATE devices SET
            is_online = 1,
            last_heartbeat_at = NOW(),
            ping_fail_count = 0
        WHERE id = ?
    ")->execute([$deviceId]);
}

/**
 * Xử lý dữ liệu cảm biến môi trường từ ESP32 ENV Sensor
 * Lưu vào bảng env_readings (khớp với EnvController)
 */
function handleEnvData(PDO $pdo, int $deviceId, array $device, string $message): void
{
    $data = json_decode($message, true);
    if (!$data) {
        logMsg("ENV [{$device['device_code']}] JSON parse failed: " . substr($message, 0, 200));
        return;
    }

    // Bỏ qua message status (ví dụ OTA updating)
    if (isset($data['status'])) {
        logMsg("ENV [{$device['device_code']}] skip status msg: {$data['status']}");
        return;
    }

    // Lấy barn_id từ device
    $barnId = $device['barn_id'] ?? null;

    // Lấy cycle_id active + tính day_age
    $cycleId = null;
    $dayAge = null;
    if ($barnId) {
        $stmt = $pdo->prepare("SELECT id, start_date FROM cycles WHERE barn_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$barnId]);
        $row = $stmt->fetch();
        if ($row) {
            $cycleId = (int)$row['id'];
            $dayAge = (int)((time() - strtotime($row['start_date'])) / 86400) + 1;
        }
    }

    // Tính heat index (chỉ số nhiệt cảm nhận)
    $temp = $data['temp'] ?? null;
    $hum = $data['humidity'] ?? null;
    $heatIndex = null;
    if ($temp !== null && $hum !== null) {
        $heatIndex = calcHeatIndex((float)$temp, (float)$hum);
    }

    try {
        $pdo->prepare("
            INSERT INTO env_readings
                (device_id, barn_id, cycle_id, day_age,
                 temperature, humidity, heat_index, light_lux,
                 nh3_ppm, mq137_raw, co2_ppm, mq135_raw,
                 mq_warmup, recorded_at)
            VALUES
                (:device_id, :barn_id, :cycle_id, :day_age,
                 :temp, :humidity, :heat_index, :lux,
                 :nh3, :mq137_raw, :co2, :mq135_raw,
                 :warmup, NOW())
        ")->execute([
            ':device_id'   => $deviceId,
            ':barn_id'     => $barnId,
            ':cycle_id'    => $cycleId,
            ':day_age'     => $dayAge,
            ':temp'        => $temp,
            ':humidity'    => $hum,
            ':heat_index'  => $heatIndex,
            ':lux'         => $data['lux'] ?? null,
            ':nh3'         => $data['nh3_ppm'] ?? null,
            ':mq137_raw'   => $data['mq137_raw'] ?? null,
            ':co2'         => $data['co2_ppm'] ?? null,
            ':mq135_raw'   => $data['mq135_raw'] ?? null,
            ':warmup'      => ($data['warmup'] ?? false) ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        logMsg("ENV [{$device['device_code']}] INSERT FAILED: " . $e->getMessage());
        logMsg("ENV [{$device['device_code']}] payload: " . substr($message, 0, 300));
        return;
    }

    // Cập nhật device online status
    $pdo->prepare("
        UPDATE devices SET is_online = 1, last_heartbeat_at = NOW(), ping_fail_count = 0
        WHERE id = ?
    ")->execute([$deviceId]);

    $nullCount = ($temp === null ? 1 : 0) + ($hum === null ? 1 : 0) + (($data['lux'] ?? null) === null ? 1 : 0)
        + (($data['nh3_ppm'] ?? null) === null ? 1 : 0) + (($data['co2_ppm'] ?? null) === null ? 1 : 0);
    $nullInfo = $nullCount > 0 ? " ({$nullCount} null sensors)" : "";
    logMsg("ENV [{$device['device_code']}] OK{$nullInfo} T={$temp} H={$hum} HI={$heatIndex} L=" . ($data['lux'] ?? 'null') . " NH3=" . ($data['nh3_ppm'] ?? 'null') . " CO2=" . ($data['co2_ppm'] ?? 'null') . " day={$dayAge}");
}

/**
 * Tính Heat Index (chỉ số nhiệt cảm nhận) theo công thức Rothfusz
 * Dùng cho cảnh báo stress nhiệt gia cầm
 */
function calcHeatIndex(float $tempC, float $humidity): float
{
    // Chuyển sang Fahrenheit để tính
    $T = $tempC * 9.0 / 5.0 + 32.0;
    $R = $humidity;

    // Công thức đơn giản cho T < 80°F
    $hi = 0.5 * ($T + 61.0 + (($T - 68.0) * 1.2) + ($R * 0.094));

    if ($hi >= 80) {
        // Công thức Rothfusz đầy đủ
        $hi = -42.379 + 2.04901523*$T + 10.14333127*$R
            - 0.22475541*$T*$R - 0.00683783*$T*$T
            - 0.05481717*$R*$R + 0.00122874*$T*$T*$R
            + 0.00085282*$T*$R*$R - 0.00000199*$T*$T*$R*$R;

        // Điều chỉnh cho độ ẩm thấp
        if ($R < 13 && $T >= 80 && $T <= 112) {
            $hi -= ((13 - $R) / 4) * sqrt((17 - abs($T - 95)) / 17);
        }
        // Điều chỉnh cho độ ẩm cao
        if ($R > 85 && $T >= 80 && $T <= 87) {
            $hi += (($R - 85) / 10) * ((87 - $T) / 5);
        }
    }

    // Chuyển về Celsius
    return round(($hi - 32.0) * 5.0 / 9.0, 2);
}

/**
 * Đánh dấu devices offline nếu không heartbeat quá OFFLINE_TIMEOUT giây
 * Tăng ping_fail_count cho devices online mà chưa đến mức offline
 */
function cleanupOffline(PDO $pdo): void
{
    // Tăng ping_fail_count cho devices online nhưng heartbeat cũ hơn PING_TIMEOUT
    $pdo->prepare("
        UPDATE devices SET ping_fail_count = ping_fail_count + 1
        WHERE is_online = 1
        AND last_heartbeat_at IS NOT NULL
        AND last_heartbeat_at < DATE_SUB(NOW(), INTERVAL :timeout SECOND)
        AND last_heartbeat_at >= DATE_SUB(NOW(), INTERVAL :offline SECOND)
    ")->execute([':timeout' => PING_TIMEOUT, ':offline' => OFFLINE_TIMEOUT]);

    // Đánh dấu offline nếu quá OFFLINE_TIMEOUT
    $stmt = $pdo->prepare("
        UPDATE devices SET is_online = 0
        WHERE is_online = 1
        AND (last_heartbeat_at IS NULL OR last_heartbeat_at < DATE_SUB(NOW(), INTERVAL :timeout SECOND))
    ");
    $stmt->execute([':timeout' => OFFLINE_TIMEOUT]);

    $count = $stmt->rowCount();
    if ($count > 0) {
        logMsg("Marked {$count} device(s) as OFFLINE (no heartbeat > " . OFFLINE_TIMEOUT . "s)");
    }
}

/**
 * Gửi ping đến tất cả devices online
 */
function sendPings(PDO $pdo, MqttClient $client): void
{
    $devices = $pdo->query("
        SELECT id, mqtt_topic FROM devices WHERE is_online = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($devices as $device) {
        try {
            $payload = json_encode(['action' => 'ping', 'ts' => time()]);
            $client->publish($device['mqtt_topic'] . '/cmd', $payload, MqttClient::QOS_AT_MOST_ONCE);
        } catch (Exception $e) {
            logMsg("Ping failed for {$device['mqtt_topic']}: " . $e->getMessage());
        }
    }

    if (count($devices) > 0) {
        logMsg("Sent ping to " . count($devices) . " device(s)");
    }
}

/**
 * Main loop với auto-reconnect
 */
function run(PDO $pdo): void
{
    $reconnectDelay = 1;

    while (true) {
        try {
            $clientId = 'cfarm_listener_' . getmypid();

            logMsg("Connecting to MQTT broker " . MQTT_HOST . ":" . MQTT_PORT . " ...");

            $client = new MqttClient(MQTT_HOST, MQTT_PORT, $clientId);

            $settings = (new ConnectionSettings)
                ->setUsername(MQTT_USER)
                ->setPassword(MQTT_PASS)
                ->setKeepAliveInterval(30)
                ->setConnectTimeout(10)
                ->setSocketTimeout(5);

            $client->connect($settings);

            logMsg("Connected! Subscribing to " . MQTT_TOPIC);

            $client->subscribe(MQTT_TOPIC, function (string $topic, string $message) use ($pdo) {
                try {
                    processMessage($pdo, $topic, $message);
                } catch (Exception $e) {
                    logMsg("Error processing [{$topic}]: " . $e->getMessage());
                }
            }, MqttClient::QOS_AT_LEAST_ONCE);

            logMsg("Listening for messages...");

            // Reset reconnect delay on successful connect
            $reconnectDelay = 1;

            global $lastCleanup, $lastPing;
            $lastCleanup = time();
            $lastPing    = time();

            // Main loop - loopOnce cho phép ta chạy logic khác giữa các iteration
            while ($client->isConnected()) {
                $client->loopOnce(microtime(true), true, 100000); // 100ms sleep

                $now = time();

                // Cleanup offline devices
                if ($now - $lastCleanup >= CLEANUP_INTERVAL) {
                    $lastCleanup = $now;
                    cleanupOffline($pdo);
                }

                // Send pings
                if ($now - $lastPing >= PING_INTERVAL) {
                    $lastPing = $now;
                    sendPings($pdo, $client);
                }
            }

            logMsg("Connection lost, will reconnect...");

        } catch (Exception $e) {
            logMsg("MQTT Error: " . $e->getMessage());
        }

        // Reconnect with exponential backoff (max 60s)
        logMsg("Reconnecting in {$reconnectDelay}s ...");
        sleep($reconnectDelay);
        $reconnectDelay = min($reconnectDelay * 2, MAX_RECONNECT_DELAY);

        // Reconnect PDO nếu cần
        try {
            $pdo->query("SELECT 1");
        } catch (Exception $e) {
            logMsg("DB connection lost, reconnecting...");
            require __DIR__ . '/../../shared/database/mysql.php';
        }
    }
}

// ============== START ==============
logMsg("=== CFarm MQTT Listener starting ===");
logMsg("PID: " . getmypid());
logMsg("Broker: " . MQTT_HOST . ":" . MQTT_PORT);
logMsg("Subscribe: " . MQTT_TOPIC);
logMsg("Offline timeout: " . OFFLINE_TIMEOUT . "s");

run($pdo);
