<?php
/**
 * Application Configuration
 * Roots School System
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application Constants
define('APP_NAME', 'Roots School System');
define('APP_SHORT_NAME', 'Roots');
define('APP_VERSION', '2.0.0');
define('APP_YEAR', '2026-2027');

// Base URL auto-detection (works with XAMPP Apache subfolder or root PHP server)
$docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? strtolower(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']))) : '';
$appDir = strtolower(str_replace('\\', '/', realpath(dirname(__DIR__))));
if (!empty($docRoot) && strpos($appDir, $docRoot) === 0) {
    $subDir = substr($appDir, strlen($docRoot));
    $baseUrl = '/' . trim(str_replace('\\', '/', $subDir), '/') . '/';
    if ($baseUrl === '//') {
        $baseUrl = '/';
    }
} else {
    $baseUrl = '/';
}
define('BASE_URL', $baseUrl);

// Database Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management_system');
define('DB_PORT', '3307');

// Include Database Connection
require_once __DIR__ . '/database.php';
