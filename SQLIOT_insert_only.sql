-- ============================================================
-- IoT Database - Safe Insert (không drop bảng)
-- ============================================================

-- 1. Thêm device_types nếu chưa có
INSERT IGNORE INTO device_types (id, name, description, device_class, total_channels, is_active) VALUES
(1, 'ESP32 Relay 8 kênh', 'Board relay 8 kênh điều khiển 4 bạt', 'relay', 8, 1),
(2, 'ESP32 DHT22 Sensor', 'Cảm biến nhiệt độ/độ ẩm', 'sensor', 0, 1),
(3, 'ESP32 ENV Sensor', 'Cảm biến môi trường', 'sensor', 0, 1);

-- 2. Thêm firmware nếu chưa có
INSERT IGNORE INTO device_firmwares (id, name, version, description, device_type_id, code, is_active, is_latest) VALUES
(1, 'ESP32 Relay 8CH Barn v1.0', '1.0.0', 'Điều khiển 4 tấm bạt với interlock', 1, 
'/*
 * CFarm ESP32 Relay 8CH - 4 Curtains
 * ============================================================
 * Kết nối GPIO: 32,33,25,26,27,14,12,13
 * MQTT: Subscribe /cmd, Publish /heartbeat + /state
 * Interlock: UP/DOWN không bật cùng lúc
 */
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

#define DEVICE_CODE \"YOUR_DEVICE_CODE\"
const char* WIFI_SSID = \"YOUR_WIFI_SSID\";
const char* WIFI_PASS = \"YOUR_WIFI_PASSWORD\";
const char* MQTT_SERVER = \"app.cfarm.vn\";
const char* MQTT_USER = \"cfarm_device\";
const char* MQTT_PASS = \"Abc@@123\";
const char* MQTT_TOPIC = \"cfarm/YOUR_MQTT_TOPIC\";

const int RELAY_PINS[8] = {32, 33, 25, 26, 27, 14, 12, 13};
const int INTERLOCK[][2] = {{1,2},{3,4},{5,6},{7,8}};

WiFiClient espClient;
PubSubClient mqtt(espClient);
bool relayState[8] = {false};
unsigned long lastHeartbeat = 0;

void setup() {
    Serial.begin(115200);
    for (int i = 0; i < 8; i++) {
        pinMode(RELAY_PINS[i], OUTPUT);
        digitalWrite(RELAY_PINS[i], HIGH);
    }
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    while (WiFi.status() != WL_CONNECTED) delay(500);
    mqtt.setServer(MQTT_SERVER, 1883);
    mqtt.setCallback(mqttCallback);
    while (!mqtt.connected()) {
        if (mqtt.connect(String(\"ESP_\" + String(DEVICE_CODE)).c_str(), MQTT_USER, MQTT_PASS)) {
            mqtt.subscribe((String(MQTT_TOPIC) + \"/cmd\").c_str());
            sendHeartbeat();
        }
        delay(1000);
    }
}

void loop() {
    if (!mqtt.connected()) mqtt.connect(String(\"ESP_\" + String(DEVICE_CODE)).c_str());
    mqtt.loop();
    if (millis() - lastHeartbeat > 30000) {
        lastHeartbeat = millis();
        sendHeartbeat();
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
        } else if (action == \"all\") {
            String st = doc[\"state\"] | \"\";
            for (int i = 0; i < 8; i++) setRelay(i, st == \"on\");
        }
    }
}

void setRelay(int ch, bool on) {
    if (on) {
        for (int i = 0; i < 4; i++) {
            int up = INTERLOCK[i][0] - 1;
            int down = INTERLOCK[i][1] - 1;
            if ((ch == up && relayState[down]) || (ch == down && relayState[up])) return;
        }
    }
    digitalWrite(RELAY_PINS[ch], on ? LOW : HIGH);
    relayState[ch] = on;
    String payload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"channel\\\":\" + String(ch+1) + \",\\\"state\\\":\\\"\" + (on?\"on\":\"off\") + \"\\\"}\";
    mqtt.publish((String(MQTT_TOPIC) + \"/state\").c_str(), payload.c_str());
}

void sendHeartbeat() {
    String r = \"[\";
    for (int i = 0; i < 8; i++) { r += relayState[i] ? \"1\" : \"0\"; if (i < 7) r += \",\"; }
    r += \"]\";
    String payload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"wifi_rssi\\\":\" + String(WiFi.RSSI()) + \",\\\"ip\\\":\\\"\" + WiFi.localIP().toString() + \"\\\",\\\"uptime\\\":\" + String(millis()/1000) + \",\\\"relays\\\":\" + r + \"}\";
    mqtt.publish((String(MQTT_TOPIC) + \"/heartbeat\").c_str(), payload.c_str(), true);
}', 1, 1);

-- Kiểm tra
SELECT id, name, device_class, is_active FROM device_types;
SELECT id, name, version, device_type_id, is_latest FROM device_firmwares;
