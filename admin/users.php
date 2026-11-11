<?php
/**
 * User Accounts & Credential Provisioning Desk
 * Roots School System
 * Unslop: High density, clean controls, credential slip generation, zero fluff
 */

$pageTitle = "Accounts & Credentials";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

$issuedSlip = $_SESSION['issued_slip'] ?? null;
unset($_SESSION['issued_slip']);

// =================== POST ACTIONS ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subAction = $_POST['sub_action'] ?? '';

    // 1. UPDATE / RESET CREDENTIALS
    if ($subAction === 'update_credentials') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newUsername  = trim($_POST['username'] ?? '');
        $newEmail     = trim($_POST['email'] ?? '');
        $newPassword  = trim($_POST['password'] ?? '');
        $newStatus    = trim($_POST['status'] ?? 'active');

        if ($targetUserId <= 0 || empty($newUsername) || empty($newEmail)) {
            setFlash('danger', 'Username and email are required.');
        } else {
            try {
                // Check if username or email is already taken by another user
                $chk = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $chk->execute([$newUsername, $newEmail, $targetUserId]);
                if ($chk->fetch()) {
                    setFlash('danger', 'The username or email is already in use by another account.');
                } else {
                    $pdo->beginTransaction();

                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            throw new Exception("Password must be at least 6 characters long.");
                        }
                        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                        $uStmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, status = ? WHERE id = ?");
                        $uStmt->execute([$newUsername, $newEmail, $hashed, $newStatus, $targetUserId]);
                    } else {
                        $uStmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE id = ?");
                        $uStmt->execute([$newUsername, $newEmail, $newStatus, $targetUserId]);
                    }

                    // Sync email with role table
                    $pdo->prepare("UPDATE teachers SET email = ? WHERE user_id = ?")->execute([$newEmail, $targetUserId]);
                    $pdo->prepare("UPDATE parents SET email = ? WHERE user_id = ?")->execute([$newEmail, $targetUserId]);

                    $pdo->commit();

                    // If a new password was provided, prepare the credential slip
                    if (!empty($newPassword)) {
                        $userRow = $pdo->prepare("
                            SELECT u.*, 
                                   COALESCE(CONCAT(s.first_name, ' ', s.last_name), CONCAT(t.first_name, ' ', t.last_name), p.father_name, u.username) as display_name
                            FROM users u
                            LEFT JOIN students s ON u.id = s.user_id
                            LEFT JOIN teachers t ON u.id = t.user_id
                            LEFT JOIN parents p ON u.id = p.user_id
                            WHERE u.id = ?
                        ");
                        $userRow->execute([$targetUserId]);
                        $uData = $userRow->fetch();

                        $_SESSION['issued_slip'] = [
                            'name' => $uData['display_name'] ?? $newUsername,
                            'role' => $uData['role'],
                            'username' => $newUsername,
                            'email' => $newEmail,
                            'password' => $newPassword,
                            'type' => 'password_reset'
                        ];
                    }

                    setFlash('success', "Credentials for account '{$newUsername}' updated successfully.");
                    redirect(BASE_URL . 'admin/users.php');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setFlash('danger', 'Error updating credentials: ' . $e->getMessage());
            }
        }
    }

    // 2. QUICK PROVISION ACCOUNT (Student / Teacher / Parent)
    if ($subAction === 'provision_account') {
        $role        = trim($_POST['role'] ?? '');
        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name'] ?? '');
        $uniqueId    = trim($_POST['unique_id'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $password    = trim($_POST['password'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');

        if (!in_array($role, ['student', 'teacher', 'parent', 'admin'])) {
            setFlash('danger', 'Invalid role selected.');
        } elseif (empty($firstName) || empty($uniqueId) || empty($email) || empty($password)) {
            setFlash('danger', 'Please fill in all mandatory fields.');
        } elseif (strlen($password) < 6) {
            setFlash('danger', 'Password must be at least 6 characters.');
        } else {
            try {
                // Check unique username & email
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $chk->execute([$uniqueId, $email]);
                if ($chk->fetch()) {
                    setFlash('danger', 'An account with that unique ID or email already exists.');
                } else {
                    $pdo->beginTransaction();

                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $uStmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                    $uStmt->execute([$uniqueId, $email, $hashed, $role]);
                    $newUserId = $pdo->lastInsertId();

                    $displayName = trim($firstName . ' ' . $lastName);

                    if ($role === 'teacher') {
                        $qualification = trim($_POST['qualification'] ?? 'B.Ed');
                        $tStmt = $pdo->prepare("
                            INSERT INTO teachers (user_id, employee_id, first_name, last_name, gender, phone, email, qualification, joining_date, salary)
                            VALUES (?, ?, ?, ?, 'Male', ?, ?, ?, CURDATE(), 0)
                        ");
                        $tStmt->execute([$newUserId, $uniqueId, $firstName, $lastName, $phone, $email, $qualification]);
                    } elseif ($role === 'student') {
                        $classId   = (int)($_POST['class_id'] ?? 1);
                        $sectionId = (int)($_POST['section_id'] ?? 1);
                        $rollNo    = trim($_POST['roll_no'] ?? '1');
                        $gender    = trim($_POST['gender'] ?? 'Male');
                        $dob       = trim($_POST['dob'] ?? date('Y-m-d', strtotime('-10 years')));
                        $sStmt = $pdo->prepare("
                            INSERT INTO students (user_id, admission_no, roll_no, class_id, section_id, first_name, last_name, gender, dob, admission_date, phone, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'active')
                        ");
                        $sStmt->execute([$newUserId, $uniqueId, $rollNo, $classId, $sectionId, $firstName, $lastName, $gender, $dob, $phone]);
                    } elseif ($role === 'parent') {
                        $pStmt = $pdo->prepare("
                            INSERT INTO parents (user_id, father_name, phone, email, occupation)
                            VALUES (?, ?, ?, ?, 'Parent/Guardian')
                        ");
                        $pStmt->execute([$newUserId, $displayName, $phone, $email]);
                        $newParentId = $pdo->lastInsertId();

                        // Link child student if selected
                        $childId = (int)($_POST['child_id'] ?? 0);
                        if ($childId > 0) {
                            $pdo->prepare("UPDATE students SET parent_id = ? WHERE id = ?")->execute([$newParentId, $childId]);
                        }
                    }

                    $pdo->commit();

                    $_SESSION['issued_slip'] = [
                        'name' => $displayName,
                        'role' => $role,
                        'username' => $uniqueId,
                        'email' => $email,
                        'password' => $password,
                        'type' => 'new_account'
                    ];

                    setFlash('success', "Account for {$displayName} ({$uniqueId}) created successfully.");
                    redirect(BASE_URL . 'admin/users.php');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setFlash('danger', 'Error provisioning account: ' . $e->getMessage());
            }
        }
    }

    // 3. TOGGLE ACCOUNT STATUS
    if ($subAction === 'toggle_status') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $currStatus   = trim($_POST['current_status'] ?? 'active');
        $newStatus    = ($currStatus === 'active') ? 'inactive' : 'active';

        if ($targetUserId === (int)$currentUser['id']) {
            setFlash('danger', 'You cannot deactivate your own administrative account.');
        } else {
            try {
                $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $targetUserId]);
                setFlash('success', "Account status toggled to {$newStatus}.");
                redirect(BASE_URL . 'admin/users.php');
            } catch (Exception $e) {
                setFlash('danger', 'Error updating status: ' . $e->getMessage());
            }
        }
    }
}

// =================== FETCH DATA ===================
$filterRole   = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$search       = trim($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($filterRole)) {
    $where[] = "u.role = ?";
    $params[] = $filterRole;
}

if (!empty($filterStatus)) {
    $where[] = "u.status = ?";
    $params[] = $filterStatus;
}

if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR p.father_name LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

$whereClause = implode(' AND ', $where);

// Account Counts
$countTotal    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$countStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$countTeachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$countParents  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn();

// Query Users
$sql = "
    SELECT u.*,
           COALESCE(CONCAT(s.first_name, ' ', s.last_name), CONCAT(t.first_name, ' ', t.last_name), p.father_name, u.username) as full_name,
           s.id as student_record_id, s.admission_no, c.class_name, sec.section_name,
           t.id as teacher_record_id, t.employee_id, t.qualification,
           p.id as parent_record_id, p.phone as parent_phone
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN teachers t ON u.id = t.user_id
    LEFT JOIN parents p ON u.id = p.user_id
    WHERE {$whereClause}
    ORDER BY u.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Classes & Sections for Provision Modal
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections ORDER BY class_id ASC, section_name ASC")->fetchAll();
$allStudents = $pdo->query("SELECT id, first_name, last_name, admission_no FROM students WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();
?>

<!-- Header Section -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4 d-print-none">
    <div>
        <h2 class="page-title">Accounts &amp; Credentials Desk</h2>
        <p class="page-subtitle">Manage institutional logins, issue unique IDs, provide emails &amp; passwords, and reset credentials.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#provisionModal">
            <i class="bi bi-person-plus me-1"></i> Provision Account
        </button>
    </div>
</div>

<!-- ================= ISSUED CREDENTIALS SLIP BANNER ================= -->
<?php if ($issuedSlip): ?>
<div class="card border border-primary-subtle shadow-sm mb-4 bg-light">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <span class="badge bg-primary px-2.5 py-1 mb-2">
                    <?php echo ($issuedSlip['type'] === 'new_account') ? 'Account Created' : 'Password Reset Slip'; ?>
                </span>
                <h5 class="fw-bold mb-1 text-dark">Institutional Credentials Generated</h5>
                <p class="text-secondary small mb-0">Provide these credentials to the user. Advise them to sign in and update their password.</p>
            </div>
            <button type="button" class="btn btn-outline-dark btn-sm d-print-none" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Slip
            </button>
        </div>

        <div class="bg-white p-3 rounded border font-monospace">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">Full Name</div>
                    <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($issuedSlip['name']); ?></div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">Assigned Role</div>
                    <div class="text-capitalize fw-semibold text-dark"><?php echo htmlspecialchars($issuedSlip['role']); ?></div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">Unique ID / Username</div>
                    <div class="d-flex align-items-center gap-1">
                        <strong class="text-primary"><?php echo htmlspecialchars($issuedSlip['username']); ?></strong>
                        <button class="btn btn-sm btn-link p-0 text-muted" onclick="copyText('<?php echo htmlspecialchars($issuedSlip['username']); ?>')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">Official Email</div>
                    <div class="d-flex align-items-center gap-1 text-truncate">
                        <span><?php echo htmlspecialchars($issuedSlip['email']); ?></span>
                        <button class="btn btn-sm btn-link p-0 text-muted" onclick="copyText('<?php echo htmlspecialchars($issuedSlip['email']); ?>')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12 border-top pt-2 mt-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <span class="text-muted small me-2">Temporary / Active Password:</span>
                            <span class="badge bg-warning-subtle text-dark border px-3 py-1.5 fs-6 font-monospace" id="slipPass">
                                <?php echo htmlspecialchars($issuedSlip['password']); ?>
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="copySlipDetails()">
                                <i class="bi bi-copy me-1"></i> Copy All Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-2 text-muted small d-flex align-items-center gap-2">
            <i class="bi bi-info-circle text-primary"></i>
            <span>Portal URL: <strong><?php echo BASE_URL; ?>login.php</strong> &bull; Users can change their password anytime via <em>Change Password</em>.</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Metric Counters -->
<div class="row g-3 mb-4 d-print-none">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <span class="stat-label">Total Accounts</span>
            <div class="stat-value"><?php echo number_format($countTotal); ?></div>
            <div class="stat-sub text-muted">All system logins</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <span class="stat-label">Student Logins</span>
            <div class="stat-value"><?php echo number_format($countStudents); ?></div>
            <div class="stat-sub text-success">Pupil accounts</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <span class="stat-label">Teacher Logins</span>
            <div class="stat-value"><?php echo number_format($countTeachers); ?></div>
            <div class="stat-sub text-primary">Instructors &amp; staff</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <span class="stat-label">Parent Logins</span>
            <div class="stat-value"><?php echo number_format($countParents); ?></div>
            <div class="stat-sub text-secondary">Guardians</div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border shadow-sm mb-4 d-print-none">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by name, unique ID, email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <option value="student" <?php echo ($filterRole === 'student') ? 'selected' : ''; ?>>Students</option>
                    <option value="teacher" <?php echo ($filterRole === 'teacher') ? 'selected' : ''; ?>>Teachers</option>
                    <option value="parent" <?php echo ($filterRole === 'parent') ? 'selected' : ''; ?>>Parents</option>
                    <option value="admin" <?php echo ($filterRole === 'admin') ? 'selected' : ''; ?>>Administrators</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo ($filterStatus === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($filterStatus === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Filter</button>
                <?php if (!empty($search) || !empty($filterRole) || !empty($filterStatus)): ?>
                    <a href="<?php echo BASE_URL; ?>admin/users.php" class="btn btn-link btn-sm text-secondary p-1" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Users Table Card -->
<div class="card border shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold text-dark">
            <i class="bi bi-people me-2 text-primary"></i>System User Accounts (<?php echo count($users); ?>)
        </h6>
        <span class="text-muted small">Sorted by registration sequence</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>User / Full Name</th>
                    <th>Role</th>
                    <th>Unique ID / Username</th>
                    <th>Email Address</th>
                    <th>Linked Profile</th>
                    <th>Status</th>
                    <th style="width: 140px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $idx => $u): ?>
                        <tr>
                            <td class="text-muted small font-monospace"><?php echo $idx + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">User ID: #<?php echo $u['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $rBadge = 'bg-secondary-subtle text-secondary';
                                if ($u['role'] === 'admin') $rBadge = 'bg-danger-subtle text-danger';
                                elseif ($u['role'] === 'teacher') $rBadge = 'bg-primary-subtle text-primary';
                                elseif ($u['role'] === 'student') $rBadge = 'bg-success-subtle text-success';
                                elseif ($u['role'] === 'parent') $rBadge = 'bg-warning-subtle text-dark';
                                ?>
                                <span class="badge <?php echo $rBadge; ?> text-capitalize px-2 py-1">
                                    <?php echo htmlspecialchars($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="font-monospace fw-bold text-dark"><?php echo htmlspecialchars($u['username']); ?></span>
                            </td>
                            <td>
                                <span class="font-monospace text-secondary small"><?php echo htmlspecialchars($u['email']); ?></span>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'student' && !empty($u['class_name'])): ?>
                                    <span class="small text-muted"><?php echo htmlspecialchars($u['class_name'] . ' - ' . $u['section_name']); ?> (Roll: <?php echo htmlspecialchars($u['admission_no']); ?>)</span>
                                <?php elseif ($u['role'] === 'teacher' && !empty($u['employee_id'])): ?>
                                    <span class="small text-muted">Emp ID: <?php echo htmlspecialchars($u['employee_id']); ?></span>
                                <?php elseif ($u['role'] === 'parent'): ?>
                                    <span class="small text-muted">Guardian Profile</span>
                                <?php else: ?>
                                    <span class="small text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success px-2 py-1">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Edit / Reset Modal Trigger -->
                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                            onclick='openEditModal(<?php echo json_encode($u); ?>)'
                                            title="Issue / Reset Credentials">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <!-- Toggle Status Form -->
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Change status of <?php echo htmlspecialchars($u['username']); ?>?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="sub_action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $u['status']; ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm" title="Toggle Active / Inactive">
                                            <i class="bi bi-power"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No user accounts found matching your filter criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODAL: EDIT / RESET CREDENTIALS ================= -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-semibold text-dark"><i class="bi bi-shield-lock me-2 text-primary"></i>Update Credentials</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="update_credentials">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Account Holder</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="edit_full_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Unique Login ID / Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="edit_username" class="form-control form-control-sm font-monospace" required>
                        <div class="form-text text-muted" style="font-size: 0.72rem;">Unique identifier used by student, teacher, or parent to log in.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Official Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control form-control-sm" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password (leave blank to keep current)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="password" id="edit_password" class="form-control form-control-sm font-monospace" placeholder="Enter new password">
                            <button class="btn btn-outline-secondary" type="button" onclick="generateRandomPass('edit_password')">Generate</button>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.72rem;">Provide this password to the user. They can change it after login.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Account Status</label>
                        <select name="status" id="edit_status" class="form-select form-select-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Save &amp; Issue Slip</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL: PROVISION NEW ACCOUNT ================= -->
<div class="modal fade" id="provisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-semibold text-dark"><i class="bi bi-person-plus me-2 text-primary"></i>Provision User Account</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="provision_account">

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Select Role <span class="text-danger">*</span></label>
                            <select name="role" id="prov_role" class="form-select form-select-sm" required onchange="handleRoleSwitch(this.value)">
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="parent">Parent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">First / Given Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="prov_first_name" class="form-control form-control-sm" required placeholder="e.g. John">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Last / Family Name</label>
                            <input type="text" name="last_name" id="prov_last_name" class="form-control form-control-sm" placeholder="e.g. Doe">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Unique Login ID <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="unique_id" id="prov_unique_id" class="form-control font-monospace" required placeholder="e.g. ADM-2026-101">
                                <button class="btn btn-outline-secondary" type="button" onclick="autoGenerateId()">Auto ID</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Official Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="prov_email" class="form-control form-control-sm" required placeholder="user@roots.edu">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Initial Password <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="password" id="prov_password" class="form-control font-monospace" required value="roots2026">
                                <button class="btn btn-outline-secondary" type="button" onclick="generateRandomPass('prov_password')">Generate</button>
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.72rem;">User can change this password after signing in.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Contact</label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="e.g. +92 300 1234567">
                        </div>
                    </div>

                    <!-- Role-Specific Fields Container -->
                    <div id="roleFieldsStudent" class="p-3 bg-light rounded border mb-3">
                        <h6 class="small fw-bold text-dark mb-2">Student Enrolment Details</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Class</label>
                                <select name="class_id" class="form-select form-select-sm">
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Section</label>
                                <select name="section_id" class="form-select form-select-sm">
                                    <?php foreach ($sections as $s): ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['section_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Roll No</label>
                                <input type="text" name="roll_no" class="form-control form-control-sm" value="01">
                            </div>
                        </div>
                    </div>

                    <div id="roleFieldsTeacher" class="p-3 bg-light rounded border mb-3 d-none">
                        <h6 class="small fw-bold text-dark mb-2">Faculty Details</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Qualification</label>
                                <input type="text" name="qualification" class="form-control form-control-sm" placeholder="e.g. M.Sc, M.Phil">
                            </div>
                        </div>
                    </div>

                    <div id="roleFieldsParent" class="p-3 bg-light rounded border mb-3 d-none">
                        <h6 class="small fw-bold text-dark mb-2">Guardian Linkage</h6>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label small">Link Enrolled Child (Optional)</label>
                                <select name="child_id" class="form-select form-select-sm">
                                    <option value="">-- Select Child --</option>
                                    <?php foreach ($allStudents as $st): ?>
                                        <option value="<?php echo $st['id']; ?>">
                                            <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?> (<?php echo htmlspecialchars($st['admission_no']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Create &amp; Issue Credentials</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_full_name').value = user.full_name + ' (' + user.role + ')';
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_status').value = user.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function handleRoleSwitch(role) {
    document.getElementById('roleFieldsStudent').classList.toggle('d-none', role !== 'student');
    document.getElementById('roleFieldsTeacher').classList.toggle('d-none', role !== 'teacher');
    document.getElementById('roleFieldsParent').classList.toggle('d-none', role !== 'parent');
    autoGenerateId();
}

function autoGenerateId() {
    const role = document.getElementById('prov_role').value;
    const rand = Math.floor(100 + Math.random() * 900);
    const year = new Date().getFullYear();
    let prefix = 'ADM-' + year + '-';
    if (role === 'teacher') prefix = 'EMP-' + year + '-';
    else if (role === 'parent') prefix = 'PAR-' + year + '-';
    document.getElementById('prov_unique_id').value = prefix + rand;
}

function generateRandomPass(targetInputId) {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById(targetInputId).value = pass;
}

function copyText(txt) {
    navigator.clipboard.writeText(txt).then(() => {
        alert('Copied to clipboard: ' + txt);
    });
}

function copySlipDetails() {
    <?php if ($issuedSlip): ?>
    const txt = "Roots School System - Official Credentials\n" +
                "Full Name: <?php echo addslashes($issuedSlip['name']); ?>\n" +
                "Role: <?php echo addslashes($issuedSlip['role']); ?>\n" +
                "Unique ID: <?php echo addslashes($issuedSlip['username']); ?>\n" +
                "Email: <?php echo addslashes($issuedSlip['email']); ?>\n" +
                "Password: <?php echo addslashes($issuedSlip['password']); ?>\n" +
                "Login URL: <?php echo BASE_URL; ?>login.php\n\n" +
                "Please sign in and change your password in Account Settings.";
    copyText(txt);
    <?php endif; ?>
}

// Initial Auto ID
document.addEventListener('DOMContentLoaded', () => {
    autoGenerateId();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
