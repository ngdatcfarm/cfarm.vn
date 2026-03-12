<?php
/**
 * CFarm — Curtain Position Sync (Cron fallback)
 * Chạy mỗi 30 giây qua systemd timer
 *
 * Mục đích: nếu ESP32 không gửi timeout status (mất kết nối, reboot...),
 * server tự cập nhật curtain position dựa trên elapsed time.
 */

require_once '/var/www/app.cfarm.vn/app/shared/database/mysql.php';

$now = time();

// Lấy tất cả curtain đang moving mà đã hết duration
$stmt = $pdo->query("
    SELECT cc.*,
           uc.channel_number as up_ch, dc.channel_number as down_ch,
           ud.mqtt_topic as up_mqtt, dd.mqtt_topic as down_mqtt,
           ud.is_online as up_online, dd.is_online as down_online
    FROM curtain_configs cc
    JOIN device_channels uc ON uc.id = cc.up_channel_id
    JOIN device_channels dc ON dc.id = cc.down_channel_id
    JOIN devices ud ON ud.id = uc.device_id
    JOIN devices dd ON dd.id = dc.device_id
    WHERE cc.moving_state != 'idle'
      AND cc.moving_started_at IS NOT NULL
      AND cc.moving_duration_seconds IS NOT NULL
");
$curtains = $stmt->fetchAll(PDO::FETCH_OBJ);

$updated = 0;
$skipped = 0;

foreach ($curtains as $c) {
    $started  = strtotime($c->moving_started_at);
    $duration = (float)$c->moving_duration_seconds;
    $elapsed  = $now - $started;

    if ($elapsed < $duration) {
        // Chưa hết giờ — bỏ qua
        $skipped++;
        continue;
    }

    // Đã hết duration — tính position thực tế
    // Nếu elapsed vượt quá duration, position = target (đã chạy xong)
    $ratio    = min(1.0, $elapsed / $duration);
    $from     = (int)$c->current_position_pct;
    $to       = (int)$c->moving_target_pct;
    $real_pos = $from + (int)round(($to - $from) * $ratio);
    $real_pos = max(0, min(100, $real_pos));

    // Cập nhật DB
    $pdo->prepare("
        UPDATE curtain_configs
        SET current_position_pct    = :pos,
            moving_state            = 'idle',
            moving_target_pct       = NULL,
            moving_started_at       = NULL,
            moving_duration_seconds = NULL
        WHERE id = :id
    ")->execute([':pos' => $real_pos, ':id' => $c->id]);

    echo '[' . date('H:i:s') . "] Curtain #{$c->id} '{$c->name}': {$from}% → {$real_pos}% (elapsed {$elapsed}s / {$duration}s)\n";
    $updated++;
}

if ($updated > 0 || $skipped === 0) {
    echo '[' . date('H:i:s') . "] Done: {$updated} updated, {$skipped} still moving\n";
}
