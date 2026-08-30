#!/usr/bin/env python3
"""
Cron script to fetch ship location from Datadocked API and store it in the database.
"""

import configparser
import datetime
import json
import os
import sys
from typing import Any, Dict, Optional

import mysql.connector
import requests

# Load environment configuration natively
config_parser = configparser.ConfigParser()
config_parser.read(os.path.join(os.path.dirname(__file__), 'config.ini'))
if not config_parser.has_section('config'):
    config_parser.add_section('config')
config = config_parser['config']


def get_db_connection() -> Optional[mysql.connector.connection.MySQLConnection]:
    """
    Establish and return a connection to the MySQL database.

    Returns:
        Optional[mysql.connector.connection.MySQLConnection]: The database connection
            object if successful, None otherwise.
    """
    try:
        connection = mysql.connector.connect(
            host=config.get('DB_HOST', 'localhost'),
            database=config.get('DB_NAME', 'where_is_mom'),
            user=config.get('DB_USER', 'root'),
            password=config.get('DB_PASS', '')
        )
        return connection
    except mysql.connector.Error as err:
        print(f"Error while connecting to MySQL: {err}", file=sys.stderr)
        return None


def fetch_ship_location() -> Optional[Dict[str, Any]]:
    """
    Fetch the ship's current location from the Datadocked API.

    Returns:
        Optional[Dict[str, Any]]: A dictionary containing the API response,
            or None if the request fails.
    """
    api_key: Optional[str] = config.get('DATADOCKED_API_KEY')
    mmsi: Optional[str] = config.get('DATADOCKED_VESSEL_MMSI')

    if not api_key or not mmsi:
        print(
            "Error: DATADOCKED_API_KEY or DATADOCKED_VESSEL_MMSI not set in config.ini",
            file=sys.stderr
        )
        sys.exit(1)

    url: str = "https://datadocked.com/api/vessels_operations/get-vessel-location"
    headers: Dict[str, str] = {
        "x-api-key": api_key,
        "accept": "application/json"
    }
    params: Dict[str, str] = {
        "imo_or_mmsi": mmsi
    }

    try:
        response: requests.Response = requests.get(url, params=params, headers=headers, timeout=10)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as err:
        print(f"Failed to fetch data from Datadocked API: {err}", file=sys.stderr)
        return None


def parse_float(val: Any) -> Optional[float]:
    """
    Parse a float value from a string or number, ignoring non-numeric characters.

    Args:
        val (Any): The value to parse.

    Returns:
        Optional[float]: The parsed float, or None if parsing fails.
    """
    if not val or val == '-' or str(val).startswith('- '):
        return None
    try:
        clean: str = ''.join(c for c in str(val) if c.isdigit() or c == '.')
        return float(clean) if clean else None
    except ValueError:
        return None


def parse_and_insert_location(db_conn: mysql.connector.connection.MySQLConnection,
                              data: Dict[str, Any]) -> None:
    """
    Parse the API response data and insert it into the locations table.

    Args:
        db_conn (mysql.connector.connection.MySQLConnection): The database connection.
        data (Dict[str, Any]): The JSON response from the API.
    """
    # pylint: disable=too-many-locals
    # Datadocked response fields parsing
    lat: Optional[float] = data.get('latitude')
    lng: Optional[float] = data.get('longitude')
    if lat is None or lng is None:
        print(
            "Error: Invalid location data received from API (missing lat or lng)",
            file=sys.stderr
        )
        return

    # Record the current server time as the timestamp, with the last known position
    timestamp: str = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

    speed: Optional[float] = parse_float(data.get('speed'))
    course: Optional[float] = parse_float(data.get('course'))
    heading: Optional[float] = parse_float(data.get('heading'))
    destination: Optional[str] = data.get('destination')

    eta_str: Optional[str] = data.get('etaUtc')
    eta: Optional[str] = None
    if eta_str:
        try:
            eta_dt = datetime.datetime.strptime(eta_str, '%b %d, %Y %H:%M UTC')
            eta = eta_dt.strftime('%Y-%m-%d %H:%M:%S')
        except ValueError:
            pass

    draught: Optional[float] = parse_float(data.get('draught'))
    navigational_status: Optional[str] = data.get('navigationalStatus')
    raw_response: str = json.dumps(data)

    # Insert into locations table
    # We use ST_SRID(Point(lng, lat), 4326) for MySQL spatial insertion
    insert_query: str = (
        "INSERT INTO locations ("
        "source, timestamp, coordinates, speed, course, heading, "
        "destination, eta, draught, navigational_status, raw_api_response"
        ") VALUES ("
        "'ais', %s, ST_SRID(Point(%s, %s), 4326), %s, %s, %s, %s, %s, %s, %s, %s"
        ")"
    )

    insert_values: tuple = (
        timestamp, float(lng), float(lat), speed, course, heading,
        destination, eta, draught, navigational_status, raw_response
    )

    cursor = db_conn.cursor(dictionary=True)
    try:
        cursor.execute(insert_query, insert_values)
        db_conn.commit()
        print(f"Successfully recorded AIS location: Lat: {lat}, Lng: {lng} at {timestamp}")
    except mysql.connector.Error as err:
        print(f"Database error during insertion: {err}", file=sys.stderr)
    finally:
        cursor.close()


def main() -> None:
    """
    Main function to execute the fetching and storing of the ship location.
    """
    db_conn: Optional[mysql.connector.connection.MySQLConnection] = get_db_connection()
    if not db_conn:
        sys.exit(1)

    cursor = db_conn.cursor(dictionary=True)

    try:
        # Check current tracking mode
        cursor.execute(
            "SELECT state_value FROM app_state WHERE state_key = 'tracking_mode_on_ship'"
        )
        result: Optional[Dict[str, Any]] = cursor.fetchone()

        # 0 = Land (Photo Mode), 1 = Ship (AIS Mode)
        # We only poll the API if we are in ship mode to save credits.
        if not result or result['state_value'] != '1':
            print("Tracking mode is currently set to 'On Land'. Skipping AIS poll.")
            return

        # Fetch AIS data
        data: Optional[Dict[str, Any]] = fetch_ship_location()
        if not data:
            return

        parse_and_insert_location(db_conn, data)

    except mysql.connector.Error as err:
        print(f"Database error during execution: {err}", file=sys.stderr)
    finally:
        if db_conn.is_connected():
            cursor.close()
            db_conn.close()


if __name__ == "__main__":
    main()
