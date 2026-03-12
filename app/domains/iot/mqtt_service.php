<?php
declare(strict_types=1);
namespace App\Domains\IoT;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttService
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->host = '127.0.0.1';
        $this->port = 1883;
        $this->user = 'cfarm_server';
        $this->pass = 'Abc@@123';
    }

    /**
     * Publish a message to MQTT topic
     */
    public function publish(string $topic, array $payload): bool
    {
        try {
            $client = new MqttClient($this->host, $this->port, 'cfarm_php_' . uniqid());
            $settings = (new ConnectionSettings)
                ->setUsername($this->user)
                ->setPassword($this->pass)
                ->setConnectTimeout(3)
                ->setSocketTimeout(3);

            $client->connect($settings);
            $client->publish($topic, json_encode($payload), 1); // QoS 1
            $client->disconnect();
            return true;
        } catch (\Throwable $e) {
            error_log('MQTT publish error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send relay command to device
     */
    public function sendCommand(string $deviceMqttTopic, int $channel, string $action, int $durationSeconds = 0): bool
    {
        $payload = [
            'cmd'      => $action, // on, off, stop
            'ch'       => $channel,
            'dur'      => $durationSeconds,
            'ts'       => time(),
            'msg_id'   => uniqid('cmd_'),
        ];
        return $this->publish($deviceMqttTopic . '/cmd', $payload);
    }

    /**
     * Send curtain command (calculated duration)
     */
    public function sendCurtainMove(
        string $deviceMqttTopic,
        int    $channel,
        string $direction, // up or down
        float  $durationSeconds
    ): bool {
        $payload = [
            'cmd'      => 'on',
            'ch'       => $channel,
            'dur'      => round($durationSeconds, 1),
            'dir'      => $direction,
            'ts'       => time(),
            'msg_id'   => uniqid('cur_'),
        ];
        return $this->publish($deviceMqttTopic . '/cmd', $payload);
    }

    /**
     * Send stop command (emergency stop)
     */
    public function sendStop(string $deviceMqttTopic, int $channel): bool
    {
        return $this->sendCommand($deviceMqttTopic, $channel, 'off', 0);
    }
}
