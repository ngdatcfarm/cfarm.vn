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
     * Format: {"action": "relay", "channel": 1, "state": "on"}
     */
    public function sendCommand(string $deviceMqttTopic, int $channel, string $action, int $durationSeconds = 0): bool
    {
        $state = ($action === 'on' || $action === 'stop') ? 'on' : 'off';
        
        $payload = [
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => $state,
        ];
        
        // Gửi lệnh ON
        $sent = $this->publish($deviceMqttTopic . '/command', $payload);
        
        // Nếu có duration > 0, lên lịch gửi OFF sau duration giây
        // Sử dụng at command để schedule gửi lệnh OFF
        if ($sent && $durationSeconds > 0 && $state === 'on') {
            $this->scheduleOff($deviceMqttTopic, $channel, $durationSeconds);
        }
        
        return $sent;
    }

    /**
     * Schedule OFF command after X seconds using at command
     */
    private function scheduleOff(string $deviceMqttTopic, int $channel, int $delaySeconds): void
    {
        // Tạo payload cho lệnh OFF
        $offPayload = json_encode([
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => 'off',
        ]);
        
        // Tạo lệnh gửi OFF sau X giây
        // Sử dụng at command (Linux) hoặc schtasks (Windows)
        $topic = $deviceMqttTopic . '/command';
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: sử dụng timeout trong cmd
            $cmd = sprintf(
                'timeout /t %d /nobreak > nul & mosquitto_pub -h %s -p %d -u %s -P %s -t "%s" -m "%s" -q 1',
                $delaySeconds,
                escapeshellcmd($this->host),
                $this->port,
                escapeshellcmd($this->user),
                escapeshellcmd($this->pass),
                escapeshellcmd($topic),
                escapeshellcmd($offPayload)
            );
            // Chạy trong background
            pclose(popen('start /b ' . $cmd, 'r'));
        } else {
            // Linux: sử dụng at hoặc sleep + &
            $cmd = sprintf(
                'mosquitto_pub -h %s -p %d -u %s -P %s -t "%s" -m "%s" -q 1 &',
                escapeshellcmd($this->host),
                $this->port,
                escapeshellcmd($this->user),
                escapeshellcmd($this->pass),
                escapeshellcmd($topic),
                escapeshellcmd($offPayload)
            );
            exec(sprintf('sleep %d && %s', $delaySeconds, $cmd));
        }
    }

    /**
     * Send curtain command (calculated duration)
     * Format: {"action": "relay", "channel": 1, "state": "on"}
     * ESP32 will turn ON, server schedules OFF after duration
     */
    public function sendCurtainMove(
        string $deviceMqttTopic,
        int    $channel,
        string $direction, // up or down (currently unused, same logic for both)
        float  $durationSeconds
    ): bool {
        $payload = [
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => 'on',
        ];
        
        // Gửi lệnh ON
        $sent = $this->publish($deviceMqttTopic . '/command', $payload);
        
        // Schedule OFF sau duration
        if ($sent && $durationSeconds > 0) {
            $this->scheduleOff($deviceMqttTopic, (int)$channel, (int)$durationSeconds);
        }
        
        return $sent;
    }

    /**
     * Send stop command (emergency stop)
     * Format: {"action": "relay", "channel": 1, "state": "off"}
     */
    public function sendStop(string $deviceMqttTopic, int $channel): bool
    {
        $payload = [
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => 'off',
        ];
        
        return $this->publish($deviceMqttTopic . '/command', $payload);
    }

    /**
     * Send command to ALL relays
     * Format: {"action": "all", "state": "on"} or {"action": "all", "state": "off"}
     */
    public function sendAll(string $deviceMqttTopic, string $state): bool
    {
        $payload = [
            'action' => 'all',
            'state'  => $state,
        ];
        
        return $this->publish($deviceMqttTopic . '/command', $payload);
    }
}
