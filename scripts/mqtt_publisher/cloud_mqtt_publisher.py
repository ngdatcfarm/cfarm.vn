#!/usr/bin/env python3
"""
Cloud MQTT Publisher Service
Publishes commands from pending_commands table to Cloud MQTT Broker (103.166.183.215)

Usage:
    python cloud_mqtt_publisher.py [--daemon]
"""

import json
import time
import signal
import sys
import logging
from datetime import datetime
import paho.mqtt.client as mqtt

# Configuration
MQTT_BROKER = "103.166.183.215"
MQTT_PORT = 1883
MQTT_USER = "cfarm_server"
MQTT_PASS = "Abc@@123"

# MySQL Configuration (cloud)
import pymysql

DB_CONFIG = {
    'host': 'localhost',
    'user': 'cfarm_user',
    'password': 'cfarm_pass',
    'database': 'cfarm_app_raw',
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

POLL_INTERVAL = 1  # seconds
BATCH_SIZE = 10

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler('/var/log/cfarm/mqtt_publisher.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

mqtt_client = None
running = True


def signal_handler(signum, frame):
    global running
    logger.info("Received signal, shutting down...")
    running = False


def get_mqtt_client():
    """Create and configure MQTT client."""
    client = mqtt.Client(client_id="cfarm_cloud_publisher")
    client.username_pw_set(MQTT_USER, MQTT_PASS)
    client.on_connect = on_connect
    client.on_disconnect = on_disconnect
    client.on_publish = on_publish
    return client


def on_connect(client, userdata, flags, rc):
    if rc == 0:
        logger.info(f"Connected to MQTT broker {MQTT_BROKER}:{MQTT_PORT}")
    else:
        logger.error(f"MQTT connection failed with code {rc}")


def on_disconnect(client, userdata, rc):
    if rc != 0:
        logger.warning(f"Disconnected from MQTT broker, reconnecting...")


def on_publish(client, userdata, mid):
    pass


def get_pending_commands():
    """Fetch pending commands from database."""
    try:
        conn = pymysql.connect(**DB_CONFIG)
        with conn.cursor() as cursor:
            sql = """
                SELECT id, device_code, command_json
                FROM pending_commands
                WHERE status = 'pending'
                ORDER BY priority DESC, created_at ASC
                LIMIT %s
            """
            cursor.execute(sql, (BATCH_SIZE,))
            return cursor.fetchall()
    except Exception as e:
        logger.error(f"Database error: {e}")
        return []
    finally:
        if 'conn' in locals():
            conn.close()


def update_command_status(cmd_id, status, error_message=None):
    """Update command status in database."""
    try:
        conn = pymysql.connect(**DB_CONFIG)
        with conn.cursor() as cursor:
            if status == 'sent':
                sql = "UPDATE pending_commands SET status = %s, sent_at = NOW() WHERE id = %s"
            else:
                sql = "UPDATE pending_commands SET status = %s, error_message = %s WHERE id = %s"
            cursor.execute(sql, (status, error_message, cmd_id) if error_message else (status, cmd_id))
            conn.commit()
    except Exception as e:
        logger.error(f"Failed to update command {cmd_id}: {e}")
    finally:
        if 'conn' in locals():
            conn.close()


def log_command(device_code, command_type, command_json, status, error_message=None):
    """Log command to command_logs table."""
    try:
        conn = pymysql.connect(**DB_CONFIG)
        with conn.cursor() as cursor:
            sql = """
                INSERT INTO command_logs (device_code, command_type, command_json, status, error_message)
                VALUES (%s, %s, %s, %s, %s)
            """
            cursor.execute(sql, (device_code, command_type, command_json, status, error_message))
            conn.commit()
    except Exception as e:
        logger.error(f"Failed to log command: {e}")
    finally:
        if 'conn' in locals():
            conn.close()


def publish_command(device_code, command_json):
    """Publish command to MQTT topic."""
    topic = f"cfarm.vn/{device_code}/cmd"
    try:
        result = mqtt_client.publish(topic, command_json, qos=1)
        if result.rc == mqtt.MQTT_ERR_SUCCESS:
            logger.info(f"Published to {topic}: {command_json[:100]}")
            return True
        else:
            logger.error(f"Publish failed: {result.rc}")
            return False
    except Exception as e:
        logger.error(f"Publish error: {e}")
        return False


def process_commands():
    """Process pending commands from database."""
    commands = get_pending_commands()
    if not commands:
        return

    logger.info(f"Processing {len(commands)} pending commands")

    for cmd in commands:
        cmd_id = cmd['id']
        device_code = cmd['device_code']
        command_json = cmd['command_json']

        # Parse to get command type
        try:
            parsed = json.loads(command_json)
            command_type = parsed.get('action', 'unknown')
        except:
            command_type = 'unknown'

        # Publish to MQTT
        success = publish_command(device_code, command_json)

        if success:
            update_command_status(cmd_id, 'sent')
            log_command(device_code, command_type, command_json, 'success')
        else:
            update_command_status(cmd_id, 'failed', 'MQTT publish failed')
            log_command(device_code, command_type, command_json, 'failed', 'MQTT publish failed')


def main():
    global mqtt_client, running

    logger.info("Starting Cloud MQTT Publisher Service...")

    # Setup signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    # Create MQTT client
    mqtt_client = get_mqtt_client()

    # Connect to broker
    try:
        logger.info(f"Connecting to {MQTT_BROKER}:{MQTT_PORT}...")
        mqtt_client.connect(MQTT_BROKER, MQTT_PORT, 60)
        mqtt_client.loop_start()
    except Exception as e:
        logger.error(f"Failed to connect to MQTT broker: {e}")
        sys.exit(1)

    # Main loop
    while running:
        try:
            process_commands()
            time.sleep(POLL_INTERVAL)
        except Exception as e:
            logger.error(f"Main loop error: {e}")
            time.sleep(5)

    # Cleanup
    if mqtt_client:
        mqtt_client.loop_stop()
        mqtt_client.disconnect()
    logger.info("Service stopped")


if __name__ == "__main__":
    main()