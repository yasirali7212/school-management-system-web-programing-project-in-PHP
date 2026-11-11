<?php
/**
 * Authentication & Application Helper Functions
 * Roots School System
 */

require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get logged-in user data
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'email'    => $_SESSION['email'] ?? '',
        'role'     => $_SESSION['user_role'] ?? 'guest',
        'name'     => $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'
    ];
}

// Guard access by specific role or array of roles
function requireRole($roles) {
    if (!isLoggedIn()) {
        setFlash('danger', 'Please sign in to access this page.');
        redirect(BASE_URL . 'login.php');
    }

    $roles = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['user_role'] ?? '';

    if (!in_array($userRole, $roles)) {
        setFlash('danger', 'Access denied. You do not have permission to view that page.');
        redirect(BASE_URL . ($userRole ? $userRole . '/index.php' : 'login.php'));
    }
}

// Redirect helper
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Input sanitization
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// CSRF Token Generation
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Field for Forms
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// CSRF Verification
function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die("Security error: Invalid CSRF Token.");
        }
    }
}

// Flash Messaging
function setFlash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function displayFlash() {
    $types = ['success', 'danger', 'warning', 'info'];
    $output = '';
    foreach ($types as $type) {
        $msg = getFlash($type);
        if ($msg) {
            $alertClass = ($type === 'danger') ? 'alert-danger' : 'alert-' . $type;
            $icon = ($type === 'success') ? 'check-circle-fill' : (($type === 'danger') ? 'exclamation-triangle-fill' : 'info-circle-fill');
            $output .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-' . $icon . ' me-2 fs-5"></i>
                <div>' . htmlspecialchars($msg) . '</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
    }
    return $output;
}

/**
 * Grade Representation that acts both as string (e.g. 'A+') and as associative array (['grade', 'gpa', 'remarks'])
 */
class GradeResult implements ArrayAccess {
    public string $grade;
    public string $gpa;
    public string $remarks;

    public function __construct(string $grade, string $gpa, string $remarks) {
        $this->grade = $grade;
        $this->gpa = $gpa;
        $this->remarks = $remarks;
    }

    public function __toString(): string {
        return $this->grade;
    }

    public function offsetExists($offset): bool {
        return in_array($offset, ['grade', 'gpa', 'remarks']);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->$offset ?? null;
    }

    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
}

// Grade calculation utility (supports both marks/total and direct percentage)
function calculateGrade($marksOrPercent, $maxMarks = 100) {
    $pct = ($maxMarks > 0 && $maxMarks != 100) ? ((float)$marksOrPercent / (float)$maxMarks) * 100 : (float)$marksOrPercent;
    if ($pct >= 90) return new GradeResult('A+', '4.0', 'Outstanding');
    if ($pct >= 80) return new GradeResult('A',  '3.7', 'Excellent');
    if ($pct >= 70) return new GradeResult('B',  '3.0', 'Very Good');
    if ($pct >= 60) return new GradeResult('C',  '2.0', 'Good');
    if ($pct >= 50) return new GradeResult('D',  '1.0', 'Pass');
    return new GradeResult('F', '0.0', 'Fail');
}

// Format Currency
function formatCurrency($amount) {
    return 'Rs. ' . number_format(floatval($amount), 2);
}

// Format Date
function formatDate($date, $format = 'd M Y') {
    if (empty($date) || $date === '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

// Status Badges
function getAttendanceBadge($status) {
    switch ($status) {
        case 'Present': return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Present</span>';
        case 'Absent':  return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Absent</span>';
        case 'Late':    return '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Late</span>';
        case 'Leave':   return '<span class="badge bg-info text-dark"><i class="bi bi-calendar-event me-1"></i>Leave</span>';
        default:        return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

function getFeeBadge($status) {
    switch ($status) {
        case 'Paid':    return '<span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Paid</span>';
        case 'Partial': return '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Partial</span>';
        case 'Unpaid':  return '<span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i>Unpaid</span>';
        default:        return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

function getStatusBadge($status) {
    return getFeeBadge($status);
}
