<?php
declare(strict_types=1);
namespace App\Domains\IoT;

class MqttService
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->host = '103.166.183.215'; // MQTT Broker - cloud server IP
        $this->port = 1883;
        $this->user = 'cfarm_server';
        $this->pass = 'Abc@@123';
    }

    /**
     * Publish a message to MQTT topic using mosquitto_pub
     */
    public function publish(string $topic, array $payload): bool
    {
        $message = json_encode($payload);
        $cmd = sprintf(
            'mosquitto_pub -h %s -p %d -u %s -P %s -t "%s" -m "%s" -q 1',
            escapeshellcmd($this->host),
            $this->port,
            escapeshellcmd($this->user),
            escapeshellcmd($this->pass),
            escapeshellcmd($topic),
            escapeshellcmd($message)
        );

        exec($cmd, $output, $return);
        return $return === 0;
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