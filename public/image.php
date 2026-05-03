<?php

/**
 * Image serving endpoint
 *
 * @category API
 * @package  WhereIsMom
 * @author   WhereIsMom <lucienve@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/lucienve/where-is-mom
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;

if ((getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')) !== 'testing') {
    session_save_path('/var/lib/where-is-mom-sessions');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
}

ini_set('session.gc_maxlifetime', '2592000');
session_set_cookie_params(
    [
    'lifetime' => 2592000,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
    ]
);
session_start();

// Refresh the session cookie lifetime to implement a 30-day rolling window
setcookie(
    session_name(), session_id(), [
    'expires' => time() + 2592000,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
    ]
);

// Force a write to the session on every request so the file's mtime is updated
$_SESSION['last_activity'] = time();

$config = parse_ini_file(__DIR__ . '/../config.ini');
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config['GOOGLE_APPLICATION_CREDENTIALS']);

// Authentication check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['viewer', 'traveler'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    http_response_code(400);
    exit('Invalid ID');
}

try {
    $pdo = new PDO(
        "mysql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'] . ";charset=utf8mb4",
        $config['DB_USER'],
        $config['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $stmt = $pdo->prepare("SELECT image_path FROM locations WHERE id = ? AND source = 'photo'");
    $stmt->execute([(int) $id]);
    $location = $stmt->fetch();

    if (!$location || empty($location['image_path'])) {
        http_response_code(404);
        exit('Image not found');
    }

    $imagePath = $location['image_path'];

    // Retrieve from GCS
    $storage = new StorageClient(
        [
            'projectId' => $config['GCP_PROJECT_ID'],
            // GCP SDK implicitly finds GOOGLE_APPLICATION_CREDENTIALS when set in ENV
        ]
    );

    $bucket = $storage->bucket($config['GCS_BUCKET_NAME']);
    $object = $bucket->object($imagePath);

    if (!$object->exists()) {
        http_response_code(404);
        exit('Image object not found in storage');
    }

    $info = $object->info();

    // Set proper headers to stream to browser
    header('Content-Type: ' . ($info['contentType'] ?? 'image/jpeg'));
    header('Content-Length: ' . $info['size']);
    header('Cache-Control: private, max-age=86400'); // Cache for 1 day

    // Stream the content directly
    $stream = $object->downloadAsStream();
    while (!$stream->eof()) {
        echo $stream->read(8192);
    }
} catch (Exception $e) {
    http_response_code(500);
    exit('Internal Server Error');
}
