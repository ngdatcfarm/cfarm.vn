-- ============================================================
-- IoT Database Setup - Full Reset
-- ============================================================

-- Xóa và tạo lại bảng
DROP TABLE IF EXISTS device_firmwares;
DROP TABLE IF EXISTS device_state_log;
DROP TABLE IF EXISTS device_states;
DROP TABLE IF EXISTS device_commands;
DROP TABLE IF EXISTS curtain_configs;
DROP TABLE IF EXISTS device_channels;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS device_types;

-- 1. Device Types
CREATE TABLE device_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    device_class ENUM('relay', 'sensor', 'mixed') DEFAULT 'relay',
    total_channels INT DEFAULT 8,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Devices  
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    barn_id BIGINT UNSIGNED,
    device_type_id INT NOT NULL,
    mqtt_topic VARCHAR(100) NOT NULL,
    is_online TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE SET NULL,
    FOREIGN KEY (device_type_id) REFERENCES device_types(id)
);

-- 3. Device Channels
CREATE TABLE device_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_number TINYINT NOT NULL,
    name VARCHAR(100),
    channel_type ENUM('curtain_up','curtain_down','fan','light','heater','water','other') DEFAULT 'other',
    gpio_pin INT,
    max_on_seconds INT DEFAULT 120,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- 4. Device Commands
CREATE TABLE device_commands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_id INT,
    command_type ENUM('on','off','stop','set_position') NOT NULL,
    payload JSON,
    source ENUM('manual','schedule','automation','ai') DEFAULT 'manual',
    status ENUM('pending','sent','acknowledged','completed','failed','timeout') DEFAULT 'pending',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id)
);

-- 5. Device States
CREATE TABLE device_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    channel_id INT,
    state VARCHAR(20),
    position_pct TINYINT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- 6. Curtain Configs
CREATE TABLE curtain_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    barn_id BIGINT UNSIGNED NOT NULL,
    device_id INT NOT NULL,
    up_channel_id INT NOT NULL,
    down_channel_id INT NOT NULL,
    full_up_seconds DECIMAL(5,1) DEFAULT 30,
    full_down_seconds DECIMAL(5,1) DEFAULT 30,
    current_position_pct TINYINT DEFAULT 0,
    moving_state ENUM('idle','moving_up','moving_down') DEFAULT 'idle',
    FOREIGN KEY (barn_id) REFERENCES barns(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    FOREIGN KEY (up_channel_id) REFERENCES device_channels(id),
    FOREIGN KEY (down_channel_id) REFERENCES device_channels(id)
);

-- 7. Device State Log
CREATE TABLE device_state_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT,
    channel_id INT,
    curtain_config_id INT,
    state VARCHAR(20),
    position_pct TINYINT,
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 8. Device Firmwares
CREATE TABLE device_firmwares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    version VARCHAR(20) NOT NULL,
    description TEXT,
    device_type_id INT NOT NULL,
    code TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_latest TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_type_id) REFERENCES device_types(id) ON DELETE CASCADE
);

-- ============================================================
-- Insert default data
-- ============================================================

INSERT INTO device_types (id, name, description, device_class, total_channels, is_active) VALUES
(1, 'ESP32 Relay 8 kênh', 'Board relay 8 kênh điều khiển 4 bạt', 'relay', 8, 1),
(2, 'ESP32 DHT22 Sensor', 'Cảm biến nhiệt độ/độ ẩm', 'sensor', 0, 1),
(3, 'ESP32 ENV Sensor', 'Cảm biến môi trường', 'sensor', 0, 1);

INSERT INTO device_firmwares (name, version, description, device_type_id, code, is_active, is_latest) VALUES
('ESP32 Relay 8CH Barn v1.0', '1.0.0', 'Điều khiển 4 tấm bạt với interlock', 1, 
'/*
 * CFarm ESP32 Relay 8CH - 4 Curtains
 */
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#define DEVICE_CODE \"YOUR_DEVICE_CODE\"
const char* WIFI_SSID = \"YOUR_WIFI\";
const char* WIFI_PASS = \"YOUR_PASSWORD\";
const char* MQTT_SERVER = \"app.cfarm.vn\";
const char* MQTT_USER = \"cfarm_device\";
const char* MQTT_PASS = \"Abc@@123\";
const char* MQTT_TOPIC = \"cfarm/YOUR_TOPIC\";
const int RELAY_PINS[8] = {32,33,25,26,27,14,12,13};
const int INTERLOCK[][2] = {{1,2},{3,4},{5,6},{7,8}};
WiFiClient ec; PubSubClient mqtt(ec);
bool rs[8] = {false}; unsigned long hb = 0;
void setup(){Serial.begin(115200);
for(int i=0;i<8;i++){pinMode(RELAY_PINS[i],OUTPUT);digitalWrite(RELAY_PINS[i],HIGH);}
WiFi.begin(WIFI_SSID,WIFI_PASS);while(WiFi.status()!=WL_CONNECTED)delay(500);
mqtt.setServer(MQTT_SERVER,1883);mqtt.setCallback([](char*t,byte*p,l){String m;for(int i=0;i<l;i++)m+=(char)p[i];
JsonDocument d;deserializeJson(d,m);String a=d[\"action\"]|\"\";
if(a==\"relay\"){int ch=d[\"channel\"]|0;String s=d[\"state\"]|\"\";if(ch>0&&ch<9)sr(ch-1,s==\"on\");}
else if(a==\"all\"){String s=d[\"state\"]|\"\";for(int i=0;i<8;i++)sr(i,s==\"on\");}});
while(!mqtt.connected()){if(mqtt.connect(String(\"ESP_\"+String(DEVICE_CODE)).c_str(),MQTT_USER,MPP_PASS)){
mqtt.subscribe((String(MQTT_TOPIC)+\"/cmd\").c_str());sendHB();}delay(1000);}
}
void loop(){if(!mqtt.connected()){mqtt.connect(String(\"ESP_\"+String(DEVICE_CODE)).c_str(),MQTT_USER,MPP_PASS);}mqtt.loop();
if(millis()-hb>30000){hb=millis();sendHB();}}
void sr(int c,bool on){if(on){for(int i=0;i<4;i++){int u=INTERLOCK[i][0]-1,d=INTERLOCK[i][1]-1;
if((c==u&&rs[d])||(c==d&&rs[u]))return;}}digitalWrite(RELAY_PINS[c],on?LOW:HIGH);rs[c]=on;
mqtt.publish((String(MQTT_TOPIC)+\"/state\").c_str(),
(String(\"{\\\"device\\\":\\\"\")+String(DEVICE_CODE)+\"\\\",\\\"ch\\\":\"+String(c+1)+\",\\\"s\\\":\\\"\"+(on?\"on\":\"off\")+\"\\\"}\").c_str());}
void sendHB(){String r=\"[\";for(int i=0;i<8;i++){r+=rs[i]?\"1\":\"0\";if(i<7)r+=',';}r+=\"]\";
mqtt.publish((String(MQTT_TOPIC)+\"/heartbeat\").c_str(),
(String(\"{\\\"device\\\":\\\"\")+String(DEVICE_CODE)+\"\\\",\\\"r\\\":\"+r+\"}\").c_str(),true);}',1,1);

-- Verify
SELECT 'device_types' as tbl, COUNT(*) as cnt FROM device_types
UNION ALL
SELECT 'devices', COUNT(*) FROM devices
UNION ALL  
SELECT 'device_firmwares', COUNT(*) FROM device_firmwares;
