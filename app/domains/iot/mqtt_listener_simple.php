<?php
/**
 * MQTT Listener - Simple version - Just receive messages
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/shared/database/mysql.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Simple MQTT Listener...\n";

$cmd = "mosquitto_sub -h 103.166.183.215 -u cfarm_device -P Abc@@123 -t 'cfarm/#' -v --id cfarm_listener_simple --keepalive 60 2>&1";

$descriptors = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"],
];

$process = proc_open($cmd, $descriptors, $pipes);

if (!is_resource($process)) {
    echo "Failed to start\n";
    exit(1);
}

stream_set_blocking($pipes[1], true);

echo "Listening for messages...\n";

while (!feof($pipes[1])) {
    $line = fgets($pipes[1]);

    if ($line) {
        echo $line;
    }

    // Small sleep to prevent CPU spinning
    usleep(100000);
}

proc_close($process);
echo "Done\n";
