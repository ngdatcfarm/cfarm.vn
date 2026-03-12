<?php
/**
 * CFarm MQTT Listener Daemon
 * Subscribe heartbeat + status từ ESP32, cập nhật DB
 *
 * Chạy thử: php /var/www/app.cfarm.vn/app/domains/iot/mqtt_listener.php
 * Chạy nền: systemctl start cfarm-mqtt-listener
 */

require_once '/var/www/app.cfarm.vn/vendor/autoload.php';
$pdo = require '/var/www/app.cfarm.vn/app/shared/database/mysql.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

// Tắt output buffer để log hiện ngay
ob_implicit_flush(true);

function log_msg(string $level, string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . "] [{$level}] {$msg}\n";
}

log_msg('INFO', 'MQTT Listener starting...');


// Tắt offline các device chưa heartbeat trong 5 phút (từ lần chạy trước)
$pdo->exec("UPDATE devices SET is_online = 0 WHERE last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

$client = new MqttClient('127.0.0.1', 1883, 'cfarm_listener_' . getmypid());

$settings = (new ConnectionSettings)
    ->setUsername('cfarm_server')
    ->setPassword('Abc@@123')
    ->setKeepAliveInterval(60)
    ->setConnectTimeout(10)
    ->setLastWillTopic('cfarm/listener/status')
    ->setLastWillMessage(json_encode(['status' => 'offline', 'pid' => getmypid()]))
    ->setLastWillQualityOfService(1);

$client->connect($settings);
log_msg('INFO', 'Connected to MQTT broker');

// ================================================================
// HEARTBEAT: cfarm/+/heartbeat
// ESP32 gửi mỗi 30 giây — cập nhật online status, wifi info
// ================================================================
$client->subscribe('cfarm/+/env', function($topic, $message) use ($pdo) {
    handle_env($pdo, $topic, $message);
}, 0);
$client->subscribe('cfarm/+/telemetry', function($topic, $message) use ($pdo) {
    handle_telemetry($pdo, $topic, $message);
}, 0);
$client->subscribe('cfarm/+/heartbeat', function (string $topic, string $message) use ($pdo) {
    try {
        $data = json_decode($message, true);
        if (!$data || empty($data['device'])) return;

        $device_code = $data['device'];
        $is_online   = ($data['status'] ?? '') === 'online' ? 1 : 0;
        $wifi_rssi   = isset($data['wifi_rssi']) ? (int)$data['wifi_rssi'] : null;
        $ip          = $data['ip'] ?? null;
        $uptime      = isset($data['uptime']) ? (int)$data['uptime'] : null;
        $heap        = isset($data['heap']) ? (int)$data['heap'] : null;
        $version     = $data['version'] ?? null;

        $rows = $pdo->prepare("
            UPDATE devices
            SET is_online        = :online,
                last_heartbeat_at = NOW(),
                wifi_rssi         = :rssi,
                ip_address        = :ip,
                uptime_seconds    = :uptime,
                free_heap_bytes   = :heap,
                firmware_version  = COALESCE(:version, firmware_version)
            WHERE device_code = :code
        ")->execute([
            ':online'  => $is_online,
            ':rssi'    => $wifi_rssi,
            ':ip'      => $ip,
            ':uptime'  => $uptime,
            ':heap'    => $heap,
            ':version' => $version,
            ':code'    => $device_code,
        ]);

        // Cập nhật relay states từ heartbeat
        if (!empty($data['relays']) && is_array($data['relays'])) {
            $dev_stmt = $pdo->prepare("SELECT id FROM devices WHERE device_code = :code");
            $dev_stmt->execute([':code' => $device_code]);
            $dev = $dev_stmt->fetch();

            if ($dev) {
                foreach ($data['relays'] as $idx => $state_val) {
                    $ch_num   = $idx + 1;
                    $state_str = $state_val ? 'on' : 'off';
                    $pdo->prepare("
                        INSERT INTO device_states (device_id, channel_id, state, changed_at)
                        SELECT :did, dc.id, :state, NOW()
                        FROM device_channels dc
                        WHERE dc.device_id = :did2 AND dc.channel_number = :ch
                        ON DUPLICATE KEY UPDATE state = :state2, changed_at = NOW()
                    ")->execute([
                        ':did'    => $dev['id'],
                        ':did2'   => $dev['id'],
                        ':ch'     => $ch_num,
                        ':state'  => $state_str,
                        ':state2' => $state_str,
                    ]);
                }
            }
        }

        $status = $is_online ? 'ONLINE' : 'OFFLINE';
        log_msg('HB', "{$device_code} {$status} RSSI:{$wifi_rssi} UP:{$uptime}s HEAP:{$heap}");

    } catch (\Throwable $e) {
        log_msg('ERROR', 'Heartbeat handler: ' . $e->getMessage());
    }
}, 1);

// ================================================================
// STATUS: cfarm/+/status
// ESP32 gửi khi relay thay đổi (bật/tắt/timeout/interlock)
// ================================================================
$client->subscribe('cfarm/+/status', function (string $topic, string $message) use ($pdo) {
    try {
        $data = json_decode($message, true);
        if (!$data || empty($data['device'])) return;

        $device_code = $data['device'];
        $ch          = (int)($data['ch'] ?? 0);
        $state       = $data['state'] ?? '';
        $reason      = $data['reason'] ?? '';
        $msg_id      = $data['msg_id'] ?? '';

        log_msg('STATUS', "{$device_code} CH{$ch} {$state} reason={$reason} msg={$msg_id}");

        // 1. Cập nhật acknowledged_at trong device_commands
        if ($msg_id) {
            $pdo->prepare("
                UPDATE device_commands
                SET acknowledged_at = COALESCE(acknowledged_at, NOW())
                WHERE JSON_SEARCH(payload_json, 'one', :msg_id) IS NOT NULL
                  AND acknowledged_at IS NULL
                LIMIT 1
            ")->execute([':msg_id' => $msg_id]);
        }

        // 2. Khi relay OFF do timeout → bạt đã chạy xong → cập nhật position = target
        if ($state === 'off' && $reason === 'timeout') {
            $dev_stmt = $pdo->prepare("SELECT id FROM devices WHERE device_code = :code");
            $dev_stmt->execute([':code' => $device_code]);
            $dev = $dev_stmt->fetch();

            if ($dev) {
                $ch_stmt = $pdo->prepare("
                    SELECT id FROM device_channels
                    WHERE device_id = :did AND channel_number = :ch
                ");
                $ch_stmt->execute([':did' => $dev['id'], ':ch' => $ch]);
                $ch_row = $ch_stmt->fetch();

                if ($ch_row) {
                    // Cập nhật curtain: position = target, moving_state = idle
                    $updated = $pdo->prepare("
                        UPDATE curtain_configs
                        SET current_position_pct  = COALESCE(moving_target_pct, current_position_pct),
                            moving_state           = 'idle',
                            moving_target_pct      = NULL,
                            moving_started_at      = NULL,
                            moving_duration_seconds = NULL
                        WHERE (up_channel_id = :chid OR down_channel_id = :chid2)
                          AND moving_state != 'idle'
                    ");
                    $updated->execute([':chid' => $ch_row['id'], ':chid2' => $ch_row['id']]);

                    if ($updated->rowCount() > 0) {
                        log_msg('INFO', "Curtain reached target via CH{$ch} timeout");
                    }
                }
            }

            // Cập nhật completed_at trong device_commands
            if ($msg_id) {
                $pdo->prepare("
                    UPDATE device_commands
                    SET completed_at = NOW()
                    WHERE JSON_SEARCH(payload_json, 'one', :msg_id) IS NOT NULL
                      AND completed_at IS NULL
                    LIMIT 1
                ")->execute([':msg_id' => $msg_id]);
            }
        }

        // 3. Khi relay OFF do stop/interlock → cũng đánh dấu completed
        if ($state === 'off' && in_array($reason, ['ok', 'interlock'])) {
            if ($msg_id) {
                $pdo->prepare("
                    UPDATE device_commands
                    SET completed_at = NOW()
                    WHERE JSON_SEARCH(payload_json, 'one', :msg_id) IS NOT NULL
                      AND completed_at IS NULL
                    LIMIT 1
                ")->execute([':msg_id' => $msg_id]);
            }
        }

        // 4. Cập nhật device_states realtime
        $dev_stmt = $pdo->prepare("SELECT id FROM devices WHERE device_code = :code");
        $dev_stmt->execute([':code' => $device_code]);
        $dev = $dev_stmt->fetch();

        if ($dev && $ch > 0) {
            $ch_stmt = $pdo->prepare("
                SELECT id FROM device_channels
                WHERE device_id = :did AND channel_number = :ch
            ");
            $ch_stmt->execute([':did' => $dev['id'], ':ch' => $ch]);
            $ch_row = $ch_stmt->fetch();

            if ($ch_row) {
                $pdo->prepare("
                    INSERT INTO device_states (device_id, channel_id, state, changed_at)
                    VALUES (:did, :chid, :state, NOW())
                    ON DUPLICATE KEY UPDATE state = :state2, changed_at = NOW()
                ")->execute([
                    ':did'    => $dev['id'],
                    ':chid'   => $ch_row['id'],
                    ':state'  => $state === 'on' ? 'on' : 'off',
                    ':state2' => $state === 'on' ? 'on' : 'off',
                ]);
            }
        }

    } catch (\Throwable $e) {
        log_msg('ERROR', 'Status handler: ' . $e->getMessage());
    }
}, 1);

log_msg('INFO', 'Subscribed: cfarm/+/heartbeat, cfarm/+/status');
log_msg('INFO', 'Listening for messages...');

// Offline watchdog: mỗi 2 phút, đánh dấu offline device không heartbeat
$lastOfflineCheck = time();


// Offline watchdog mỗi 2 phút
$lastOfflineCheck = time();

while (true) {
    $client->loop(true, true);

    if (time() - $lastOfflineCheck >= 120) {
        $affected = $pdo->exec(
            "UPDATE devices SET is_online = 0
             WHERE is_online = 1
               AND last_heartbeat_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)"
        );
        if ($affected > 0) {
            log_msg('WATCH', "{$affected} device(s) marked offline (no heartbeat)");
        }
        $lastOfflineCheck = time();
    }
}
