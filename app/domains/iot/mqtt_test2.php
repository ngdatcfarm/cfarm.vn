<?php
/**
 * MQTT Listener - Simple version
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$host = '103.166.183.215';
$port = 1883;
$user = 'cfarm_server';
$pass = 'Abc@@123';

echo "Creating client...\n";

$client = new \PhpMqtt\Client\MqttClient($host, $port, 'test');

echo "Connecting...\n";

$client->connect(
    new \PhpMqtt\Client\ConnectionSettings(null, null, $user, $pass)
);

echo "Connected! Subscribing...\n";

$client->subscribe('cfarm/#', function($topic, $message) {
    echo "$topic: $message\n";
});

echo "Listening...\n";
$client->loop(true);
