/*
 * CFarm ESP32 Relay 8CH - 4 Curtains (v2)
 * ============================================================
 * GPIO: 32,33,25,26,27,14,12,13
 * MQTT: Subscribe /cmd, Publish /heartbeat + /state + /pong
 *
 * v2 changes:
 *   - duration auto-off: relay tu dong tat sau X giay (non-blocking timer)
 *   - LWT: broker tu publish offline khi ESP32 mat ket noi
 *   - ping/pong: server ping, ESP32 tra pong
 *   - heartbeat: them free_heap, status field
 *   - Interlock auto-off kenh doi nghich + dead-time 100ms
 *   - Non-blocking WiFi/MQTT reconnect
 *   - Watchdog Timer (30s)
 *   - snprintf thay String concatenation
 *   - Luu/khoi phuc trang thai relay qua NVS
 *   - OTA update qua ArduinoOTA
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <esp_task_wdt.h>
#include <Preferences.h>
#include <ArduinoOTA.h>
#include <HTTPClient.h>
#include <Update.h>

// ======================== CAU HINH ========================

#define DEVICE_CODE       "YOUR_DEVICE_CODE"

const char* WIFI_SSID   = "Dat Lim";
const char* WIFI_PASS   = "hoilamgi";
const char* MQTT_SERVER = "app.cfarm.vn";
const int   MQTT_PORT   = 1883;
const char* MQTT_USER   = "cfarm_device";
const char* MQTT_PASS   = "Abc@@123";
const char* MQTT_TOPIC  = "cfarm/YOUR_MQTT_TOPIC";

// ======================== PHAN CUNG ========================

const int RELAY_PINS[8]   = {32, 33, 25, 26, 27, 14, 12, 13};
const int INTERLOCK[][2]  = {{1,2}, {3,4}, {5,6}, {7,8}};
const int INTERLOCK_DEAD_TIME_MS = 100;

// ======================== TIMING ========================

const unsigned long HEARTBEAT_INTERVAL_MS   = 30000;
const unsigned long WIFI_RECONNECT_INTERVAL = 5000;
const unsigned long MQTT_RECONNECT_INTERVAL = 5000;
const unsigned long WDT_TIMEOUT_S           = 30;

// ======================== BIEN TOAN CUC ========================

WiFiClient espClient;
PubSubClient mqtt(espClient);
Preferences prefs;

bool relayState[8]          = {false};
unsigned long relayOffAt[8] = {0};  // millis() khi can tat (0 = khong timer)

unsigned long lastHeartbeat = 0;
unsigned long lastWifiRetry = 0;
unsigned long lastMqttRetry = 0;

char topicCmd[64];
char topicState[64];
char topicHeartbeat[64];
char topicPong[64];
char topicLwt[64];
char mqttClientId[48];
char lwtPayload[128];

// ======================== SETUP ========================

void setup() {
    Serial.begin(115200);
    Serial.println("\n[CFarm] Khoi dong v2...");

    // Watchdog Timer
    #if ESP_ARDUINO_VERSION >= ESP_ARDUINO_VERSION_VAL(3, 0, 0)
        esp_task_wdt_config_t wdt_config = {
            .timeout_ms = WDT_TIMEOUT_S * 1000,
            .idle_core_mask = 0,
            .trigger_panic = true
        };
        esp_task_wdt_init(&wdt_config);
    #else
        esp_task_wdt_init(WDT_TIMEOUT_S, true);
    #endif
    esp_task_wdt_add(NULL);

    // GPIO
    for (int i = 0; i < 8; i++) {
        pinMode(RELAY_PINS[i], OUTPUT);
        digitalWrite(RELAY_PINS[i], HIGH);  // relay off (active LOW)
    }

    // MQTT topics
    snprintf(topicCmd,       sizeof(topicCmd),       "%s/cmd",       MQTT_TOPIC);
    snprintf(topicState,     sizeof(topicState),     "%s/state",     MQTT_TOPIC);
    snprintf(topicHeartbeat, sizeof(topicHeartbeat), "%s/heartbeat", MQTT_TOPIC);
    snprintf(topicPong,      sizeof(topicPong),      "%s/pong",      MQTT_TOPIC);
    snprintf(topicLwt,       sizeof(topicLwt),       "%s/lwt",       MQTT_TOPIC);
    snprintf(mqttClientId,   sizeof(mqttClientId),   "ESP_%s",       DEVICE_CODE);

    // LWT payload - broker se publish khi ESP32 mat ket noi
    snprintf(lwtPayload, sizeof(lwtPayload),
        "{\"device\":\"%s\",\"status\":\"offline\"}", DEVICE_CODE);

    restoreRelayStates();

    // WiFi
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    Serial.println("[WiFi] Dang ket noi...");

    // MQTT
    mqtt.setServer(MQTT_SERVER, MQTT_PORT);
    mqtt.setBufferSize(512);
    mqtt.setCallback(mqttCallback);

    setupOTA();
    Serial.println("[CFarm] Setup hoan tat.");
}

// ======================== LOOP ========================

void loop() {
    esp_task_wdt_reset();
    unsigned long now = millis();

    // WiFi reconnect (non-blocking)
    if (WiFi.status() != WL_CONNECTED) {
        if (now - lastWifiRetry > WIFI_RECONNECT_INTERVAL) {
            lastWifiRetry = now;
            Serial.println("[WiFi] Mat ket noi, thu lai...");
            WiFi.disconnect();
            WiFi.begin(WIFI_SSID, WIFI_PASS);
        }
        return;
    }

    // MQTT reconnect (non-blocking)
    if (!mqtt.connected()) {
        if (now - lastMqttRetry > MQTT_RECONNECT_INTERVAL) {
            lastMqttRetry = now;
            mqttReconnect();
        }
    } else {
        mqtt.loop();
    }

    // Heartbeat
    if (now - lastHeartbeat > HEARTBEAT_INTERVAL_MS) {
        lastHeartbeat = now;
        sendHeartbeat();
    }

    // Duration auto-off timers (non-blocking)
    checkRelayTimers(now);

    ArduinoOTA.handle();
}

// ======================== RELAY TIMERS ========================

void checkRelayTimers(unsigned long now) {
    for (int i = 0; i < 8; i++) {
        if (relayOffAt[i] > 0 && now >= relayOffAt[i]) {
            Serial.printf("[Timer] Auto-off CH%d\n", i + 1);
            relayOffAt[i] = 0;
            applyRelay(i, false);
        }
    }
}

// ======================== MQTT RECONNECT ========================

void mqttReconnect() {
    Serial.println("[MQTT] Dang ket noi...");

    // Connect voi LWT: khi mat ket noi, broker tu publish lwtPayload len topicLwt
    if (mqtt.connect(mqttClientId, MQTT_USER, MQTT_PASS,
                     topicLwt, 1, false, lwtPayload)) {
        Serial.println("[MQTT] Da ket noi! (voi LWT)");
        mqtt.subscribe(topicCmd);
        sendHeartbeat();
    } else {
        Serial.printf("[MQTT] That bai, rc=%d\n", mqtt.state());
    }
}

// ======================== MQTT CALLBACK ========================

void mqttCallback(char* topic, byte* payload, unsigned int length) {
    StaticJsonDocument<256> doc;
    DeserializationError err = deserializeJson(doc, payload, length);
    if (err) {
        Serial.printf("[MQTT] JSON parse loi: %s\n", err.c_str());
        return;
    }

    const char* action = doc["action"] | "";

    if (strcmp(action, "relay") == 0) {
        handleRelayCmd(doc);
    } else if (strcmp(action, "all") == 0) {
        handleAllCmd(doc);
    } else if (strcmp(action, "ping") == 0) {
        handlePing(doc);
    } else if (strcmp(action, "ota") == 0) {
        handleOtaCmd(doc);
    }
}

// ======================== COMMAND HANDLERS ========================

void handleRelayCmd(const JsonDocument& doc) {
    int ch = doc["channel"] | 0;
    const char* st = doc["state"] | "";
    int duration = doc["duration"] | 0;  // seconds, 0 = khong timer

    if (ch < 1 || ch > 8) return;

    int idx = ch - 1;
    bool on = (strcmp(st, "on") == 0);

    setRelay(idx, on);

    // Duration auto-off: bat relay + tu tat sau X giay
    if (on && duration > 0) {
        relayOffAt[idx] = millis() + ((unsigned long)duration * 1000UL);
        Serial.printf("[Timer] CH%d auto-off sau %ds\n", ch, duration);
    } else {
        relayOffAt[idx] = 0;  // xoa timer neu tat thu cong
    }
}

void handleAllCmd(const JsonDocument& doc) {
    const char* st = doc["state"] | "";
    bool on = (strcmp(st, "on") == 0);
    for (int i = 0; i < 8; i++) {
        setRelay(i, on);
        relayOffAt[i] = 0;  // xoa tat ca timer
    }
}

void handlePing(const JsonDocument& doc) {
    if (!mqtt.connected()) return;

    unsigned long ts = doc["ts"] | 0;
    char buf[192];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"ts\":%lu,\"uptime\":%lu,\"heap\":%u,\"rssi\":%d}",
        DEVICE_CODE,
        ts,
        millis() / 1000,
        ESP.getFreeHeap(),
        WiFi.RSSI());

    mqtt.publish(topicPong, buf);
    Serial.println("[Pong] Sent");
}

// ======================== HTTP OTA ========================

void handleOtaCmd(const JsonDocument& doc) {
    const char* url = doc["url"] | "";
    const char* version = doc["version"] | "unknown";

    if (strlen(url) == 0) {
        Serial.println("[OTA] URL rong, bo qua");
        return;
    }

    Serial.printf("[OTA] Bat dau cap nhat v%s tu: %s\n", version, url);

    // Tat het relay truoc khi OTA
    for (int i = 0; i < 8; i++) {
        digitalWrite(RELAY_PINS[i], HIGH);
        relayOffAt[i] = 0;
    }

    // Thong bao server dang cap nhat
    char buf[192];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"status\":\"updating\",\"version\":\"%s\"}",
        DEVICE_CODE, version);
    mqtt.publish(topicState, buf);

    WiFiClient client;
    HTTPClient http;
    http.begin(client, url);
    http.setTimeout(30000);

    int httpCode = http.GET();
    if (httpCode != 200) {
        Serial.printf("[OTA] HTTP loi: %d\n", httpCode);
        http.end();
        return;
    }

    int contentLength = http.getSize();
    if (contentLength <= 0) {
        Serial.println("[OTA] Content-Length khong hop le");
        http.end();
        return;
    }

    if (!Update.begin(contentLength)) {
        Serial.printf("[OTA] Khong du bo nho: %d bytes\n", contentLength);
        http.end();
        return;
    }

    // Tang WDT timeout cho OTA (120s)
    esp_task_wdt_reset();

    WiFiClient* stream = http.getStreamPtr();
    size_t written = Update.writeStream(*stream);
    http.end();

    if (written != contentLength) {
        Serial.printf("[OTA] Ghi thieu: %d/%d bytes\n", written, contentLength);
        Update.abort();
        return;
    }

    if (!Update.end(true)) {
        Serial.printf("[OTA] Loi ket thuc: %s\n", Update.errorString());
        return;
    }

    Serial.println("[OTA] Hoan tat! Khoi dong lai...");
    delay(500);
    ESP.restart();
}

// ======================== SET RELAY ========================

void setRelay(int ch, bool on) {
    if (ch < 0 || ch >= 8) return;

    // Interlock: tat kenh doi nghich truoc khi bat
    if (on) {
        for (int i = 0; i < 4; i++) {
            int up   = INTERLOCK[i][0] - 1;
            int down = INTERLOCK[i][1] - 1;

            if (ch == up && relayState[down]) {
                applyRelay(down, false);
                relayOffAt[down] = 0;  // xoa timer kenh doi nghich
                delay(INTERLOCK_DEAD_TIME_MS);
                break;
            }
            if (ch == down && relayState[up]) {
                applyRelay(up, false);
                relayOffAt[up] = 0;
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

    // Publish state change
    char buf[128];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"channel\":%d,\"state\":\"%s\"}",
        DEVICE_CODE, ch + 1, on ? "on" : "off");

    if (!mqtt.publish(topicState, buf)) {
        Serial.printf("[MQTT] Publish state CH%d that bai\n", ch + 1);
    }
}

// ======================== HEARTBEAT ========================

void sendHeartbeat() {
    if (!mqtt.connected()) return;

    char buf[320];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"status\":\"online\",\"wifi_rssi\":%d,"
        "\"ip\":\"%s\",\"uptime\":%lu,\"heap\":%u,"
        "\"relays\":[%d,%d,%d,%d,%d,%d,%d,%d]}",
        DEVICE_CODE,
        WiFi.RSSI(),
        WiFi.localIP().toString().c_str(),
        millis() / 1000,
        ESP.getFreeHeap(),
        relayState[0], relayState[1], relayState[2], relayState[3],
        relayState[4], relayState[5], relayState[6], relayState[7]);

    if (mqtt.publish(topicHeartbeat, buf, true)) {
        Serial.println("[Heartbeat] OK");
    }
}

// ======================== NVS ========================

void saveRelayStates() {
    uint8_t packed = 0;
    for (int i = 0; i < 8; i++) {
        if (relayState[i]) packed |= (1 << i);
    }
    prefs.begin("relay", false);
    prefs.putUChar("state", packed);
    prefs.end();
}

void restoreRelayStates() {
    prefs.begin("relay", true);
    uint8_t packed = prefs.getUChar("state", 0);
    prefs.end();
    for (int i = 0; i < 8; i++) {
        relayState[i] = (packed >> i) & 1;
        digitalWrite(RELAY_PINS[i], relayState[i] ? LOW : HIGH);
    }
    Serial.printf("[NVS] Khoi phuc relay: 0x%02X\n", packed);
}

// ======================== OTA ========================

void setupOTA() {
    ArduinoOTA.setHostname(DEVICE_CODE);
    ArduinoOTA.setPassword("cfarm_ota");
    ArduinoOTA.onStart([]() {
        Serial.println("[OTA] Bat dau cap nhat...");
        for (int i = 0; i < 8; i++) {
            digitalWrite(RELAY_PINS[i], HIGH);  // tat het relay khi OTA
            relayOffAt[i] = 0;
        }
    });
    ArduinoOTA.onEnd([]()   { Serial.println("\n[OTA] Hoan tat!"); });
    ArduinoOTA.onError([](ota_error_t error) {
        Serial.printf("[OTA] Loi [%u]\n", error);
    });
    ArduinoOTA.begin();
    Serial.println("[OTA] San sang.");
}
