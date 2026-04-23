<?php

/**
 * Main API endpoint
 *
 * @category API
 * @package  WhereIsMom
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\ExifExtractor;
use Google\Cloud\Storage\StorageClient;

ini_set('session.gc_maxlifetime', '2592000');
session_set_cookie_params(2592000);
session_start();

$config = parse_ini_file(__DIR__ . '/../config.ini');
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config['GOOGLE_APPLICATION_CREDENTIALS']);

header('Content-Type: application/json');

$pdo = new PDO(
    "mysql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'] . ";charset=utf8mb4",
    $config['DB_USER'],
    $config['DB_PASS'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

switch ($action) {
    case 'login':
        $password = $_POST['password'] ?? '';
        if ($password === $config['VIEWER_PASSWORD']) {
            $_SESSION['role'] = 'viewer';
            echo json_encode(['success' => true, 'role' => 'viewer']);
        } elseif ($password === $config['TRAVELER_PASSWORD']) {
            $_SESSION['role'] = 'traveler';
            echo json_encode(['success' => true, 'role' => 'traveler']);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'status':
        $role = $_SESSION['role'] ?? null;

        $stmt = $pdo->prepare("SELECT state_value FROM app_state WHERE state_key = 'tracking_mode_on_ship'");
        $stmt->execute();
        $state = $stmt->fetch();
        $isShipMode = ($state && $state['state_value'] === '1');

        // Also securely pass map key ONLY if logged in
        $mapKey = $role ? $config['GOOGLE_MAPS_API_KEY'] : null;

        echo json_encode(
            [
                'authenticated' => $role !== null,
                'role' => $role,
                'onShipMode' => $isShipMode,
                'mapsApiKey' => $mapKey
            ]
        );
        break;

    case 'set_mode':
        if (($_SESSION['role'] ?? '') !== 'traveler') {
            http_response_code(403);
            exit(json_encode(['error' => 'Forbidden']));
        }
        $mode = $_POST['mode'] ?? '1'; // 1 = ship, 0 = land
        $stmt = $pdo->prepare("UPDATE app_state SET state_value = ? WHERE state_key = 'tracking_mode_on_ship'");
        $stmt->execute([$mode === '1' ? '1' : '0']);
        echo json_encode(['success' => true]);
        break;

    case 'locations':
        if (!isset($_SESSION['role'])) {
            http_response_code(401);
            exit(json_encode(['error' => 'Unauthorized']));
        }

        // Bounding box parameters
        $west = (float) ($_GET['w'] ?? -180.0);
        $south = (float) ($_GET['s'] ?? -90.0);
        $east = (float) ($_GET['e'] ?? 180.0);
        $north = (float) ($_GET['n'] ?? 90.0);

        // MBRContains polygon construction string
        $polygon = sprintf(
            'POLYGON((%f %f, %f %f, %f %f, %f %f, %f %f))',
            $west,
            $south,
            $east,
            $south,
            $east,
            $north,
            $west,
            $north,
            $west,
            $south
        );

        $stmt = $pdo->prepare(
            "
            SELECT id, source, timestamp, ST_Longitude(coordinates) as lng, ST_Latitude(coordinates) as lat, image_path 
            FROM locations 
            WHERE MBRContains(ST_GeomFromText(? , 4326, 'axis-order=long-lat'), coordinates)
            ORDER BY timestamp ASC
        "
        );
        $stmt->execute([$polygon]);
        $locations = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $locations]);
        break;

    case 'latest':
        if (!isset($_SESSION['role'])) {
            http_response_code(401);
            exit(json_encode(['error' => 'Unauthorized']));
        }
        $stmt = $pdo->prepare(
            "SELECT ST_Longitude(coordinates) as lng, ST_Latitude(coordinates) as lat, timestamp " .
            "FROM locations ORDER BY timestamp DESC LIMIT 1"
        );
        $stmt->execute();
        $latest = $stmt->fetch();
        echo json_encode(['success' => true, 'data' => $latest]);
        break;

    case 'upload':
        if (($_SESSION['role'] ?? '') !== 'traveler') {
            http_response_code(403);
            exit(json_encode(['error' => 'Forbidden']));
        }

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            exit(json_encode(['error' => 'Upload failed or no file provided.']));
        }

        $tmpPath = $_FILES['photo']['tmp_name'];

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/heic'];
        $mimeType = mime_content_type($tmpPath);
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid file type. Only JPEG, PNG, and HEIC are allowed.']));
        }

        $gpsData = ExifExtractor::extractGps($tmpPath);

        $lat = null;
        $lng = null;
        $timestamp = date('Y-m-d H:i:s');
        $gpsMissing = true;

        if ($gpsData) {
            $lat = $gpsData['lat'];
            $lng = $gpsData['lng'];
            if ($gpsData['timestamp']) {
                $dt = DateTime::createFromFormat('Y:m:d H:i:s', $gpsData['timestamp']);
                if ($dt) {
                    $timestamp = $dt->format('Y-m-d H:i:s');
                }
            }
            $gpsMissing = false;
        } else {
            // Fallback to most recent known location
            $stmt = $pdo->prepare(
                "SELECT ST_Longitude(coordinates) as lng, ST_Latitude(coordinates) as lat " .
                "FROM locations ORDER BY timestamp DESC LIMIT 1"
            );
            $stmt->execute();
            $recent = $stmt->fetch();
            if ($recent) {
                $lat = $recent['lat'];
                $lng = $recent['lng'];
            } else {
                http_response_code(400);
                exit(json_encode(['error' => 'No EXIF GPS data and no historical data to fallback on.']));
            }
        }

        // Upload to GCS
        try {
            $storage = new StorageClient(['projectId' => $config['GCP_PROJECT_ID']]);
            $bucket = $storage->bucket($config['GCS_BUCKET_NAME']);

            $objectName = 'photos/' . uniqid() . '_' . basename($_FILES['photo']['name']);
            $bucket->upload(
                fopen($tmpPath, 'r'),
                ['name' => $objectName]
            );

            // Insert into DB
            $stmt = $pdo->prepare(
                "
                INSERT INTO locations (source, timestamp, coordinates, image_path) 
                VALUES ('photo', ?, ST_SRID(Point(?, ?), 4326), ?)
            "
            );
            $stmt->execute([$timestamp, $lng, $lat, $objectName]);

            echo json_encode(
                [
                    'success' => true,
                    'gps_missing' => $gpsMissing,
                    'fallback_lat' => $gpsMissing ? $lat : null,
                    'fallback_lng' => $gpsMissing ? $lng : null
                ]
            );
        } catch (Exception $e) {
            http_response_code(500);
            exit(json_encode(['error' => 'Storage error: ' . $e->getMessage()]));
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
