<?php
/**
 * MQTT Listener - Simple version
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

$host = '103.166.183.215';
$port = 1883;

echo "Testing MQTT connection...\n";

$client = new \PhpMqtt\Client\MqttClient($host, $port, 'test_cli');

try {
    echo "Connecting with user/pass...\n";
    $client->connect('cfarm_device', 'Abc@@123', false, null, 'cfarm_listen', 60, true);
    echo "Connected!\n";
    
    $client->subscribe('cfarm/#', function($topic, $message) {
        echo "$topic: $message\n";
    });
    
    echo "Listening...\n";
    $client->loop(true);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
