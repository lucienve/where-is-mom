#!/usr/bin/env python3
import os
import sys
import json
import requests
import datetime
import mysql.connector
import configparser
from mysql.connector import Error

# Load environment configuration natively
config_parser = configparser.ConfigParser()
config_parser.read(os.path.join(os.path.dirname(__file__), 'config.ini'))
config = config_parser['config']

def get_db_connection():
    try:
        connection = mysql.connector.connect(
            host=config.get('DB_HOST', 'localhost'),
            database=config.get('DB_NAME', 'where_is_mom'),
            user=config.get('DB_USER', 'root'),
            password=config.get('DB_PASS', '')
        )
        return connection
    except Error as e:
        print(f"Error while connecting to MySQL: {e}", file=sys.stderr)
        return None

def fetch_ship_location():
    api_key = config.get('DATADOCKED_API_KEY')
    mmsi = config.get('DATADOCKED_VESSEL_MMSI')
    
    if not api_key or not mmsi:
        print("Error: DATADOCKED_API_KEY or DATADOCKED_VESSEL_MMSI not set in config.ini", file=sys.stderr)
        sys.exit(1)
        
    url = "https://datadocked.com/api/vessels_operations/get-vessel-location"
    headers = {
        "x-api-key": api_key,
        "accept": "application/json"
    }
    params = {
        "imo_or_mmsi": mmsi
    }
    
    try:
        response = requests.get(url, params=params, headers=headers, timeout=10)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"Failed to fetch data from Datadocked API: {e}", file=sys.stderr)
        return None

def main():
    
    db = get_db_connection()
    if not db:
        sys.exit(1)
        
    cursor = db.cursor(dictionary=True)
    
    try:
        # Check current tracking mode
        cursor.execute("SELECT state_value FROM app_state WHERE state_key = 'tracking_mode_on_ship'")
        result = cursor.fetchone()
        
        # 0 = Land (Photo Mode), 1 = Ship (AIS Mode)
        # We only poll the API if we are in ship mode to save credits.
        if not result or result['state_value'] != '1':
            print("Tracking mode is currently set to 'On Land'. Skipping AIS poll.")
            return

        # Fetch AIS data
        data = fetch_ship_location()
        if not data:
            return
        
        # Datadocked response fields parsing
        lat = data.get('latitude')
        lng = data.get('longitude')
        if lat is None or lng is None:
            print("Error: Invalid location data received from API (missing lat or lng)", file=sys.stderr)
            return
            
        # Record the current server time as the timestamp, with the last known position
        timestamp = datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')

        def parse_float(val):
            if not val or val == '-' or str(val).startswith('- '):
                return None
            try:
                clean = ''.join(c for c in str(val) if c.isdigit() or c == '.')
                return float(clean) if clean else None
            except ValueError:
                return None

        speed = parse_float(data.get('speed'))
        course = parse_float(data.get('course'))
        heading = parse_float(data.get('heading'))
        destination = data.get('destination')
        
        eta_str = data.get('etaUtc')
        eta = None
        if eta_str:
            try:
                eta = datetime.datetime.strptime(eta_str, '%b %d, %Y %H:%M UTC').strftime('%Y-%m-%d %H:%M:%S')
            except ValueError:
                pass
                
        draught = parse_float(data.get('draught'))
        navigational_status = data.get('navigationalStatus')
        raw_response = json.dumps(data)
        
        # Insert into locations table
        # We use ST_SRID(Point(lng, lat), 4326) for MySQL spatial insertion
        insert_query = """
            INSERT INTO locations (
                source, timestamp, coordinates, speed, course, heading, 
                destination, eta, draught, navigational_status, raw_api_response
            ) VALUES (
                'ais', %s, ST_SRID(Point(%s, %s), 4326), %s, %s, %s, %s, %s, %s, %s, %s
            )
        """
        
        insert_values = (
            timestamp, float(lat), float(lng), speed, course, heading,
            destination, eta, draught, navigational_status, raw_response
        )
        
        cursor.execute(insert_query, insert_values)
        db.commit()
        print(f"Successfully recorded AIS location: Lat: {lat}, Lng: {lng} at {timestamp}")

    except Error as e:
        print(f"Database error during execution: {e}", file=sys.stderr)
    finally:
        if db.is_connected():
            cursor.close()
            db.close()

if __name__ == "__main__":
    main()
