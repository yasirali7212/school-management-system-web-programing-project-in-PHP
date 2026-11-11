<?php
/**
 * Teacher Management Module
 * Roots School System
 */

$pageTitle = "Teacher Management";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subAction = $_POST['sub_action'] ?? '';

    // 1. ADD TEACHER
    if ($subAction === 'add') {
        $empId        = trim($_POST['employee_id'] ?? '');
        $firstName    = trim($_POST['first_name'] ?? '');
        $lastName     = trim($_POST['last_name'] ?? '');
        $gender       = trim($_POST['gender'] ?? 'Female');
        $phone        = trim($_POST['phone'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $qualification= trim($_POST['qualification'] ?? '');
        $joiningDate  = trim($_POST['joining_date'] ?? date('Y-m-d'));
        $salary       = (float)($_POST['salary'] ?? 0);
        $address      = trim($_POST['address'] ?? '');
        $password     = trim($_POST['password'] ?? 'teacher123');

        if (empty($empId) || empty($firstName) || empty($lastName) || empty($email) || empty($phone)) {
            setFlash('danger', 'Please complete all required fields.');
        } else {
            try {
                $pdo->beginTransaction();

                // Create user login account
                $userUsername = strtolower(str_replace([' ', '-'], '', $empId));
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $userStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role, status) 
                    VALUES (?, ?, ?, 'teacher', 'active')
                ");
                $userStmt->execute([$userUsername, $email, $hashedPassword]);
                $userId = $pdo->lastInsertId();

                // Insert into teachers
                $tStmt = $pdo->prepare("
                    INSERT INTO teachers 
                    (user_id, employee_id, first_name, last_name, gender, phone, email, qualification, joining_date, salary, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $tStmt->execute([
                    $userId, $empId, $firstName, $lastName, $gender, $phone, $email, $qualification, $joiningDate, $salary, $address
                ]);

                $pdo->commit();
                setFlash('success', "Faculty member '{$firstName} {$lastName}' registered successfully!");
                redirect(BASE_URL . 'admin/teachers.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setFlash('danger', 'Error adding teacher: ' . $e->getMessage());
            }
        }
    }

    // 2. EDIT TEACHER
    if ($subAction === 'edit') {
        $teacherId    = (int)($_POST['teacher_id'] ?? 0);
        $firstName    = trim($_POST['first_name'] ?? '');
        $lastName     = trim($_POST['last_name'] ?? '');
        $gender       = trim($_POST['gender'] ?? 'Female');
        $phone        = trim($_POST['phone'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $qualification= trim($_POST['qualification'] ?? '');
        $salary       = (float)($_POST['salary'] ?? 0);
        $address      = trim($_POST['address'] ?? '');

        try {
            $stmt = $pdo->prepare("
                UPDATE teachers SET 
                first_name = ?, last_name = ?, gender = ?, phone = ?, email = ?, qualification = ?, salary = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $gender, $phone, $email, $qualification, $salary, $address, $teacherId]);

            // Update email in users table as well
            $uStmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = (SELECT user_id FROM teachers WHERE id = ?)");
            $uStmt->execute([$email, $teacherId]);

            setFlash('success', "Teacher record updated successfully.");
            redirect(BASE_URL . 'admin/teachers.php');
        } catch (Exception $e) {
            setFlash('danger', 'Update error: ' . $e->getMessage());
        }
    }

    // 3. DELETE TEACHER
    if ($subAction === 'delete') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        try {
            $uId = $pdo->query("SELECT user_id FROM teachers WHERE id = {$teacherId}")->fetchColumn();
            if ($uId) {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uId]);
            } else {
                $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$teacherId]);
            }
            setFlash('success', "Teacher record deleted successfully.");
            redirect(BASE_URL . 'admin/teachers.php');
        } catch (Exception $e) {
            setFlash('danger', 'Delete error: ' . $e->getMessage());
        }
    }
}

// Fetch Teachers List with assigned subjects count
$teachers = $pdo->query("
    SELECT t.*, 
           COUNT(DISTINCT ta.id) as total_assignments,
           GROUP_CONCAT(DISTINCT sub.subject_name SEPARATOR ', ') as subjects_taught
    FROM teachers t
    LEFT JOIN teacher_assignments ta ON t.id = ta.teacher_id
    LEFT JOIN subjects sub ON ta.subject_id = sub.id
    GROUP BY t.id
    ORDER BY t.id ASC
")->fetchAll();
?>

<!-- Header & Add Button -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-0">Teacher Management</h3>
        <p class="text-muted small mb-0">Manage faculty members, departmental credentials, and teaching assignments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>admin/subjects.php" class="btn btn-outline-primary btn-sm px-3">
            <i class="bi bi-diagram-3 me-1"></i> Subject Assignments
        </a>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            <i class="bi bi-person-plus-fill me-1"></i> Register Teacher
        </button>
    </div>
</div>

<!-- Teachers Table Card -->
<div class="content-card">
    <div class="content-card-header">
        <span class="fw-bold text-dark">
            <i class="bi bi-person-badge-fill text-primary me-2"></i>Faculty Directory
            <span class="badge bg-light text-muted border ms-2"><?php echo count($teachers); ?> Total</span>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Faculty Member</th>
                    <th>Employee ID</th>
                    <th>Contact Info</th>
                    <th>Qualification</th>
                    <th>Assigned Subjects</th>
                    <th>Salary</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($teachers)): ?>
                    <?php foreach ($teachers as $t): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="rounded-circle bg-success bg-opacity-10 text-success fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                        <?php echo strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark lh-1"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($t['gender']); ?> &bull; Joined <?php echo formatDate($t['joining_date']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($t['employee_id']); ?></span></td>
                            <td>
                                <div class="small fw-medium"><?php echo htmlspecialchars($t['email']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($t['phone']); ?></small>
                            </td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo htmlspecialchars($t['qualification'] ?? 'N/A'); ?></span></td>
                            <td>
                                <?php if (!empty($t['subjects_taught'])): ?>
                                    <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($t['subjects_taught']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">No assignments yet</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="fw-bold text-success"><?php echo formatCurrency($t['salary']); ?></span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>admin/users.php?role=teacher&q=<?php echo urlencode($t['employee_id']); ?>" class="btn btn-outline-secondary" title="Manage Login Credentials">
                                        <i class="bi bi-key"></i>
                                    </a>
                                    <button class="btn btn-outline-primary edit-teacher-btn"
                                            data-id="<?php echo $t['id']; ?>"
                                            data-first="<?php echo htmlspecialchars($t['first_name']); ?>"
                                            data-last="<?php echo htmlspecialchars($t['last_name']); ?>"
                                            data-gender="<?php echo $t['gender']; ?>"
                                            data-phone="<?php echo htmlspecialchars($t['phone']); ?>"
                                            data-email="<?php echo htmlspecialchars($t['email']); ?>"
                                            data-qual="<?php echo htmlspecialchars($t['qualification'] ?? ''); ?>"
                                            data-salary="<?php echo $t['salary']; ?>"
                                            data-address="<?php echo htmlspecialchars($t['address'] ?? ''); ?>"
                                            title="Edit Teacher">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-teacher-btn"
                                            data-id="<?php echo $t['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>"
                                            title="Delete Teacher">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No faculty members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= ADD TEACHER MODAL ================= -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Register Faculty Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="add">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" class="form-control form-control-sm" required placeholder="EMP-103">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control form-control-sm" required placeholder="e.g. Sarah">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control form-control-sm" required placeholder="e.g. Khan">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select form-select-sm" required>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control form-control-sm" required placeholder="+92 300 0000000">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Official Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control form-control-sm" required placeholder="teacher@roots.edu">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Qualification</label>
                            <input type="text" name="qualification" class="form-control form-control-sm" placeholder="e.g. M.Sc. Mathematics">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" name="joining_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Monthly Salary</label>
                            <input type="number" step="0.01" name="salary" class="form-control form-control-sm" placeholder="75000.00">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Login Default Password</label>
                            <input type="text" name="password" class="form-control form-control-sm" value="teacher123">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Residential Address</label>
                            <input type="text" name="address" class="form-control form-control-sm" placeholder="House, Street, City">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-save me-1"></i>Save Faculty Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= EDIT TEACHER MODAL ================= -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Faculty Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="edit">
                <input type="hidden" name="teacher_id" id="editTeacherId">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="editTeacherFirst" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="editTeacherLast" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="editTeacherGender" class="form-select form-select-sm" required>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="editTeacherPhone" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="editTeacherEmail" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Qualification</label>
                            <input type="text" name="qualification" id="editTeacherQual" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Monthly Salary</label>
                            <input type="number" step="0.01" name="salary" id="editTeacherSalary" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Residential Address</label>
                            <input type="text" name="address" id="editTeacherAddress" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Update Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= DELETE CONFIRMATION MODAL ================= -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-2"></i>
                <h5 class="fw-bold">Confirm Deletion</h5>
                <p class="text-muted small">Delete teacher <strong id="deleteTeacherName"></strong>? This will remove their user account and assignments.</p>
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="sub_action" value="delete">
                    <input type="hidden" name="teacher_id" id="deleteTeacherId">
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        <button type="button" class="btn btn-light btn-sm px-3 border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Populate Edit Modal
    const editBtns = document.querySelectorAll('.edit-teacher-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editTeacherId').value = btn.dataset.id;
            document.getElementById('editTeacherFirst').value = btn.dataset.first;
            document.getElementById('editTeacherLast').value = btn.dataset.last;
            document.getElementById('editTeacherGender').value = btn.dataset.gender;
            document.getElementById('editTeacherPhone').value = btn.dataset.phone;
            document.getElementById('editTeacherEmail').value = btn.dataset.email;
            document.getElementById('editTeacherQual').value = btn.dataset.qual;
            document.getElementById('editTeacherSalary').value = btn.dataset.salary;
            document.getElementById('editTeacherAddress').value = btn.dataset.address;

            new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
        });
    });

    // Populate Delete Modal
    const deleteBtns = document.querySelectorAll('.delete-teacher-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('deleteTeacherId').value = btn.dataset.id;
            document.getElementById('deleteTeacherName').textContent = btn.dataset.name;
            new bootstrap.Modal(document.getElementById('deleteTeacherModal')).show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

