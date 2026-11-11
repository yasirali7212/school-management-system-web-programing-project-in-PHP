<?php
/**
 * Database Connection Configuration
 * School Management System
 * Uses PDO with smart port auto-detection (port 3307 / 3306)
 */

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'school_management_system');
if (!defined('DB_PORT')) define('DB_PORT', '3307');

$pdo = null;
$dbError = null;

// Ports to attempt: configured port first, then standard 3307 and 3306 fallbacks
$ports = array_unique([DB_PORT, '3307', '3306']);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 2,
];

foreach ($ports as $port) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $dbError = null;
        break; // Successfully connected!
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
    }
}

/**
 * Helper function to retrieve active PDO database connection
 */
function getDB() {
    global $pdo;
    return $pdo;
}

