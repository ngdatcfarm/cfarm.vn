<?php
declare(strict_types=1);
namespace App\Domains\IoT\Services;

use Exception;

/**
 * MQTT Service - Gửi lệnh đến ESP32 qua MQTT
 */
class MqttService
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->host = '103.166.183.215'; // MQTT Broker IP
        $this->port = 1883;
        $this->user = 'cfarm_server';
        $this->pass = 'Abc@@123';
    }

    /**
     * Publish message đến MQTT topic
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

        exec($cmd . ' 2>&1', $output, $return);
        
        if ($return !== 0) {
            error_log("MQTT publish failed: " . implode("\n", $output));
        }
        
        return $return === 0;
    }

    /**
     * Gửi lệnh bật/tắt relay
     * Format: {"action": "relay", "channel": 1, "state": "on"}
     */
    public function sendRelayCommand(
        string $mqttTopic, 
        int $channel, 
        string $state
    ): bool {
        $payload = [
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => $state, // "on" or "off"
        ];
        
        return $this->publish($mqttTopic . '/cmd', $payload);
    }

    /**
     * Gửi lệnh bật relay với thời gian
     * ESP32 sẽ tự tắt sau duration
     */
    public function sendRelayOnWithDuration(
        string $mqttTopic,
        int $channel,
        int $durationSeconds
    ): bool {
        // Gửi lệnh ON
        $sent = $this->sendRelayCommand($mqttTopic, $channel, 'on');
        
        // Schedule lệnh OFF sau duration
        if ($sent && $durationSeconds > 0) {
            $this->scheduleRelayOff($mqttTopic, $channel, $durationSeconds);
        }
        
        return $sent;
    }

    /**
     * Gửi lệnh tắt relay
     */
    public function sendRelayOff(string $mqttTopic, int $channel): bool
    {
        return $this->sendRelayCommand($mqttTopic, $channel, 'off');
    }

    /**
     * Gửi lệnh đến tất cả các kênh
     * Format: {"action": "all", "state": "on"}
     */
    public function sendAllCommand(string $mqttTopic, string $state): bool
    {
        $payload = [
            'action' => 'all',
            'state'  => $state,
        ];
        
        return $this->publish($mqttTopic . '/cmd', $payload);
    }

    /**
     * Schedule gửi lệnh OFF sau X giây
     */
    private function scheduleRelayOff(string $mqttTopic, int $channel, int $delaySeconds): void
    {
        $offPayload = json_encode([
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => 'off',
        ]);
        
        $topic = $mqttTopic . '/cmd';
        
        // Linux - dùng at command hoặc background process với sleep
        $cmd = sprintf(
            'mosquitto_pub -h %s -p %d -u %s -P %s -t "%s" -m "%s" -q 1 &',
            escapeshellcmd($this->host),
            $this->port,
            escapeshellcmd($this->user),
            escapeshellcmd($this->pass),
            escapeshellcmd($topic),
            escapeshellcmd($offPayload)
        );
        
        // Chạy background với delay
        $fullCmd = sprintf('sleep %d && %s', $delaySeconds, $cmd);
        exec($fullCmd . ' > /dev/null 2>&1 &');
    }
}
