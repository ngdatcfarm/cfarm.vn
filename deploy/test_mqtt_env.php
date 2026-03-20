<?php
/**
 * Test script: Gửi 1 message ENV giả qua MQTT để kiểm tra listener có hoạt động
 *
 * Chạy trên server: php deploy/test_mqtt_env.php
 *
 * Trước khi chạy:
 *   1. Kiểm tra listener đang chạy: systemctl status cfarm-mqtt-listener
 *   2. Xem log listener:            journalctl -u cfarm-mqtt-listener -f
 *   3. Kiểm tra device_code tồn tại trong DB: SELECT device_code FROM devices;
 *
 * Sau khi chạy:
 *   - Xem log listener có dòng "ENV [...] OK" không
 *   - Kiểm tra DB: SELECT * FROM env_readings ORDER BY id DESC LIMIT 5;
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/shared/database/mysql.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// ====== CONFIG ======
$mqttHost = '103.166.183.215';
$mqttPort = 1883;
$mqttUser = 'cfarm_server';
$mqttPass = 'Abc@@123';

echo "=== CFarm MQTT ENV Test ===\n\n";

// Step 1: Kiểm tra bảng env_readings tồn tại
echo "[1] Kiểm tra bảng env_readings...\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'env_readings'")->fetch();
    if (!$result) {
        echo "    !!! BẢNG env_readings CHƯA TẠO !!!\n";
        echo "    Chạy SQL: mysql -u cfarm_user -p cfarm_app_raw < SQLADD_sensor_readings.sql\n";
        exit(1);
    }
    echo "    OK - bảng tồn tại\n";

    $count = $pdo->query("SELECT COUNT(*) as c FROM env_readings")->fetch()['c'];
    echo "    Hiện có {$count} bản ghi\n\n";
} catch (PDOException $e) {
    echo "    DB ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Lấy device_code đầu tiên từ DB
echo "[2] Tìm device trong DB...\n";
$device = $pdo->query("
    SELECT d.id, d.device_code, d.mqtt_topic, d.barn_id, d.device_type, d.is_online
    FROM devices d
    ORDER BY d.id
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($device)) {
    echo "    !!! KHÔNG CÓ DEVICE NÀO TRONG DB !!!\n";
    echo "    Thêm device trước khi test.\n";
    exit(1);
}

echo "    Danh sách devices:\n";
foreach ($device as $d) {
    $online = $d['is_online'] ? 'ONLINE' : 'offline';
    echo "    - [{$d['id']}] {$d['device_code']} | topic={$d['mqtt_topic']} | barn={$d['barn_id']} | type={$d['device_type']} | {$online}\n";
}

// Tìm ENV sensor device
$envDevice = null;
foreach ($device as $d) {
    if (stripos($d['device_type'] ?? '', 'env') !== false || stripos($d['device_type'] ?? '', 'sensor') !== false) {
        $envDevice = $d;
        break;
    }
}
if (!$envDevice) {
    $envDevice = $device[0]; // Fallback: dùng device đầu tiên
    echo "\n    Không tìm thấy device loại ENV, dùng device đầu tiên: {$envDevice['device_code']}\n";
}

$deviceCode = $envDevice['device_code'];
$mqttTopic  = $envDevice['mqtt_topic'];
echo "\n    Sẽ test với device: {$deviceCode} (topic: {$mqttTopic})\n\n";

// Step 3: Kiểm tra MQTT listener đang chạy
echo "[3] Kiểm tra MQTT listener...\n";
$listenerPid = trim(shell_exec("pgrep -f 'mqtt_listener.php' 2>/dev/null") ?? '');
if ($listenerPid) {
    echo "    OK - Listener đang chạy (PID: {$listenerPid})\n\n";
} else {
    echo "    !!! LISTENER KHÔNG CHẠY !!!\n";
    echo "    Khởi động: systemctl start cfarm-mqtt-listener\n";
    echo "    Hoặc chạy thủ công: php app/domains/iot/mqtt_listener.php &\n\n";
    echo "    Tiếp tục gửi message test anyway...\n\n";
}

// Step 4: Gửi message ENV giả
echo "[4] Gửi message ENV test...\n";
$payload = json_encode([
    'device'    => $deviceCode,
    'temp'      => 28.5,
    'humidity'  => 72.3,
    'lux'       => 150.0,
    'nh3_ppm'   => 8.2,
    'co2_ppm'   => 620.5,
    'mq137_raw' => 1850,
    'mq135_raw' => 2100,
    'warmup'    => true,
    'seq'       => 999,
]);

$topic = $mqttTopic . '/env';
echo "    Topic: {$topic}\n";
echo "    Payload: {$payload}\n\n";

try {
    $client = new MqttClient($mqttHost, $mqttPort, 'cfarm_test_' . getmypid());
    $settings = (new ConnectionSettings)
        ->setUsername($mqttUser)
        ->setPassword($mqttPass)
        ->setConnectTimeout(5);

    $client->connect($settings);
    echo "    MQTT connected!\n";

    $client->publish($topic, $payload, MqttClient::QOS_AT_LEAST_ONCE);
    echo "    Message SENT!\n";

    $client->disconnect();
    echo "    Disconnected.\n\n";
} catch (Exception $e) {
    echo "    !!! MQTT SEND FAILED: " . $e->getMessage() . "\n";
    echo "    Kiểm tra:\n";
    echo "    - Server {$mqttHost}:{$mqttPort} có accessible không?\n";
    echo "    - Credentials đúng không?\n";
    echo "    - Firewall có chặn port 1883 không?\n";
    exit(1);
}

// Step 5: Đợi và kiểm tra kết quả
echo "[5] Đợi 3s rồi kiểm tra DB...\n";
sleep(3);

$newCount = $pdo->query("SELECT COUNT(*) as c FROM env_readings")->fetch()['c'];
echo "    env_readings: {$newCount} bản ghi (trước: {$count})\n";

if ($newCount > $count) {
    $latest = $pdo->query("SELECT * FROM env_readings ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo "\n    +++ THÀNH CÔNG! Dữ liệu đã được ghi +++\n";
    echo "    Latest record:\n";
    foreach ($latest as $k => $v) {
        echo "      {$k} = {$v}\n";
    }
} else {
    echo "\n    !!! KHÔNG CÓ DỮ LIỆU MỚI !!!\n";
    echo "\n    Debug checklist:\n";
    echo "    1. Listener có đang chạy? systemctl status cfarm-mqtt-listener\n";
    echo "    2. Xem log listener:      tail -50 /var/log/cfarm-mqtt-listener.log\n";
    echo "    3. device_code '{$deviceCode}' có trong DB? Đã check ở step 2 → OK\n";
    echo "    4. Bảng env_readings có đúng schema? DESCRIBE env_readings;\n";
    echo "    5. Thử restart listener:  systemctl restart cfarm-mqtt-listener\n";
}

echo "\n=== Done ===\n";
