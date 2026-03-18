<?php
/**
 * MQTT Listener - Debug version
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_implicit_flush(true);

echo "Step 1: Loading autoload...\n";

require_once __DIR__ . '/../../../vendor/autoload.php';

echo "Step 2: Creating client...\n";

$host = '103.166.183.215';
$port = 1883;
$user = 'cfarm_device';
$pass = 'Abc@@123';

$client = new \PhpMqtt\Client\MqttClient($host, $port, 'test123');

echo "Step 3: Connecting...\n";

try {
    $client->connect(
        new \PhpMqtt\Client\ConnectionSettings('', 1883, '', $user, $pass)
    );
    echo "Step 4: Connected!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 5: Subscribing...\n";

$client->subscribe('cfarm/#', function($topic, $message) {
    echo "$topic => $message\n";
});

echo "Step 6: Looping...\n";
$client->loop(true);
