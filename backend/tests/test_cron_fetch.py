#!/usr/bin/env python3
"""
Tests for the cron_fetch module.
"""

from typing import Any, Dict
from unittest.mock import MagicMock, patch

import requests

# pylint: disable=wrong-import-position
import cron_fetch


def test_parse_float_valid_numbers() -> None:
    """Test parse_float with valid numbers and strings."""
    assert cron_fetch.parse_float("123.45") == 123.45
    assert cron_fetch.parse_float("123") == 123.0
    assert cron_fetch.parse_float(123.45) == 123.45
    assert cron_fetch.parse_float(123) == 123.0


def test_parse_float_invalid_numbers() -> None:
    """Test parse_float with invalid inputs."""
    assert cron_fetch.parse_float("-") is None
    assert cron_fetch.parse_float("- 123") is None
    assert cron_fetch.parse_float("abc") is None
    assert cron_fetch.parse_float("") is None
    assert cron_fetch.parse_float(None) is None


@patch('cron_fetch.config')
@patch('cron_fetch.requests.get')
def test_fetch_ship_location_success(mock_get: MagicMock, mock_config: MagicMock) -> None:
    """Test fetch_ship_location on successful API call."""
    mock_config.get.side_effect = lambda key, default=None: "dummy" if key in (
        'DATADOCKED_API_KEY', 'DATADOCKED_VESSEL_MMSI'
    ) else default

    mock_response = MagicMock()
    mock_response.json.return_value = {"latitude": 10.0, "longitude": 20.0}
    mock_get.return_value = mock_response

    result = cron_fetch.fetch_ship_location()
    assert result == {"latitude": 10.0, "longitude": 20.0}
    mock_get.assert_called_once()


@patch('cron_fetch.config')
@patch('cron_fetch.requests.get')
def test_fetch_ship_location_request_exception(mock_get: MagicMock, mock_config: MagicMock) -> None:
    """Test fetch_ship_location handles RequestException."""
    mock_config.get.side_effect = lambda key, default=None: "dummy" if key in (
        'DATADOCKED_API_KEY', 'DATADOCKED_VESSEL_MMSI'
    ) else default

    mock_get.side_effect = requests.exceptions.RequestException("API error")

    result = cron_fetch.fetch_ship_location()
    assert result is None
    mock_get.assert_called_once()


@patch('cron_fetch.sys.exit')
@patch('cron_fetch.config')
def test_fetch_ship_location_missing_config(mock_config: MagicMock, mock_exit: MagicMock) -> None:
    """Test fetch_ship_location aborts when config is missing."""
    mock_config.get.return_value = None

    cron_fetch.fetch_ship_location()
    mock_exit.assert_called_once_with(1)


@patch('cron_fetch.mysql.connector.connect')
def test_parse_and_insert_location_valid(_mock_connect: MagicMock) -> None:
    """Test parse_and_insert_location handles valid data and inserts correctly."""
    mock_db_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_db_conn.cursor.return_value = mock_cursor

    data: Dict[str, Any] = {
        "latitude": 34.0,
        "longitude": -118.0,
        "speed": "12.5",
        "course": "180.0",
        "heading": "180",
        "destination": "LOS ANGELES",
        "etaUtc": "Apr 16, 2026 23:42 UTC",
        "draught": "10.5",
        "navigationalStatus": "Under way using engine"
    }

    cron_fetch.parse_and_insert_location(mock_db_conn, data)

    mock_cursor.execute.assert_called_once()
    mock_db_conn.commit.assert_called_once()
    mock_cursor.close.assert_called_once()

    # Check that cursor.execute was called with the right query and params
    args, _kwargs = mock_cursor.execute.call_args
    query = args[0]
    params = args[1]

    assert "INSERT INTO locations" in query
    assert params[1] == -118.0
    assert params[2] == 34.0
    assert params[3] == 12.5
    assert params[4] == 180.0
    assert params[5] == 180.0
    assert params[6] == "LOS ANGELES"
    assert params[7] == "2026-04-16 23:42:00"
    assert params[8] == 10.5
    assert params[9] == "Under way using engine"


@patch('cron_fetch.mysql.connector.connect')
def test_parse_and_insert_location_missing_coords(_mock_connect: MagicMock) -> None:
    """Test parse_and_insert_location ignores data with missing coordinates."""
    mock_db_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_db_conn.cursor.return_value = mock_cursor

    data: Dict[str, Any] = {
        "latitude": None,
        "longitude": None
    }

    cron_fetch.parse_and_insert_location(mock_db_conn, data)

    # Should not insert anything
    mock_cursor.execute.assert_not_called()
    mock_db_conn.commit.assert_not_called()
