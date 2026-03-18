-- ============================================================
-- Add device_firmwares table for firmware template management
-- ============================================================

-- Table for storing firmware templates
CREATE TABLE IF NOT EXISTS device_firmwares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Tên firmware',
    version VARCHAR(20) NOT NULL COMMENT 'Version: 1.0.0',
    description TEXT COMMENT 'Mô tả',
    device_type_id INT NOT NULL COMMENT 'Loại thiết bị',
    code TEXT NOT NULL COMMENT 'Mã nguồn firmware',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Đang hoạt động',
    is_latest TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Phiên bản mới nhất',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_type_id) REFERENCES device_types(id) ON DELETE CASCADE,
    INDEX idx_device_type (device_type_id),
    INDEX idx_is_latest (is_latest)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Firmware templates';

-- Insert default firmware for ESP32 Relay 8CH
INSERT INTO device_firmwares (id, name, version, description, device_type_id, code, is_active, is_latest) VALUES
(1, 'ESP32 Relay 8CH v1.0', '1.0.0', 'Firmware chuẩn cho ESP32 Relay 8 kênh', 1, 
'/*
 * CFarm ESP32 Relay Controller
 * ================================
 * MQTT PROTOCOL:
 * - Subscribe: {device}/cmd
 * - Format: {\"action\": \"relay\", \"channel\": 1, \"state\": \"on\"}
 * ================================
 */
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

#define DEVICE_CODE \"YOUR_DEVICE_CODE\"
const char* WIFI_SSID = \"YOUR_WIFI_SSID\";
const char* WIFI_PASS = \"YOUR_WIFI_PASS\";
const char* MQTT_SERVER = \"app.cfarm.vn\";
const char* MQTT_USER = \"cfarm_device\";
const char* MQTT_PASS = \"Abc@@123\";
const char* MQTT_TOPIC = \"cfarm/YOUR_DEVICE_CODE\";

const int RELAY_PINS[8] = {32, 33, 25, 26, 27, 14, 12, 13};
const int INTERLOCK_PAIRS[][2] = {{1,2},{3,4},{5,6},{7,8}};
const int NUM_INTERLOCKS = 4;

WiFiClient espClient;
PubSubClient mqtt(espClient);
unsigned long lastHeartbeat = 0;
const unsigned long HEARTBEAT_INTERVAL = 30000;
bool relayState[8] = {false};

void setup() {
    Serial.begin(115200);
    for (int i = 0; i < 8; i++) {
        pinMode(RELAY_PINS[i], OUTPUT);
        digitalWrite(RELAY_PINS[i], HIGH);
    }
    connectWiFi();
    connectMqtt();
}

void loop() {
    if (!mqtt.connected()) connectMqtt();
    mqtt.loop();
    if (millis() - lastHeartbeat > HEARTBEAT_INTERVAL) {
        lastHeartbeat = millis();
        sendHeartbeat();
    }
}

void connectWiFi() {
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    while (WiFi.status() != WL_CONNECTED) delay(500);
}

void connectMqtt() {
    mqtt.setServer(MQTT_SERVER, 1883);
    mqtt.setCallback(mqttCallback);
    while (!mqtt.connected()) {
        if (mqtt.connect(String(\"ESP_\" + String(DEVICE_CODE)).c_str(), MQTT_USER, MQTT_PASS)) {
            mqtt.subscribe((String(MQTT_TOPIC) + \"/cmd\").c_str());
            sendHeartbeat();
        } else delay(1000);
    }
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
    String msg;
    for (int i = 0; i < length; i++) msg += (char)payload[i];
    StaticJsonDocument<256> doc;
    if (deserializeJson(doc, msg) == DeserializationError::Ok) {
        String action = doc[\"action\"] | \"\";
        if (action == \"relay\") {
            int ch = doc[\"channel\"] | 0;
            String st = doc[\"state\"] | \"\";
            if (ch >= 1 && ch <= 8) setRelay(ch - 1, st == \"on\");
        }
    }
}

void sendHeartbeat() {
    String payload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"status\\\":\\\"online\\\",\\\"wifi_rssi\\\":\" + String(WiFi.RSSI()) + \"}\";
    mqtt.publish((String(MQTT_TOPIC) + \"/heartbeat\").c_str(), payload.c_str(), true);
}

void setRelay(int ch, bool on) {
    if (on) {
        for (int i = 0; i < NUM_INTERLOCKS; i++) {
            int up = INTERLOCK_PAIRS[i][0] - 1;
            int down = INTERLOCK_PAIRS[i][1] - 1;
            if ((ch == up && relayState[down]) || (ch == down && relayState[up])) return;
        }
    }
    digitalWrite(RELAY_PINS[ch], on ? LOW : HIGH);
    relayState[ch] = on;
    mqtt.publish((String(MQTT_TOPIC) + \"/state\").c_str(), 
        (String(\"{\\\"device\\\":\\\"\") + String(DEVICE_CODE) + \"\\\",\\\"channel\\\":\" + String(ch+1) + \",\\\"state\\\":\\\"\" + (on?\"on\":\"off\") + \"\\\"}\").c_str());
}', 1, 1);

-- Add is_active column to device_types if not exists
ALTER TABLE device_types ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER description;

-- Update existing device_types to have is_active = 1
UPDATE device_types SET is_active = 1 WHERE is_active IS NULL OR is_active = 0;
