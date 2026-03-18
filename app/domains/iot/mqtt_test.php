<?php
/**
 * MQTT Listener - Debug version
 */

echo "Starting...\n";

require_once __DIR__ . '/../../vendor/autoload.php';

echo "Autoload loaded\n";

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

echo "Creating MQTT client...\n";

$host = '103.166.183.215';
$port = 1883;
$user = 'cfarm_server';
$pass = 'Abc@@123';

try {
    $mqtt = new MqttClient($host, $port, 'test_listener');
    echo "Connecting...\n";
    
    $mqtt->connect(
        (new ConnectionSettings)
            ->setUsername($user)
            ->setPassword($pass)
    );
    
    echo "Connected! Subscribing...\n";
    
    $mqtt->subscribe('cfarm/#', function($topic, $message) {
        echo "Received: $topic\n";
    }, 0);
    
    echo "Listening...\n";
    $mqtt->loop(true);
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
