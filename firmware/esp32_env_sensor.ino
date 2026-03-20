/*
 * CFarm ESP32 ENV Sensor (v1)
 * ============================================================
 * Cam bien moi truong chuong nuoi:
 *   - SHT40 (I2C 0x44) : Nhiet do + Do am
 *   - GY30 / BH1750 (I2C 0x23) : Cuong do anh sang (lux)
 *   - MQ137 (Analog) : Khi NH3 (amoniac)
 *   - MQ135 (Analog) : Chat luong khong khi (CO2, VOC)
 *
 * MQTT publish:
 *   - {MQTT_TOPIC}/env        : Du lieu cam bien moi 5 phut
 *   - {MQTT_TOPIC}/heartbeat  : Trang thai thiet bi moi 30s
 *   - {MQTT_TOPIC}/pong       : Phan hoi ping tu server
 *   - {MQTT_TOPIC}/lwt        : Broker tu publish khi mat ket noi
 *
 * MQTT subscribe:
 *   - {MQTT_TOPIC}/cmd : Nhan lenh tu server (ping, ota, config)
 *
 * GPIO:
 *   - SDA=21, SCL=22 (I2C)
 *   - MQ137=34, MQ135=35 (ADC)
 *
 * Thu vien can cai (Arduino Library Manager):
 *   - PubSubClient (Nick O'Leary)
 *   - ArduinoJson (Benoit Blanchon)
 *   - Adafruit SHT4x
 *   - BH1750 (Christopher Laws)
 */

#include <WiFi.h>
#include <Wire.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <esp_task_wdt.h>
#include <ArduinoOTA.h>
#include <HTTPClient.h>
#include <Update.h>
#include <Adafruit_SHT4x.h>
#include <BH1750.h>

// ======================== CAU HINH ========================

#define DEVICE_CODE       "YOUR_DEVICE_CODE"

const char* WIFI_SSID   = "Dat Lim";
const char* WIFI_PASS   = "hoilamgi";
const char* MQTT_SERVER = "app.cfarm.vn";
const int   MQTT_PORT   = 1883;
const char* MQTT_USER   = "cfarm_device";
const char* MQTT_PASS   = "Abc@@123";
// MQTT_TOPIC phai KHOP voi devices.mqtt_topic trong DB
// Vi du: DB co mqtt_topic = "cfarm/barn1/0020" thi dat DUNG nhu vay
const char* MQTT_TOPIC  = "YOUR_MQTT_TOPIC";

// ======================== PHAN CUNG ========================

// I2C (SHT40 + GY30)
const int I2C_SDA = 21;
const int I2C_SCL = 22;

// Analog sensors
const int MQ137_PIN = 34;   // NH3 (amoniac)
const int MQ135_PIN = 35;   // CO2 / chat luong khi

// MQ sensor calibration
// Dien ap khi khong khi sach (can chinh bang cach doc raw khi ngoai troi)
// Sau 24-48h warm-up, doc gia tri raw ngoai troi sach -> dat vao day
const float MQ137_R0 = 1.0;  // R0 cho MQ137 (chinh sau khi warm-up)
const float MQ135_R0 = 1.0;  // R0 cho MQ135 (chinh sau khi warm-up)
const float MQ_RL    = 10.0; // Load resistor (kOhm) tren module

// ======================== TIMING ========================

const unsigned long ENV_INTERVAL_MS       = 300000;  // 5 phut gui du lieu cam bien
const unsigned long HEARTBEAT_INTERVAL_MS = 30000;   // 30s heartbeat
const unsigned long WIFI_RECONNECT_MS     = 5000;
const unsigned long MQTT_RECONNECT_MS     = 5000;
const unsigned long WDT_TIMEOUT_S         = 30;
const unsigned long SENSOR_WARMUP_MS      = 60000;   // 60s cho MQ warm-up sau boot

// ADC sampling
const int ADC_SAMPLES = 32;           // So lan doc ADC de lay trung binh
const int ADC_SAMPLE_DELAY_MS = 10;   // Delay giua cac lan doc

// ======================== BIEN TOAN CUC ========================

WiFiClient espClient;
PubSubClient mqtt(espClient);

Adafruit_SHT4x sht40;
BH1750 bh1750;

bool sht40_ok   = false;
bool bh1750_ok  = false;
bool warmup_done = false;

unsigned long lastEnvSend   = 0;
unsigned long lastHeartbeat = 0;
unsigned long lastWifiRetry = 0;
unsigned long lastMqttRetry = 0;
unsigned long bootTime      = 0;

// Du lieu cam bien moi nhat (NAN = chua doc duoc / khong co sensor)
float lastTemp     = NAN;
float lastHumidity = NAN;
float lastLux      = NAN;
float lastNH3_ppm  = NAN;
float lastCO2_ppm  = NAN;
int   lastMQ137raw = -1;
int   lastMQ135raw = -1;
int   envSendCount = 0;

char topicCmd[64];
char topicEnv[64];
char topicHeartbeat[64];
char topicPong[64];
char topicLwt[64];
char mqttClientId[48];
char lwtPayload[128];

// ======================== SETUP ========================

void setup() {
    Serial.begin(115200);
    Serial.println("\n[CFarm ENV] Khoi dong v1...");

    bootTime = millis();

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

    // ADC
    analogReadResolution(12);  // 0-4095
    analogSetAttenuation(ADC_11db);  // 0-3.3V range

    // I2C
    Wire.begin(I2C_SDA, I2C_SCL);

    // SHT40
    if (sht40.begin(&Wire)) {
        sht40.setPrecision(SHT4X_HIGH_PRECISION);
        sht40.setHeater(SHT4X_NO_HEATER);
        sht40_ok = true;
        Serial.println("[SHT40] OK");
    } else {
        Serial.println("[SHT40] !!! Khong tim thay sensor");
    }

    // BH1750 (GY30)
    if (bh1750.begin(BH1750::CONTINUOUS_HIGH_RES_MODE, 0x23, &Wire)) {
        bh1750_ok = true;
        Serial.println("[BH1750] OK");
    } else {
        Serial.println("[BH1750] !!! Khong tim thay sensor");
    }

    // MQTT topics
    snprintf(topicCmd,       sizeof(topicCmd),       "%s/cmd",       MQTT_TOPIC);
    snprintf(topicEnv,       sizeof(topicEnv),       "%s/env",       MQTT_TOPIC);
    snprintf(topicHeartbeat, sizeof(topicHeartbeat), "%s/heartbeat", MQTT_TOPIC);
    snprintf(topicPong,      sizeof(topicPong),      "%s/pong",      MQTT_TOPIC);
    snprintf(topicLwt,       sizeof(topicLwt),       "%s/lwt",       MQTT_TOPIC);
    snprintf(mqttClientId,   sizeof(mqttClientId),   "ESP_%s",       DEVICE_CODE);

    // LWT payload
    snprintf(lwtPayload, sizeof(lwtPayload),
        "{\"device\":\"%s\",\"status\":\"offline\"}", DEVICE_CODE);

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
    Serial.println("[CFarm ENV] Setup hoan tat.");
    Serial.printf("[CFarm ENV] MQ warm-up: %lu giay...\n", SENSOR_WARMUP_MS / 1000);
}

// ======================== LOOP ========================

void loop() {
    esp_task_wdt_reset();
    unsigned long now = millis();

    // Kiem tra MQ warm-up
    if (!warmup_done && (now - bootTime) >= SENSOR_WARMUP_MS) {
        warmup_done = true;
        Serial.println("[MQ] Warm-up hoan tat, bat dau doc cam bien khi");
    }

    // WiFi reconnect (non-blocking)
    if (WiFi.status() != WL_CONNECTED) {
        if (now - lastWifiRetry > WIFI_RECONNECT_MS) {
            lastWifiRetry = now;
            Serial.println("[WiFi] Mat ket noi, thu lai...");
            WiFi.disconnect();
            WiFi.begin(WIFI_SSID, WIFI_PASS);
        }
        return;
    }

    // MQTT reconnect (non-blocking)
    if (!mqtt.connected()) {
        if (now - lastMqttRetry > MQTT_RECONNECT_MS) {
            lastMqttRetry = now;
            mqttReconnect();
        }
    } else {
        mqtt.loop();
    }

    // Gui du lieu cam bien
    if (now - lastEnvSend > ENV_INTERVAL_MS) {
        lastEnvSend = now;
        readAndSendEnv();
    }

    // Heartbeat
    if (now - lastHeartbeat > HEARTBEAT_INTERVAL_MS) {
        lastHeartbeat = now;
        sendHeartbeat();
    }

    ArduinoOTA.handle();
}

// ======================== DOC CAM BIEN ========================

/**
 * Doc ADC nhieu lan va lay trung binh (loc nhieu)
 */
int readADCAvg(int pin) {
    long sum = 0;
    for (int i = 0; i < ADC_SAMPLES; i++) {
        sum += analogRead(pin);
        delay(ADC_SAMPLE_DELAY_MS);
    }
    return (int)(sum / ADC_SAMPLES);
}

/**
 * Tinh nong do khi tu gia tri ADC
 * Cong thuc: Rs/R0 ratio -> tra bang -> ppm
 *
 * MQ137: NH3 (amoniac) - pho bien trong chuong nuoi
 *   - Range: 5-200 ppm
 *   - Nguong canh bao: >25 ppm
 *
 * MQ135: CO2 / VOC
 *   - Range: 10-1000 ppm
 *   - Nguong canh bao: >1000 ppm CO2
 */
float calcMQppm(int rawADC, float R0, float a, float b) {
    if (rawADC <= 0 || R0 <= 0) return 0;

    float voltage = (rawADC / 4095.0) * 3.3;
    if (voltage <= 0.01) return 0;  // Sensor chua san sang

    // Rs = RL * (Vc - Vout) / Vout
    float Rs = MQ_RL * (3.3 - voltage) / voltage;
    float ratio = Rs / R0;

    // ppm = a * (Rs/R0)^b (tham so tu datasheet/calibration)
    float ppm = a * pow(ratio, b);
    return ppm;
}

/**
 * Doc tat ca cam bien va gui len MQTT
 */
void readAndSendEnv() {
    Serial.println("[ENV] === Doc cam bien ===");

    // SHT40: Nhiet do + Do am
    if (sht40_ok) {
        sensors_event_t hum, temp;
        if (sht40.getEvent(&hum, &temp)) {
            lastTemp = temp.temperature;
            lastHumidity = hum.relative_humidity;
            Serial.printf("[SHT40] Temp=%.1f°C  Hum=%.1f%%\n", lastTemp, lastHumidity);
        } else {
            Serial.println("[SHT40] !!! Doc loi");
        }
    }

    // BH1750: Anh sang
    if (bh1750_ok) {
        float lux = bh1750.readLightLevel();
        if (lux >= 0) {
            lastLux = lux;
            Serial.printf("[BH1750] Lux=%.1f\n", lastLux);
        } else {
            Serial.println("[BH1750] !!! Doc loi");
        }
    }

    // MQ sensors (chi doc sau warm-up)
    // ADC floating (khong cam sensor) thuong doc ~0 hoac >4000 (nhieu)
    // Sensor that: raw 100-3900 khi co tai (dien tro heater keo dong)
    if (warmup_done) {
        // MQ137 - NH3
        lastMQ137raw = readADCAvg(MQ137_PIN);
        if (lastMQ137raw >= 100 && lastMQ137raw <= 3900) {
            lastNH3_ppm = calcMQppm(lastMQ137raw, MQ137_R0, 102.2, -2.473);
            Serial.printf("[MQ137] Raw=%d  NH3=%.1f ppm\n", lastMQ137raw, lastNH3_ppm);
        } else {
            lastNH3_ppm = NAN;
            Serial.printf("[MQ137] Raw=%d — khong co sensor (floating)\n", lastMQ137raw);
        }

        // MQ135 - CO2
        lastMQ135raw = readADCAvg(MQ135_PIN);
        if (lastMQ135raw >= 100 && lastMQ135raw <= 3900) {
            lastCO2_ppm = calcMQppm(lastMQ135raw, MQ135_R0, 116.602, -2.769);
            Serial.printf("[MQ135] Raw=%d  CO2=%.1f ppm\n", lastMQ135raw, lastCO2_ppm);
        } else {
            lastCO2_ppm = NAN;
            Serial.printf("[MQ135] Raw=%d — khong co sensor (floating)\n", lastMQ135raw);
        }
    } else {
        Serial.println("[MQ] Dang warm-up, bo qua...");
    }

    // Publish MQTT
    if (!mqtt.connected()) {
        Serial.println("[ENV] !!! MQTT khong ket noi, khong gui duoc");
        return;
    }

    envSendCount++;

    // Dung ArduinoJson de xu ly null dung cach
    StaticJsonDocument<384> doc;
    doc["device"] = DEVICE_CODE;

    // SHT40: null neu khong co sensor
    if (!isnan(lastTemp))     doc["temp"] = serialized(String(lastTemp, 1));
    else                      doc["temp"] = (char*)NULL;
    if (!isnan(lastHumidity)) doc["humidity"] = serialized(String(lastHumidity, 1));
    else                      doc["humidity"] = (char*)NULL;

    // BH1750: null neu khong co sensor
    if (!isnan(lastLux))      doc["lux"] = serialized(String(lastLux, 1));
    else                      doc["lux"] = (char*)NULL;

    // MQ sensors: null neu khong cam hoac chua warm-up
    if (!isnan(lastNH3_ppm))  doc["nh3_ppm"] = serialized(String(lastNH3_ppm, 1));
    else                      doc["nh3_ppm"] = (char*)NULL;
    if (!isnan(lastCO2_ppm))  doc["co2_ppm"] = serialized(String(lastCO2_ppm, 1));
    else                      doc["co2_ppm"] = (char*)NULL;

    doc["mq137_raw"] = (lastMQ137raw >= 0) ? lastMQ137raw : 0;
    doc["mq135_raw"] = (lastMQ135raw >= 0) ? lastMQ135raw : 0;
    doc["warmup"]    = warmup_done;
    doc["seq"]       = envSendCount;

    char buf[384];
    serializeJson(doc, buf, sizeof(buf));

    if (mqtt.publish(topicEnv, buf, false)) {
        Serial.printf("[ENV] >>> Da gui (#%d)\n", envSendCount);
    } else {
        Serial.println("[ENV] !!! Publish that bai");
    }
}

// ======================== MQTT ========================

void mqttReconnect() {
    Serial.println("[MQTT] Dang ket noi...");

    if (mqtt.connect(mqttClientId, MQTT_USER, MQTT_PASS,
                     topicLwt, 1, false, lwtPayload)) {
        Serial.println("[MQTT] Da ket noi! (voi LWT)");
        mqtt.subscribe(topicCmd);
        sendHeartbeat();
        // Gui du lieu cam bien ngay khi ket noi
        readAndSendEnv();
    } else {
        Serial.printf("[MQTT] That bai, rc=%d\n", mqtt.state());
    }
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
    char raw[257];
    int copyLen = (length < sizeof(raw) - 1) ? length : sizeof(raw) - 1;
    memcpy(raw, payload, copyLen);
    raw[copyLen] = '\0';
    Serial.printf("[CMD] <<< Nhan lenh: %s\n", raw);

    StaticJsonDocument<256> doc;
    DeserializationError err = deserializeJson(doc, payload, length);
    if (err) {
        Serial.printf("[CMD] !!! JSON parse loi: %s\n", err.c_str());
        return;
    }

    const char* action = doc["action"] | "";

    if (strcmp(action, "ping") == 0) {
        handlePing(doc);
    } else if (strcmp(action, "ota") == 0) {
        handleOtaCmd(doc);
    } else if (strcmp(action, "config") == 0) {
        handleConfig(doc);
    } else {
        Serial.printf("[CMD] !!! Action khong ho tro: \"%s\"\n", action);
    }
}

void handlePing(const JsonDocument& doc) {
    Serial.println("[CMD] >>> Nhan PING tu server");
    if (!mqtt.connected()) return;

    unsigned long ts = doc["ts"] | 0;
    char buf[256];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"ts\":%lu,\"uptime\":%lu,\"heap\":%u,\"rssi\":%d,"
        "\"sht40\":%s,\"bh1750\":%s,\"warmup\":%s}",
        DEVICE_CODE, ts, millis() / 1000,
        ESP.getFreeHeap(), WiFi.RSSI(),
        sht40_ok ? "true" : "false",
        bh1750_ok ? "true" : "false",
        warmup_done ? "true" : "false");

    mqtt.publish(topicPong, buf);
    Serial.println("[CMD] OK: Da gui PONG");
}

/**
 * Lenh config tu xa: thay doi interval, R0 calibration
 * VD: {"action":"config","env_interval":60,"mq137_r0":3.5,"mq135_r0":2.8}
 */
void handleConfig(const JsonDocument& doc) {
    Serial.println("[CMD] >>> Config tu xa");

    // TODO: Luu vao NVS neu can
    // Hien tai chi log, chua apply runtime
    serializeJsonPretty(doc, Serial);
    Serial.println();
}

// ======================== HEARTBEAT ========================

void sendHeartbeat() {
    if (!mqtt.connected()) return;

    char buf[320];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"status\":\"online\",\"wifi_rssi\":%d,"
        "\"ip\":\"%s\",\"uptime\":%lu,\"heap\":%u,"
        "\"sensors\":{\"sht40\":%s,\"bh1750\":%s,\"mq_warmup\":%s}}",
        DEVICE_CODE,
        WiFi.RSSI(),
        WiFi.localIP().toString().c_str(),
        millis() / 1000,
        ESP.getFreeHeap(),
        sht40_ok ? "true" : "false",
        bh1750_ok ? "true" : "false",
        warmup_done ? "true" : "false");

    if (mqtt.publish(topicHeartbeat, buf, false)) {
        Serial.println("[Heartbeat] OK");
    }
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

    // Thong bao server
    char buf[192];
    snprintf(buf, sizeof(buf),
        "{\"device\":\"%s\",\"status\":\"updating\",\"version\":\"%s\"}",
        DEVICE_CODE, version);
    mqtt.publish(topicEnv, buf);

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

// ======================== OTA ========================

void setupOTA() {
    ArduinoOTA.setHostname(DEVICE_CODE);
    ArduinoOTA.setPassword("cfarm_ota");
    ArduinoOTA.onStart([]() { Serial.println("[OTA] Bat dau cap nhat..."); });
    ArduinoOTA.onEnd([]()   { Serial.println("\n[OTA] Hoan tat!"); });
    ArduinoOTA.onError([](ota_error_t error) {
        Serial.printf("[OTA] Loi [%u]\n", error);
    });
    ArduinoOTA.begin();
    Serial.println("[OTA] San sang.");
}
