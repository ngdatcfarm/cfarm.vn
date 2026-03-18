-- ============================================================
-- Insert default device types if not exists
-- ============================================================

-- Kiểm tra và thêm device types
INSERT IGNORE INTO device_types (id, name, description, device_class, total_channels, is_active) VALUES
(1, 'ESP32 Relay 8 kênh', 'Board relay 8 kênh điều khiển thiết bị', 'relay', 8, 1),
(2, 'ESP32 DHT22 Sensor', 'Cảm biến nhiệt độ/độ ẩm DHT22', 'sensor', 0, 1),
(3, 'ESP32 ENV Sensor', 'Cảm biến môi trường đầy đủ', 'sensor', 0, 1);

-- Kiểm tra
SELECT * FROM device_types;
SELECT * FROM device_firmwares;
