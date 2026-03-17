-- ============================================================
-- UPDATE FIRMWARE TEMPLATE to match ESP32 firmware
-- Run this SQL on cloud database
-- ============================================================

-- Update device_types firmware template for ESP32 Relay 8 kênh (id=1)
-- New firmware matches the actual ESP32 firmware running:
-- - Subscribe: /command and /set
-- - Format: {"action": "relay", "channel": 1, "state": "on"} or {"action": "all", "state": "on"}
-- ============================================================

UPDATE device_types 
SET firmware_template = '/*
 * CFarm ESP32 Relay Controller
 * ================================
 * MQTT PROTOCOL (bat buoc):
 *
 * 1. HEARTBEAT - gui moi 30s len: {mqtt_topic}/heartbeat (retained=true)
 *    {\"device\":\"DEVICE_CODE\",\"status\":\"online\",\"uptime\":INT,
 *     \"wifi_rssi\":INT,\"ip\":\"STRING\",\"heap\":INT,\"version\":\"STRING\",
 *     \"relays\":[0,1,0,...]}
 *
 * 2. STATE - gui khi relay thay doi len: {mqtt_topic}/state
 *    {\"device\":\"DEVICE_CODE\",\"channel\":INT,\"state\":\"on|off\"}
 *
 * 3. COMMAND - nhan tu server tai:
 *    {mqtt_topic}/command  hoac  {mqtt_topic}/set
 *    Format: {\"action\":\"relay\",\"channel\":1,\"state\":\"on\"}
 *            {\"action\":\"all\",\"state\":\"on|off\"}
 *
 * Last Will Testament: topic=heartbeat, payload={\"device\":\"...\",\"status\":\"offline\"}
 * ================================
 * Chi sua: WIFI_SSID, WIFI_PASSWORD, DEVICE_CODE, MQTT_TOPIC, INTERLOCK_PAIRS
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
    Serial.println(\"Ready!\");
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
    Serial.print(\"Connecting to WiFi\");
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print(\".\"); }
    Serial.println(\"\"); Serial.print(\"IP: \"); Serial.println(WiFi.localIP());
}

void connectMqtt() {
    mqtt.setServer(MQTT_SERVER, 1883);
    mqtt.setCallback(mqttCallback);
    String clientId = \"ESP32_\" + String(DEVICE_CODE);
    String willTopic = String(MQTT_TOPIC) + \"/status\";
    String willPayload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"status\\\":\\\"offline\\\"}\";
    while (!mqtt.connected()) {
        if (mqtt.connect(clientId.c_str(), MQTT_USER, MQTT_PASS, willTopic.c_str(), 1, true, willPayload.c_str())) {
            Serial.println(\"MQTT connected!\");
            mqtt.subscribe((String(MQTT_TOPIC) + \"/command\").c_str());
            mqtt.subscribe((String(MQTT_TOPIC) + \"/set\").c_str());
            sendHeartbeat();
        } else { delay(1000); }
    }
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
    String msg; for (int i = 0; i < length; i++) msg += (char)payload[i];
    StaticJsonDocument<256> doc;
    DeserializationError error = deserializeJson(doc, msg);
    if (error) return;
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

void sendHeartbeat() {
    String payload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"status\\\":\\\"online\\\",\\\"wifi_rssi\\\":\" + String(WiFi.RSSI()) + \",\\\"ip\\\":\\\"\" + WiFi.localIP().toString() + \"\\\",\\\"uptime\\\":\" + String(millis()/1000) + \",\\\"heap\\\":\" + String(ESP.getFreeHeap()) + \"}\";
    String heartbeatTopic = String(MQTT_TOPIC) + \"/heartbeat\";
    mqtt.publish(heartbeatTopic.c_str(), payload.c_str(), true);
}

void setRelay(int ch, bool on) {
    if (ch < 0 || ch >= 8) return;
    if (on) {
        for (int i = 0; i < NUM_INTERLOCKS; i++) {
            int up = INTERLOCK_PAIRS[i][0] - 1;
            int down = INTERLOCK_PAIRS[i][1] - 1;
            if ((ch == up && relayState[down]) || (ch == down && relayState[up])) return;
        }
    }
    digitalWrite(RELAY_PINS[ch], on ? LOW : HIGH);
    relayState[ch] = on;
    String payload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"channel\\\":\" + String(ch+1) + \",\\\"state\\\":\\\"\" + String(on?\"on\":\"off\") + \"\\\"}\";
    mqtt.publish((String(MQTT_TOPIC) + \"/state\").c_str(), payload.c_str());
}',
    mqtt_protocol = '{\"heartbeat\":{\"topic\":\"{device}/heartbeat\",\"interval_s\":30},\"state\":{\"topic\":\"{device}/state\"},\"command\":{\"topic\":\"{device}/command\"},\"set\":{\"topic\":\"{device}/set\"}}'
WHERE id = 1;

-- ============================================================
-- Verify the update
-- ============================================================
SELECT id, name, device_class, firmware_version, mqtt_protocol 
FROM device_types 
WHERE id = 1;
