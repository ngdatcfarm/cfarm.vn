-- ============================================================
-- UPDATE FIRMWARE TEMPLATE with MQTT DEBUG
-- Run this SQL on cloud database
-- ============================================================

UPDATE device_types 
SET firmware_template = '/*
 * CFarm ESP32 Relay Controller (MQTT DEBUG)
 * ============================================================
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
    Serial.println(\"=== CFarm ESP32 Relay (MQTT DEBUG) ===\");
    for (int i = 0; i < 8; i++) {
        pinMode(RELAY_PINS[i], OUTPUT);
        digitalWrite(RELAY_PINS[i], HIGH);
    }
    connectWiFi();
    connectMqtt();
    Serial.println(\"Ready!\");
}

void loop() {
    if (!mqtt.connected()) {
        Serial.println(\"MQTT Disconnected! Reconnecting...\");
        connectMqtt();
    }
    mqtt.loop();
    if (millis() - lastHeartbeat > HEARTBEAT_INTERVAL) {
        lastHeartbeat = millis();
        sendHeartbeat();
    }
}

void connectWiFi() {
    Serial.print(\"WiFi connecting to: \"); Serial.println(WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) { 
        delay(500); Serial.print(\".\"); attempts++; 
    }
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println(\"\");
        Serial.print(\"WiFi OK! IP: \"); Serial.println(WiFi.localIP());
    } else {
        Serial.println(\"\");
        Serial.print(\"WiFi FAILED! Status: \"); Serial.println(WiFi.status());
    }
}

void connectMqtt() {
    Serial.print(\"MQTT connecting to: \"); Serial.println(MQTT_SERVER);
    Serial.print(\"MQTT port: \"); Serial.println(1883);
    Serial.print(\"MQTT user: \"); Serial.println(MQTT_USER);
    
    mqtt.setServer(MQTT_SERVER, 1883);
    mqtt.setCallback(mqttCallback);
    
    String clientId = \"ESP32_\" + String(DEVICE_CODE);
    String willTopic = String(MQTT_TOPIC) + \"/status\";
    String willPayload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"status\\\":\\\"offline\\\"}\";
    
    Serial.print(\"Client ID: \"); Serial.println(clientId);
    Serial.print(\"Will topic: \"); Serial.println(willTopic);
    
    int result = mqtt.connect(clientId.c_str(), MQTT_USER, MQTT_PASS, willTopic.c_str(), 1, true, willPayload.c_str());
    
    Serial.print(\"MQTT connect result: \"); Serial.println(result);
    Serial.print(\"MQTT state: \"); Serial.println(mqtt.state());
    
    if (mqtt.connected()) {
        Serial.println(\"MQTT CONNECTED OK!\");
        mqtt.subscribe((String(MQTT_TOPIC) + \"/command\").c_str());
        mqtt.subscribe((String(MQTT_TOPIC) + \"/set\").c_str());
        Serial.print(\"Subscribed to: \"); Serial.println(MQTT_TOPIC);
        sendHeartbeat();
    } else {
        Serial.println(\"MQTT CONNECT FAILED!\");
        Serial.println(\"Possible causes:\");
        Serial.println(\"  - Wrong MQTT_SERVER / IP\");
        Serial.println(\"  - Wrong credentials\");
        Serial.println(\"  - Port 1883 blocked by firewall\");
        Serial.println(\"  - MQTT broker not running\");
    }
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
    Serial.print(\"\n=== MQTT RECEIVED ===\");
    Serial.print(\"Topic: \"); Serial.println(topic);
    String msg;
    for (int i = 0; i < length; i++) msg += (char)payload[i];
    Serial.print(\"Payload: \"); Serial.println(msg);
    
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

void sendHeartbeat() {
    String payload = \"{\\\"device\\\":\\\"\" + String(DEVICE_CODE) + \"\\\",\\\"status\\\":\\\"online\\\",\\\"wifi_rssi\\\":\" + String(WiFi.RSSI()) + \",\\\"ip\\\":\\\"\" + WiFi.localIP().toString() + \"\\\",\\\"uptime\\\":\" + String(millis()/1000) + \"}\";
    mqtt.publish((String(MQTT_TOPIC) + \"/heartbeat\").c_str(), payload.c_str(), true);
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
    mqtt.publish((String(MQTT_TOPIC) + \"/state\").c_str(), (String(\"{\\\"device\\\":\\\"\") + String(DEVICE_CODE) + \"\\\",\\\"channel\\\":\" + String(ch+1) + \",\\\"state\\\":\\\"\" + (on ? \"on\" : \"off\") + \"\\\"}\").c_str());
}',
    mqtt_protocol = '{\"heartbeat\":{\"topic\":\"{device}/heartbeat\"},\"command\":{\"topic\":\"{device}/command\"}}'
WHERE id = 1;

SELECT id, name FROM device_types WHERE id = 1;
