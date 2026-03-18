-- ============================================================
-- Update firmware to new optimized version
-- ============================================================

UPDATE device_firmwares 
SET code = '/*
 * CFarm ESP32 Relay 8CH - 4 Curtains (Optimized)
 * ============================================================
 * Kết nối GPIO: 32,33,25,26,27,14,12,13
 * MQTT: Subscribe /cmd, Publish /heartbeat + /state
 * Interlock: UP/DOWN không bật cùng lúc + dead-time bảo vệ
 * 
 * Cải tiến:
 *   - Non-blocking WiFi/MQTT reconnect
 *   - Watchdog Timer (30s)
 *   - snprintf thay String concatenation (chống heap fragmentation)
 *   - Interlock auto-off kênh đối nghịch + dead-time 100ms
 *   - MQTT buffer 512 bytes
 *   - Lưu/khôi phục trạng thái relay qua NVS (Preferences)
 *   - OTA update qua ArduinoOTA
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <esp_task_wdt.h>
#include <Preferences.h>
#include <ArduinoOTA.h>

// ======================== CẤU HÌNH ========================
#define DEVICE_CODE       \"YOUR_DEVICE_CODE\"

const char* WIFI_SSID   = \"YOUR_WIFI_SSID\";
const char* WIFI_PASS   = \"YOUR_WIFI_PASSWORD\";
const char* MQTT_SERVER = \"app.cfarm.vn\";
const int   MQTT_PORT   = 1883;
const char* MQTT_USER   = \"cfarm_device\";
const char* MQTT_PASS   = \"Abc@@123\";
const char* MQTT_TOPIC  = \"cfarm/YOUR_MQTT_TOPIC\";

// ======================== PHẦN CỨNG ========================
const int RELAY_PINS[8]   = {32, 33, 25, 26, 27, 14, 12, 13};
const int INTERLOCK[][2]  = {{1,2}, {3,4}, {5,6}, {7,8}};
const int INTERLOCK_DEAD_TIME_MS = 100;

// ======================== TIMING ========================
const unsigned long HEARTBEAT_INTERVAL_MS   = 30000;
const unsigned long WIFI_RECONNECT_INTERVAL = 5000;
const unsigned long MQTT_RECONNECT_INTERVAL = 5000;
const unsigned long WDT_TIMEOUT_S           = 30;

// ======================== BIẾN TOÀN CỤC ========================
WiFiClient espClient;
PubSubClient mqtt(espClient);
Preferences prefs;

bool relayState[8] = {false};

unsigned long lastHeartbeat    = 0;
unsigned long lastWifiRetry    = 0;
unsigned long lastMqttRetry    = 0;

char topicCmd[64];
char topicState[64];
char topicHeartbeat[64];
char mqttClientId[48];

// ======================== SETUP ========================
void setup() {
    Serial.begin(115200);
    Serial.println(\"\n[CFarm] Khởi động...\");

    esp_task_wdt_init(WDT_TIMEOUT_S, true);
    esp_task_wdt_add(NULL);

    for (int i = 0; i < 8; i++) {
        pinMode(RELAY_PINS[i], OUTPUT);
        digitalWrite(RELAY_PINS[i], HIGH);
    }

    snprintf(topicCmd,       sizeof(topicCmd),       \"%s/cmd\",       MQTT_TOPIC);
    snprintf(topicState,     sizeof(topicState),     \"%s/state\",     MQTT_TOPIC);
    snprintf(topicHeartbeat, sizeof(topicHeartbeat), \"%s/heartbeat\", MQTT_TOPIC);
    snprintf(mqttClientId,   sizeof(mqttClientId),   \"ESP_%s\",       DEVICE_CODE);

    restoreRelayStates();

    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    Serial.println(\"[WiFi] Đang kết nối...\");

    mqtt.setServer(MQTT_SERVER, MQTT_PORT);
    mqtt.setBufferSize(512);
    mqtt.setCallback(mqttCallback);

    setupOTA();

    Serial.println(\"[CFarm] Setup hoàn tất.\");
}

// ======================== LOOP ========================
void loop() {
    esp_task_wdt_reset();

    unsigned long now = millis();

    if (WiFi.status() != WL_CONNECTED) {
        if (now - lastWifiRetry > WIFI_RECONNECT_INTERVAL) {
            lastWifiRetry = now;
            Serial.println(\"[WiFi] Mất kết nối, thử lại...\");
            WiFi.disconnect();
            WiFi.begin(WIFI_SSID, WIFI_PASS);
        }
        return;
    }

    if (!mqtt.connected()) {
        if (now - lastMqttRetry > MQTT_RECONNECT_INTERVAL) {
            lastMqttRetry = now;
            mqttReconnect();
        }
    } else {
        mqtt.loop();
    }

    if (now - lastHeartbeat > HEARTBEAT_INTERVAL_MS) {
        lastHeartbeat = now;
        sendHeartbeat();
    }

    ArduinoOTA.handle();
}

// ======================== MQTT RECONNECT ========================
void mqttReconnect() {
    Serial.println(\"[MQTT] Đang kết nối...\");
    if (mqtt.connect(mqttClientId, MQTT_USER, MQTT_PASS)) {
        Serial.println(\"[MQTT] Đã kết nối!\");
        mqtt.subscribe(topicCmd);
        sendHeartbeat();
    }
}

// ======================== MQTT CALLBACK ========================
void mqttCallback(char* topic, byte* payload, unsigned int length) {
    StaticJsonDocument<256> doc;
    DeserializationError err = deserializeJson(doc, payload, length);
    if (err) {
        Serial.printf(\"[MQTT] JSON parse lỗi: %s\n\", err.c_str());
        return;
    }

    const char* action = doc[\"action\"] | \"\";

    if (strcmp(action, \"relay\") == 0) {
        int ch = doc[\"channel\"] | 0;
        const char* st = doc[\"state\"] | \"\";
        if (ch >= 1 && ch <= 8) {
            setRelay(ch - 1, strcmp(st, \"on\") == 0);
        }
    } else if (strcmp(action, \"all\") == 0) {
        const char* st = doc[\"state\"] | \"\";
        bool on = (strcmp(st, \"on\") == 0);
        for (int i = 0; i < 8; i++) {
            setRelay(i, on);
        }
    }
}

// ======================== SET RELAY ========================
void setRelay(int ch, bool on) {
    if (ch < 0 || ch >= 8) return;

    if (on) {
        for (int i = 0; i < 4; i++) {
            int up   = INTERLOCK[i][0] - 1;
            int down = INTERLOCK[i][1] - 1;

            if (ch == up && relayState[down]) {
                applyRelay(down, false);
                delay(INTERLOCK_DEAD_TIME_MS);
                break;
            }
            if (ch == down && relayState[up]) {
                applyRelay(up, false);
                delay(INTERLOCK_DEAD_TIME_MS);
                break;
            }
        }
    }

    applyRelay(ch, on);
}

void applyRelay(int ch, bool on) {
    if (relayState[ch] == on) return;

    digitalWrite(RELAY_PINS[ch], on ? LOW : HIGH);
    relayState[ch] = on;

    saveRelayStates();

    char buf[128];
    snprintf(buf, sizeof(buf),
        \"{\\\"device\\\":\\\"%s\\\",\\\"channel\\\":%d,\\\"state\\\":\\\"%s\\\"}\",
        DEVICE_CODE, ch + 1, on ? \"on\" : \"off\");

    if (!mqtt.publish(topicState, buf)) {
        Serial.printf(\"[MQTT] Publish state CH%d thất bại\n\", ch + 1);
    }
}

// ======================== HEARTBEAT ========================
void sendHeartbeat() {
    if (!mqtt.connected()) return;

    char buf[256];
    snprintf(buf, sizeof(buf),
        \"{\\\"device\\\":\\\"%s\\\",\\\"wifi_rssi\\\":%d,\\\"ip\\\":\\\"%s\\\",\"
        \"\\\"uptime\\\":%lu,\\\"relays\\\":[%d,%d,%d,%d,%d,%d,%d,%d]}\",
        DEVICE_CODE,
        WiFi.RSSI(),
        WiFi.localIP().toString().c_str(),
        millis() / 1000,
        relayState[0], relayState[1], relayState[2], relayState[3],
        relayState[4], relayState[5], relayState[6], relayState[7]);

    if (mqtt.publish(topicHeartbeat, buf, true)) {
        Serial.println(\"[Heartbeat] OK\");
    }
}

// ======================== NVS ========================
void saveRelayStates() {
    uint8_t packed = 0;
    for (int i = 0; i < 8; i++) {
        if (relayState[i]) packed |= (1 << i);
    }
    prefs.begin(\"relay\", false);
    prefs.putUChar(\"state\", packed);
    prefs.end();
}

void restoreRelayStates() {
    prefs.begin(\"relay\", true);
    uint8_t packed = prefs.getUChar(\"state\", 0);
    prefs.end();

    for (int i = 0; i < 8; i++) {
        relayState[i] = (packed >> i) & 1;
        digitalWrite(RELAY_PINS[i], relayState[i] ? LOW : HIGH);
    }
    Serial.printf(\"[NVS] Khôi phục relay: 0x%02X\n\", packed);
}

// ======================== OTA ========================
void setupOTA() {
    ArduinoOTA.setHostname(DEVICE_CODE);
    ArduinoOTA.setPassword(\"cfarm_ota\");

    ArduinoOTA.onStart([]() {
        Serial.println(\"[OTA] Bắt đầu cập nhật...\");
        for (int i = 0; i < 8; i++) {
            digitalWrite(RELAY_PINS[i], HIGH);
        }
    });
    ArduinoOTA.onEnd([]()   { Serial.println(\"\n[OTA] Hoàn tất!\"); });
    ArduinoOTA.onError([](ota_error_t error) {
        Serial.printf(\"[OTA] Lỗi [%u]\n\", error);
    });

    ArduinoOTA.begin();
    Serial.println(\"[OTA] Sẵn sàng.\");
}',
    description = 'Firmware tối ưu - Non-blocking, Watchdog, NVS, OTA',
    is_latest = 1
WHERE id = 1;

SELECT id, name, version, is_latest FROM device_firmwares;
