<?php
/**
 * Dynamic Sidebar Navigation Component
 * Roots School System
 * Unslop: Quiet chrome, line icons, clean sentence case, no loud gradients
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$role = $currentUser['role'] ?? 'guest';
$currentScript = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = null) {
    global $currentScript, $currentDir;
    if ($dir !== null && $currentDir !== $dir) return '';
    return ($currentScript === $page) ? 'active' : '';
}
?>

<aside class="app-sidebar" id="appSidebar">
    <!-- Sidebar Header / Logo -->
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL . $role . '/index.php'; ?>" class="sidebar-brand">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.svg" alt="Roots Crest" style="width: 32px; height: 32px; border-radius: 50%;">
            <div>
                <div class="sidebar-brand-name"><?php echo APP_SHORT_NAME; ?></div>
                <div class="sidebar-brand-sub">Academic Portal</div>
            </div>
        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-menu">

        <?php if ($role === 'admin'): ?>
            <!-- ================= ADMIN NAVIGATION ================= -->
            <div class="menu-category">Overview</div>
            <a href="<?php echo BASE_URL; ?>admin/index.php" class="nav-item-custom <?php echo isActive('index.php', 'admin'); ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Executive overview</span>
            </a>

            <div class="menu-category">Academic structure</div>
            <a href="<?php echo BASE_URL; ?>admin/students.php" class="nav-item-custom <?php echo isActive('students.php', 'admin'); ?>">
                <i class="bi bi-people"></i>
                <span>Students</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/teachers.php" class="nav-item-custom <?php echo isActive('teachers.php', 'admin'); ?>">
                <i class="bi bi-person-badge"></i>
                <span>Teaching faculty</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/classes.php" class="nav-item-custom <?php echo isActive('classes.php', 'admin'); ?>">
                <i class="bi bi-building"></i>
                <span>Classes &amp; sections</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/subjects.php" class="nav-item-custom <?php echo isActive('subjects.php', 'admin'); ?>">
                <i class="bi bi-journal-bookmark"></i>
                <span>Subjects &amp; curriculum</span>
            </a>

            <div class="menu-category">Operations</div>
            <a href="<?php echo BASE_URL; ?>admin/attendance.php" class="nav-item-custom <?php echo isActive('attendance.php', 'admin'); ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Daily attendance</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/examinations.php" class="nav-item-custom <?php echo isActive('examinations.php', 'admin'); ?>">
                <i class="bi bi-award"></i>
                <span>Examinations &amp; marks</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/fees.php" class="nav-item-custom <?php echo isActive('fees.php', 'admin'); ?>">
                <i class="bi bi-cash-stack"></i>
                <span>Fee vouchers &amp; dues</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/timetables.php" class="nav-item-custom <?php echo isActive('timetables.php', 'admin'); ?>">
                <i class="bi bi-clock-history"></i>
                <span>Weekly timetables</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/announcements.php" class="nav-item-custom <?php echo isActive('announcements.php', 'admin'); ?>">
                <i class="bi bi-megaphone"></i>
                <span>School circulars</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/reports.php" class="nav-item-custom <?php echo isActive('reports.php', 'admin'); ?>">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Analytics &amp; reports</span>
            </a>

        <?php elseif ($role === 'teacher'): ?>
            <!-- ================= TEACHER NAVIGATION ================= -->
            <div class="menu-category">Faculty desk</div>
            <a href="<?php echo BASE_URL; ?>teacher/index.php" class="nav-item-custom <?php echo isActive('index.php', 'teacher'); ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Faculty desk</span>
            </a>
            <a href="<?php echo BASE_URL; ?>teacher/attendance.php" class="nav-item-custom <?php echo isActive('attendance.php', 'teacher'); ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Mark attendance</span>
            </a>
            <a href="<?php echo BASE_URL; ?>teacher/marks.php" class="nav-item-custom <?php echo isActive('marks.php', 'teacher'); ?>">
                <i class="bi bi-pencil-square"></i>
                <span>Enter exam marks</span>
            </a>
            <a href="<?php echo BASE_URL; ?>teacher/homework.php" class="nav-item-custom <?php echo isActive('homework.php', 'teacher'); ?>">
                <i class="bi bi-book"></i>
                <span>Homework &amp; tasks</span>
            </a>
            <a href="<?php echo BASE_URL; ?>teacher/timetable.php" class="nav-item-custom <?php echo isActive('timetable.php', 'teacher'); ?>">
                <i class="bi bi-clock-history"></i>
                <span>Teaching schedule</span>
            </a>
            <a href="<?php echo BASE_URL; ?>teacher/students.php" class="nav-item-custom <?php echo isActive('students.php', 'teacher'); ?>">
                <i class="bi bi-people"></i>
                <span>Class rosters</span>
            </a>

        <?php elseif ($role === 'student'): ?>
            <!-- ================= STUDENT NAVIGATION ================= -->
            <div class="menu-category">Student portal</div>
            <a href="<?php echo BASE_URL; ?>student/index.php" class="nav-item-custom <?php echo isActive('index.php', 'student'); ?>">
                <i class="bi bi-house"></i>
                <span>Student home</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/profile.php" class="nav-item-custom <?php echo isActive('profile.php', 'student'); ?>">
                <i class="bi bi-person"></i>
                <span>My profile</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/attendance.php" class="nav-item-custom <?php echo isActive('attendance.php', 'student'); ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance history</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/results.php" class="nav-item-custom <?php echo isActive('results.php', 'student'); ?>">
                <i class="bi bi-award"></i>
                <span>Exam report cards</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/homework.php" class="nav-item-custom <?php echo isActive('homework.php', 'student'); ?>">
                <i class="bi bi-journal-text"></i>
                <span>Homework assignments</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/timetable.php" class="nav-item-custom <?php echo isActive('timetable.php', 'student'); ?>">
                <i class="bi bi-clock"></i>
                <span>Class timetable</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/announcements.php" class="nav-item-custom <?php echo isActive('announcements.php', 'student'); ?>">
                <i class="bi bi-megaphone"></i>
                <span>School notices</span>
            </a>

        <?php elseif ($role === 'parent'): ?>
            <!-- ================= PARENT NAVIGATION ================= -->
            <div class="menu-category">Guardian portal</div>
            <a href="<?php echo BASE_URL; ?>parent/index.php" class="nav-item-custom <?php echo isActive('index.php', 'parent'); ?>">
                <i class="bi bi-house"></i>
                <span>Guardian overview</span>
            </a>
            <a href="<?php echo BASE_URL; ?>parent/children.php" class="nav-item-custom <?php echo isActive('children.php', 'parent'); ?>">
                <i class="bi bi-people"></i>
                <span>Enrolled children</span>
            </a>
            <a href="<?php echo BASE_URL; ?>parent/attendance.php" class="nav-item-custom <?php echo isActive('attendance.php', 'parent'); ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Children attendance</span>
            </a>
            <a href="<?php echo BASE_URL; ?>parent/results.php" class="nav-item-custom <?php echo isActive('results.php', 'parent'); ?>">
                <i class="bi bi-award"></i>
                <span>Report cards</span>
            </a>
            <a href="<?php echo BASE_URL; ?>parent/fees.php" class="nav-item-custom <?php echo isActive('fees.php', 'parent'); ?>">
                <i class="bi bi-receipt"></i>
                <span>Fee vouchers &amp; receipts</span>
            </a>
            <a href="<?php echo BASE_URL; ?>parent/announcements.php" class="nav-item-custom <?php echo isActive('announcements.php', 'parent'); ?>">
                <i class="bi bi-megaphone"></i>
                <span>School circulars</span>
            </a>
        <?php endif; ?>

        <!-- Logout Link -->
        <div class="mt-4 pt-3 border-top border-white border-opacity-10">
            <a href="<?php echo BASE_URL; ?>logout.php" class="nav-item-custom text-secondary" style="color: #94a3b8;">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sign out</span>
            </a>
        </div>
    </div>
</aside>
