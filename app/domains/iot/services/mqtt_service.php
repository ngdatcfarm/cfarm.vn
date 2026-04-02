<?php
declare(strict_types=1);
namespace App\Domains\IoT\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Exception;

/**
 * MQTT Service - Gửi/nhận lệnh ESP32 qua MQTT broker
 * Sử dụng php-mqtt/client thay vì mosquitto CLI
 *
 * Hỗ trợ dual-subscribe cho ESP32 firmware mới:
 * - Local prefix: cfarm/{code}/cmd (cho local server)
 * - Cloud prefix: cfarm.vn/{code}/cmd (cho cloud server - dual subscribe ESP32)
 */
class MqttService
{
    private string $host = '103.166.183.215';
    private int    $port = 1883;
    private string $user = 'cfarm_server';
    private string $pass = 'Abc@@123';

    private ?MqttClient $client = null;

    // Prefix cho cloud MQTT (dual-subscribe ESP32)
    const CLOUD_PREFIX = 'cfarm.vn';

    /**
     * Kết nối đến MQTT broker (lazy connect)
     */
    private function getClient(): MqttClient
    {
        if ($this->client !== null && $this->client->isConnected()) {
            return $this->client;
        }

        $clientId = 'cfarm_web_' . getmypid() . '_' . mt_rand(1000, 9999);

        $this->client = new MqttClient($this->host, $this->port, $clientId);

        $settings = (new ConnectionSettings)
            ->setUsername($this->user)
            ->setPassword($this->pass)
            ->setKeepAliveInterval(30)
            ->setConnectTimeout(5)
            ->setSocketTimeout(5);

        $this->client->connect($settings);

        return $this->client;
    }

    /**
     * Publish JSON message đến MQTT topic
     */
    public function publish(string $topic, array $payload): bool
    {
        try {
            $client = $this->getClient();
            $message = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $client->publish($topic, $message, MqttClient::QOS_AT_LEAST_ONCE);
            return true;
        } catch (Exception $e) {
            error_log("[MQTT] Publish failed on {$topic}: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Publish raw string message
     */
    public function publishRaw(string $topic, string $message, int $qos = 0, bool $retain = false): bool
    {
        try {
            $client = $this->getClient();
            $client->publish($topic, $message, $qos, $retain);
            return true;
        } catch (Exception $e) {
            error_log("[MQTT] PublishRaw failed on {$topic}: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Gửi lệnh bật/tắt relay
     */
    public function sendRelayCommand(string $mqttTopic, int $channel, string $state): bool
    {
        return $this->publish($mqttTopic . '/cmd', [
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => $state,
        ]);
    }

    /**
     * Gửi lệnh bật relay + tự tắt sau duration giây
     * ESP32 firmware sẽ handle auto-off dựa trên duration field
     */
    public function sendRelayOnWithDuration(string $mqttTopic, int $channel, int $durationSeconds): bool
    {
        return $this->publish($mqttTopic . '/cmd', [
            'action'   => 'relay',
            'channel'  => $channel,
            'state'    => 'on',
            'duration' => $durationSeconds,
        ]);
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
     */
    public function sendAllCommand(string $mqttTopic, string $state): bool
    {
        return $this->publish($mqttTopic . '/cmd', [
            'action' => 'all',
            'state'  => $state,
        ]);
    }

    /**
     * Gửi lệnh ping để kiểm tra device
     */
    public function sendPing(string $mqttTopic): bool
    {
        return $this->publish($mqttTopic . '/cmd', [
            'action' => 'ping',
            'ts'     => time(),
        ]);
    }

    // =========================================================================
    // DUAL-SUBSCRIBE METHODS (for ESP32 firmware with cloud MQTT support)
    // These publish to cfarm.vn/{code}/cmd instead of cfarm/{code}/cmd
    // =========================================================================

    /**
     * Chuyển đổi mqtt_topic sang cloud prefix
     * cfarm/barn1 -> cfarm.vn/barn1
     * cfarm.vn/barn1 -> cfarm.vn/barn1 (no change)
     */
    public function toCloudTopic(string $mqttTopic): string
    {
        // Remove any existing prefix
        $topic = preg_replace('#^cfarm\.vn/#', '', $mqttTopic);
        $topic = preg_replace('#^cfarm/#', '', $topic);

        return self::CLOUD_PREFIX . '/' . $topic;
    }

    /**
     * Gửi lệnh relay qua cloud MQTT (dual-subscribe ESP32)
     * Publishes to cfarm.vn/{code}/cmd
     */
    public function sendRelayCommandCloud(string $deviceCode, int $channel, string $state): bool
    {
        $topic = $this->toCloudTopic($deviceCode) . '/cmd';
        return $this->publish($topic, [
            'action'  => 'relay',
            'channel' => $channel,
            'state'   => $state,
        ]);
    }

    /**
     * Gửi lệnh relay với duration qua cloud MQTT (dual-subscribe ESP32)
     * Publishes to cfarm.vn/{code}/cmd
     */
    public function sendRelayOnWithDurationCloud(string $deviceCode, int $channel, int $durationSeconds): bool
    {
        $topic = $this->toCloudTopic($deviceCode) . '/cmd';
        return $this->publish($topic, [
            'action'   => 'relay',
            'channel'  => $channel,
            'state'    => 'on',
            'duration' => $durationSeconds,
        ]);
    }

    /**
     * Gửi lệnh tắt relay qua cloud MQTT (dual-subscribe ESP32)
     * Publishes to cfarm.vn/{code}/cmd
     */
    public function sendRelayOffCloud(string $deviceCode, int $channel): bool
    {
        return $this->sendRelayCommandCloud($deviceCode, $channel, 'off');
    }

    /**
     * Gửi lệnh ping qua cloud MQTT (dual-subscribe ESP32)
     * Publishes to cfarm.vn/{code}/cmd
     */
    public function sendPingCloud(string $deviceCode): bool
    {
        $topic = $this->toCloudTopic($deviceCode) . '/cmd';
        return $this->publish($topic, [
            'action' => 'ping',
            'ts'     => time(),
        ]);
    }

    /**
     * Gửi curtain position qua cloud MQTT (dual-subscribe ESP32)
     * Publishes to cfarm.vn/{code}/cmd với action set_position
     */
    public function sendCurtainPositionCloud(string $deviceCode, int $positionPct): bool
    {
        $topic = $this->toCloudTopic($deviceCode) . '/cmd';
        return $this->publish($topic, [
            'action' => 'set_position',
            'to'     => $positionPct,
        ]);
    }

    // =========================================================================

    /**
     * Đóng kết nối
     */
    public function disconnect(): void
    {
        if ($this->client !== null) {
            try {
                if ($this->client->isConnected()) {
                    $this->client->disconnect();
                }
            } catch (Exception $e) {
                // ignore
            }
            $this->client = null;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
