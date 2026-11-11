<?php
/**
 * Root Entry Point Router
 * Roots School System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'student';
    redirect(BASE_URL . $role . '/index.php');
} else {
    redirect(BASE_URL . 'login.php');
}
