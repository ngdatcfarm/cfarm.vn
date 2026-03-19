<?php
/**
 * MQTT Listener - Fresh start
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting...\n";

$cmd = '/usr/bin/mosquitto_sub -h 103.166.183.215 -u cfarm_device -P Abc@@123 -t "cfarm/#" -v --id cfarm_listener_v2 --keepalive 60';

$desc = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open($cmd, $desc, $pipes);

if (!$proc) {
    echo "FAIL\n";
    exit(1);
}

echo "OK, waiting...\n";

while (true) {
    $line = fgets($pipes[1]);
    if ($line) {
        echo $line;
    }
    usleep(100000);
}
