<?php
/**
 * Global Header Component
 * Roots School System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | <?php echo APP_NAME; ?></title>

    <!-- Google Fonts (Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS & Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <!-- Custom Dashboard Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css">
</head>
<body>

<div class="app-wrapper">
    <!-- Mobile Sidebar Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar Navigation Partial -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Chamber -->
    <div class="app-main">
        <!-- Topbar Header -->
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle Navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-sm-flex align-items-center gap-3 small text-secondary">
                    <span><?php echo date('l, d F Y'); ?></span>
                    <span class="text-muted">&bull;</span>
                    <span>Session <?php echo APP_YEAR; ?></span>
                </div>
            </div>

            <!-- Topbar User Profile & Actions -->
            <div class="d-flex align-items-center gap-3">
                <?php if ($currentUser): ?>
                    <!-- Quiet Role Identifier -->
                    <span class="badge bg-secondary-subtle text-capitalize d-none d-md-inline-block">
                        <?php echo htmlspecialchars($currentUser['role']); ?>
                    </span>

                    <!-- User Dropdown Menu -->
                    <div class="dropdown">
                        <a href="#" class="topbar-user dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($currentUser['name'] ?? $currentUser['username'], 0, 1)); ?>
                            </div>
                            <div class="d-none d-md-block text-start lh-sm">
                                <div class="fw-semibold small text-dark"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2" style="font-size: 0.84rem; min-width: 200px;">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo ucfirst(htmlspecialchars($currentUser['role'])); ?> account</div>
                            </li>
                            <?php if ($currentUser['role'] === 'student'): ?>
                                <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>student/profile.php"><i class="bi bi-person me-2 text-secondary"></i>My profile</a></li>
                            <?php elseif ($currentUser['role'] === 'admin'): ?>
                                <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>admin/users.php"><i class="bi bi-shield-lock me-2 text-secondary"></i>Accounts &amp; credentials</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>change_password.php"><i class="bi bi-key me-2 text-secondary"></i>Change password</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary btn-sm px-3">Sign in</a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Dashboard View Container -->
        <main class="app-content">
            <?php echo displayFlash(); ?>
