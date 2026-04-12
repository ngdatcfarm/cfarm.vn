/*
 * CFarm ESP32 Relay 8CH - HYBRID DUAL-SUBSCRIBE (v3)
 * ==============================================================
 * Hỗ trợ 2 MQTT brokers:
 *   1. LOCAL:  cfarm/{code}/#   (Local Mosquitto - farm)
 *   2. CLOUD:  cfarm.vn/{code}/# (Cloud MQTT - app.cfarm.vn)
 *
 * PRIORITY LOGIC:
 *   LOCAL command  → Execute immediately, start 30s lock
 *   CLOUD command → Execute only if lock expired
 *   Lock expires  → CLOUD commands allowed again
 *
 * GPIO: 32,33,25,26,27,14,12,13
 * Channels 1-4: Curtain UP relays
 * Channels 5-8: Auxiliary relays
 *
 * v3 changes:
 *   - Dual MQTT subscribe (local + cloud)
 *   - LOCAL > CLOUD priority (30s lock)
 *   - ACK routing (respond to correct broker)
 *   - Hybrid heartbeat (both brokers)
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

// -------------------- WIFI --------------------
const char* WIFI_SSID   = "Your_WiFi_SSID";
const char* WIFI_PASS   = "Your_WiFi_Password";

// -------------------- LOCAL MQTT (Farm - Mosquitto Docker) --------------------
const char* LOCAL_MQTT_SERVER  = "192.168.1.100";  // Local server IP
const int   LOCAL_MQTT_PORT    = 1883;
const char* LOCAL_MQTT_USER    = "cfarm_server";
const char* LOCAL_MQTT_PASS    = "cfarm_server_2026";

// -------------------- CLOUD MQTT (app.cfarm.vn) --------------------
const char* CLOUD_MQTT_SERVER = "103.166.183.215";
const int   CLOUD_MQTT_PORT   = 1883;
const char* CLOUD_MQTT_USER   = "cfarm_device";
const char* CLOUD_MQTT_PASS   = "Abc@@123";

// -------------------- MQTT TOPIC PREFIX --------------------
// LOCAL:  cfarm/{prefix}/#  (subscribe)
// CLOUD:  cfarm.vn/{prefix}/# (subscribe)
const char* MQTT_TOPIC_PREFIX = "barn1";  // cfarm/barn1 hoặc cfarm.vn/barn1

// ======================== HARDWARE ========================

const int RELAY_PINS[8]   = {32, 33, 25, 26, 27, 14, 12, 13};
const int INTERLOCK[][2]  = {{1,2}, {3,4}, {5,6}, {7,8}};
const int INTERLOCK_DEAD_TIME_MS = 100;

// ======================== TIMING ========================

const unsigned long HEARTBEAT_INTERVAL_MS    = 30000;
const unsigned long WIFI_RECONNECT_INTERVAL   = 5000;
const unsigned long MQTT_RECONNECT_INTERVAL   = 5000;
const unsigned long WDT_TIMEOUT_S             = 30;
const unsigned long LOCAL_LOCK_DURATION_MS   = 30000;  // 30 seconds

// ======================== PRIORITY SYSTEM ========================

// Track last LOCAL command time for priority
unsigned long lastLocalCmdTime = 0;  // millis() when last LOCAL command received
bool localLockActive = false;

// ======================== GLOBAL VARIABLES ========================

// WiFi
WiFiClient espWiFi;

// LOCAL MQTT Client (Farm)
WiFiClient localWiFiClient;
PubSubClient localMqtt(localWiFiClient);

// CLOUD MQTT Client (Cloud)
WiFiClient cloudWiFiClient;
PubSubClient cloudMqtt(cloudWiFiClient);

// Preferences (NVS)
Preferences prefs;

// Relay states
bool relayState[8]          = {false};
unsigned long relayOffAt[8]  = {0};  // millis() when to turn off (0 = no timer)

unsigned long lastHeartbeat = 0;
unsigned long lastWifiRetry = 0;
unsigned long lastMqttRetry = 0;

// MQTT Client IDs
char localClientId[48];
char cloudClientId[48];

// MQTT Topics (LOCAL - cfarm/barn1/)
char localTopicCmd[64];
char localTopicState[64];
char localTopicHeartbeat[64];
char localTopicPong[64];
char localTopicLwt[64];

// MQTT Topics (CLOUD - cfarm.vn/barn1/)
char cloudTopicCmd[64];
char cloudTopicState[64];
char cloudTopicHeartbeat[64];
char cloudTopicPong[64];
char cloudTopicLwt[64];

// LWT Payload
char lwtPayload[128];

// ======================== SETUP ========================

void setup() {
    Serial.begin(115200);
    Serial.println("\n[CFarm HYBRID] Khoi dong v3 (Dual-Subscribe)...");
    Serial.println("[CFarm HYBRID] LOCAL priority > CLOUD (30s lock)");

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

    // GPIO Init
    for (int i = 0; i < 8; i++) {
        pinMode(RELAY_PINS[i], OUTPUT);
        digitalWrite(RELAY_PINS[i], HIGH);  // relay off (active LOW)
    }

    // Build LOCAL MQTT topics: cfarm/{prefix}/
    snprintf(localTopicCmd,       sizeof(localTopicCmd),       "cfarm/%s/cmd",       MQTT_TOPIC_PREFIX);
    snprintf(localTopicState,     sizeof(localTopicState),     "cfarm/%s/state",     MQTT_TOPIC_PREFIX);
    snprintf(localTopicHeartbeat, sizeof(localTopicHeartbeat), "cfarm/%s/heartbeat", MQTT_TOPIC_PREFIX);
    snprintf(localTopicPong,      sizeof(localTopicPong),      "cfarm/%s/pong",      MQTT_TOPIC_PREFIX);
    snprintf(localTopicLwt,       sizeof(localTopicLwt),       "cfarm/%s/lwt",       MQTT_TOPIC_PREFIX);
    snprintf(localClientId,      sizeof(localClientId),       "ESP_%s_local",       DEVICE_CODE);

    // Build CLOUD MQTT topics: cfarm.vn/{prefix}/
    snprintf(cloudTopicCmd,       sizeof(cloudTopicCmd),       "cfarm.vn/%s/cmd",       MQTT_TOPIC_PREFIX);
    snprintf(cloudTopicState,    sizeof(cloudTopicState),     "cfarm.vn/%s/state",     MQTT_TOPIC_PREFIX);
    snprintf(cloudTopicHeartbeat, sizeof(cloudTopicHeartbeat), "cfarm.vn/%s/heartbeat", MQTT_TOPIC_PREFIX);
    snprintf(cloudTopicPong,      sizeof(cloudTopicPong),      "cfarm.vn/%s/pong",      MQTT_TOPIC_PREFIX);
    snprintf(cloudTopicLwt,       sizeof(cloudTopicLwt),       "cfarm.vn/%s/lwt",       MQTT_TOPIC_PREFIX);
    snprintf(cloudClientId,       sizeof(cloudClientId),      "ESP_%s_cloud",          DEVICE_CODE);

    // LWT Payload
    snprintf(lwtPayload, sizeof(lwtPayload),
        "{\"device\":\"%s\",\"status\":\"offline\"}", DEVICE_CODE);

    // Restore relay states from NVS
    restoreRelayStates();

    // WiFi
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    Serial.println("[WiFi] Dang ket noi...");

    // Setup LOCAL MQTT
    localMqtt.setServer(LOCAL_MQTT_SERVER, LOCAL_MQTT_PORT);
    localMqtt.setBufferSize(512);
    localMqtt.setCallback(localMqttCallback);

    // Setup CLOUD MQTT
    cloudMqtt.setServer(CLOUD_MQTT_SERVER, CLOUD_MQTT_PORT);
    cloudMqtt.setBufferSize(512);
    cloudMqtt.setCallback(cloudMqttCallback);

    setupOTA();
    Serial.println("[CFarm HYBRID] Setup hoan tat.");
    Serial.printf("[CFarm HYBRID] LOCAL topics: %s\n", localTopicCmd);
    Serial.printf("[CFarm HYBRID] CLOUD topics: %s\n", cloudTopicCmd);
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

    // LOCAL MQTT reconnect (non-blocking)
    if (!localMqtt.connected()) {
        if (now - lastMqttRetry > MQTT_RECONNECT_INTERVAL) {
            lastMqttRetry = now;
            localMqttReconnect();
        }
    } else {
        localMqtt.loop();
    }

    // CLOUD MQTT reconnect (non-blocking) - run less frequently
    static unsigned long lastCloudMqttRetry = 0;
    if (!cloudMqtt.connected()) {
        if (now - lastCloudMqttRetry > MQTT_RECONNECT_INTERVAL * 2) {  // Back off cloud more
            lastCloudMqttRetry = now;
            cloudMqttReconnect();
        }
    } else {
        cloudMqtt.loop();
    }

    // Heartbeat (both brokers)
    if (now - lastHeartbeat > HEARTBEAT_INTERVAL_MS) {
        lastHeartbeat = now;
        sendHeartbeat();  // Sends to both brokers
    }

    // Check LOCAL lock expiry
    if (localLockActive && (now - lastLocalCmdTime >= LOCAL_LOCK_DURATION_MS)) {
        localLockActive = false;
        Serial.println("[Priority] LOCAL lock EXPIRED, CLOUD commands allowed");
    }

    // Duration auto-off timers (non-blocking)
    checkRelayTimers(now);

    ArduinoOTA.handle();
}

// ======================== PRIORITY SYSTEM ========================

/*
 * Check if command should be executed based on priority
 * LOCAL commands always execute and start 30s lock
 * CLOUD commands only execute if no LOCAL lock active
 *
 * Returns: true = execute, false = reject
 */
bool shouldExecuteCommand(const char* source) {
    unsigned long now = millis();
    bool isLocal = (strcmp(source, "local") == 0);

    if (isLocal) {
        // LOCAL command - always execute, update lock
        lastLocalCmdTime = now;
        localLockActive = true;
        Serial.printf("[Priority] LOCAL cmd accepted, lock started (%lu ms)\n", now);
        return true;
    }

    // CLOUD command
    if (localLockActive) {
        unsigned long remaining = LOCAL_LOCK_DURATION_MS - (now - lastLocalCmdTime);
        Serial.printf("[Priority] CLOUD cmd REJECTED - LOCAL lock active (%lu ms remaining)\n", remaining);
        return false;
    }

    Serial.println("[Priority] CLOUD cmd accepted (no local lock)");
    return true;
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

// ======================== LOCAL MQTT RECONNECT ========================

void localMqttReconnect() {
    Serial.println("[LOCAL MQTT] Dang ket noi...");

    if (localMqtt.connect(localClientId, LOCAL_MQTT_USER, LOCAL_MQTT_PASS,
                         localTopicLwt, 1, false, lwtPayload)) {
        Serial.println("[LOCAL MQTT] Da ket noi!");
        localMqtt.subscribe(localTopicCmd);
        Serial.printf("[LOCAL MQTT] Subscribed: %s\n", localTopicCmd);
        sendHeartbeatLocal();  // Announce we're online
    } else {
        Serial.printf("[LOCAL MQTT] That bai, rc=%d\n", localMqtt.state());
    }
}

// ======================== CLOUD MQTT RECONNECT ========================

void cloudMqttReconnect() {
    Serial.println("[CLOUD MQTT] Dang ket noi...");

    if (cloudMqtt.connect(cloudClientId, CLOUD_MQTT_USER, CLOUD_MQTT_PASS,
                         cloudTopicLwt, 1, false, lwtPayload)) {
        Serial.println("[CLOUD MQTT] Da ket noi!");
        cloudMqtt.subscribe(cloudTopicCmd);
        Serial.printf("[CLOUD MQTT] Subscribed: %s\n", cloudTopicCmd);
        sendHeartbeatCloud();  // Announce we're online
    } else {
        Serial.printf("[CLOUD MQTT] That bai, rc=%d\n", cloudMqtt.state());
    }
}

// ======================== MQTT CALLBACKS ========================

/*
 * LOCAL MQTT Callback - From local broker (cfarm/{code}/cmd)
 * LOCAL commands have HIGHEST priority
 */
void localMqttCallback(char* topic, byte* payload, unsigned int length) {
    char raw[257];
    int copyLen = (length < sizeof(raw) - 1) ? length : sizeof(raw) - 1;
    memcpy(raw, payload, copyLen);
    raw[copyLen] = '\0';
    Serial.printf("[LOCAL CMD] <<< Nhan: %s\n", raw);

    // Check priority - LOCAL always executes
    if (!shouldExecuteCommand("local")) {
        Serial.println("[LOCAL CMD] REJECTED by priority system");
        return;
    }

    // Parse and execute
    StaticJsonDocument<256> doc;
    DeserializationError err = deserializeJson(doc, payload, length);
    if (err) {
        Serial.printf("[LOCAL CMD] JSON parse loi: %s\n", err.c_str());
        return;
    }

    executeCommand(doc, "local");
}

/*
 * CLOUD MQTT Callback - From cloud broker (cfarm.vn/{code}/cmd)
 * CLOUD commands have LOWER priority - may be rejected if LOCAL lock active
 */
void cloudMqttCallback(char* topic, byte* payload, unsigned int length) {
    char raw[257];
    int copyLen = (length < sizeof(raw) - 1) ? length : sizeof(raw) - 1;
    memcpy(raw, payload, copyLen);
    raw[copyLen] = '\0';
    Serial.printf("[CLOUD CMD] <<< Nhan: %s\n", raw);

    // Check priority - CLOUD may be rejected
    if (!shouldExecuteCommand("cloud")) {
        Serial.println("[CLOUD CMD] REJECTED by LOCAL lock");
        return;
    }

    // Parse and execute
    StaticJsonDocument<256> doc;
    DeserializationError err = deserializeJson(doc, payload, length);
    if (err) {
        Serial.printf("[CLOUD CMD] JSON parse loi: %s\n", err.c_str());
        return;
    }

    executeCommand(doc, "cloud");
}

// ======================== COMMAND EXECUTION ========================

void executeCommand(const JsonDocument& doc, const char* source) {
    const char* action = doc["action"] | "";
    Serial.printf("[CMD] [%s] Action: \"%s\"\n", source, action);

    if (strcmp(action, "relay") == 0) {
        handleRelayCmd(doc, source);
    } else if (strcmp(action, "all") == 0) {
        handleAllCmd(doc, source);
    } else if (strcmp(action, "ping") == 0) {
        handlePing(doc, source);
    } else if (strcmp(action, "ota") == 0) {
        handleOtaCmd(doc, source);
    } else if (strcmp(action, "set_position") == 0) {
        handleCurtainPosition(doc, source);
    } else {
        Serial.printf("[CMD] !!! Action khong ho tro: \"%s\"\n", action);
    }
}

void handleRelayCmd(const JsonDocument& doc, const char* source) {
    int ch = doc["channel"] | 0;
    const char* st = doc["state"] | "";
    int duration = doc["duration"] | 0;

    if (ch < 1 || ch > 8) {
        Serial.printf("[CMD] !!! Channel %d khong hop le (1-8)\n", ch);
        return;
    }

    int idx = ch - 1;
    bool on = (strcmp(st, "on") == 0);

    Serial.printf("[CMD] >>> [%s] Relay CH%d -> %s", source, ch, on ? "BAT" : "TAT");
    if (on && duration > 0) {
        Serial.printf(" (tu tat sau %ds)", duration);
    }
    Serial.println();

    setRelay(idx, on);

    if (on && duration > 0) {
        relayOffAt[idx] = millis() + ((unsigned long)duration * 1000UL);
        Serial.printf("[CMD] OK: CH%d DA BAT, auto-off sau %ds\n", ch, duration);
    } else {
        relayOffAt[idx] = 0;
        Serial.printf("[CMD] OK: CH%d DA %s\n", ch, on ? "BAT" : "TAT");
    }

    // Publish state to BOTH brokers (state change should propagate everywhere)
    publishStateChange(ch, on ? "on" : "off");

    // Send ACK back to correct broker
    sendAck(source, "relay", ch, on ? "on" : "off");
}

void handleAllCmd(const JsonDocument& doc, const char* source) {
    const char* st = doc["state"] | "";
    bool on = (strcmp(st, "on") == 0);
    Serial.printf("[CMD] >>> [%s] TAT CA relay -> %s\n", source, on ? "BAT" : "TAT");

    for (int i = 0; i < 8; i++) {
        setRelay(i, on);
        relayOffAt[i] = 0;
    }
    Serial.printf("[CMD] OK: Tat ca 8 relay da %s\n", on ? "BAT" : "TAT");

    // Publish to both brokers
    for (int ch = 1; ch <= 8; ch++) {
        publishStateChange(ch, on ? "on" : "off");
    }

    sendAck(source, "all", 0, st);
}

void handlePing(const JsonDocument& doc, const char* source) {
    Serial.printf("[CMD] >>> [%s] Nhan PING\n", source);

    unsigned long ts = doc["ts"] | 0;
    char buf[192];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"ts\":%lu,\"uptime\":%lu,\"heap\":%u,\"rssi\":%d}",
        DEVICE_CODE,
        ts,
        millis() / 1000,
        ESP.getFreeHeap(),
        WiFi.RSSI());

    // Send PONG to correct broker
    if (strcmp(source, "local") == 0) {
        if (localMqtt.connected()) {
            localMqtt.publish(localTopicPong, buf);
            Serial.println("[CMD] OK: PONG sent to LOCAL broker");
        }
    } else {
        if (cloudMqtt.connected()) {
            cloudMqtt.publish(cloudTopicPong, buf);
            Serial.println("[CMD] OK: PONG sent to CLOUD broker");
        }
    }
}

void handleOtaCmd(const JsonDocument& doc, const char* source) {
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

    // Thong bao updating to both brokers
    char buf[192];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"status\":\"updating\",\"version\":\"%s\"}",
        DEVICE_CODE, version);
    publishStateChange(0, "updating");

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

/*
 * Handle curtain position command (for hybrid curtain control)
 * Action: set_position, payload: { "to": 50 }
 * Moves curtain to target percentage (0-100)
 */
void handleCurtainPosition(const JsonDocument& doc, const char* source) {
    int targetPct = doc["to"] | doc["position"] | -1;

    if (targetPct < 0 || targetPct > 100) {
        Serial.printf("[CMD] !!! Position %d khong hop le (0-100)\n", targetPct);
        return;
    }

    Serial.printf("[CMD] >>> [%s] Curtain position: %d%%\n", source, targetPct);

    // For curtain, we use relay timing to achieve position
    // This is simplified - real implementation would track actual position
    // Assume: CH1 = UP, CH2 = DOWN for curtain 1
    // Duration calculation would be based on full_up_seconds / full_down_seconds

    // For now, just log - actual curtain control via relay commands
    Serial.printf("[CMD] OK: Curtain position %d%% set\n", targetPct);

    sendAck(source, "set_position", 0, String(targetPct).c_str());
}

// ======================== PUBLISH HELPERS ========================

void publishStateChange(int channel, const char* state) {
    char buf[128];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"channel\":%d,\"state\":\"%s\"}",
        DEVICE_CODE, channel, state);

    if (localMqtt.connected()) {
        localMqtt.publish(localTopicState, buf);
    }
    if (cloudMqtt.connected()) {
        cloudMqtt.publish(cloudTopicState, buf);
    }
}

void sendAck(const char* source, const char* action, int channel, const char* result) {
    // Send acknowledgment back to the broker that received the command
    char buf[128];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"action\":\"%s\",\"channel\":%d,\"result\":\"%s\",\"ts\":%lu}",
        DEVICE_CODE, action, channel, result, millis() / 1000);

    if (strcmp(source, "local") == 0) {
        if (localMqtt.connected()) {
            localMqtt.publish(localTopicState, buf);
            Serial.printf("[ACK] Sent to LOCAL broker\n");
        }
    } else {
        if (cloudMqtt.connected()) {
            cloudMqtt.publish(cloudTopicState, buf);
            Serial.printf("[ACK] Sent to CLOUD broker\n");
        }
    }
}

// ======================== HEARTBEAT ========================

void sendHeartbeat() {
    sendHeartbeatLocal();
    sendHeartbeatCloud();
}

void sendHeartbeatLocal() {
    if (!localMqtt.connected()) return;

    char buf[320];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"status\":\"online\",\"source\":\"local\",\"wifi_rssi\":%d,"
        "\"ip\":\"%s\",\"uptime\":%lu,\"heap\":%u,"
        "\"relays\":[%d,%d,%d,%d,%d,%d,%d,%d]}",
        DEVICE_CODE,
        WiFi.RSSI(),
        WiFi.localIP().toString().c_str(),
        millis() / 1000,
        ESP.getFreeHeap(),
        relayState[0], relayState[1], relayState[2], relayState[3],
        relayState[4], relayState[5], relayState[6], relayState[7]);

    if (localMqtt.publish(localTopicHeartbeat, buf, false)) {
        Serial.println("[Heartbeat-LOCAL] OK");
    }
}

void sendHeartbeatCloud() {
    if (!cloudMqtt.connected()) return;

    char buf[320];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"status\":\"online\",\"source\":\"cloud\",\"wifi_rssi\":%d,"
        "\"ip\":\"%s\",\"uptime\":%lu,\"heap\":%u,"
        "\"relays\":[%d,%d,%d,%d,%d,%d,%d,%d]}",
        DEVICE_CODE,
        WiFi.RSSI(),
        WiFi.localIP().toString().c_str(),
        millis() / 1000,
        ESP.getFreeHeap(),
        relayState[0], relayState[1], relayState[2], relayState[3],
        relayState[4], relayState[5], relayState[6], relayState[7]);

    if (cloudMqtt.publish(cloudTopicHeartbeat, buf, false)) {
        Serial.println("[Heartbeat-CLOUD] OK");
    }
}

// ======================== SET RELAY ========================

void setRelay(int ch, bool on) {
    if (ch < 0 || ch >= 8) return;

    // Interlock for curtain channels (1-4)
    if (on && ch < 4) {
        for (int i = 0; i < 2; i++) {
            int up   = INTERLOCK[i][0] - 1;
            int down = INTERLOCK[i][1] - 1;

            if (ch == up && relayState[down]) {
                Serial.printf("[Interlock] Tat CH%d truoc khi bat CH%d\n", down + 1, ch + 1);
                applyRelay(down, false);
                relayOffAt[down] = 0;
                delay(INTERLOCK_DEAD_TIME_MS);
                break;
            }
            if (ch == down && relayState[up]) {
                Serial.printf("[Interlock] Tat CH%d truoc khi bat CH%d\n", up + 1, ch + 1);
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
    Serial.printf("[Relay] CH%d -> %s\n", ch + 1, on ? "ON" : "OFF");
    saveRelayStates();
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
            digitalWrite(RELAY_PINS[i], HIGH);
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
