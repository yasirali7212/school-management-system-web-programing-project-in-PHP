<?php
/**
 * Student Management Module
 * Roots School System
 */

$pageTitle = "Student Management";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Actions (Add, Edit, Delete)
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

// Fetch classes and sections for dropdowns
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$parents = $pdo->query("SELECT id, father_name, phone FROM parents ORDER BY father_name ASC")->fetchAll();

// =================== POST ACTIONS ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subAction = $_POST['sub_action'] ?? '';

    // 1. ADD STUDENT
    if ($subAction === 'add') {
        $firstName    = trim($_POST['first_name'] ?? '');
        $lastName     = trim($_POST['last_name'] ?? '');
        $gender       = trim($_POST['gender'] ?? 'Male');
        $dob          = trim($_POST['dob'] ?? '');
        $admissionNo  = trim($_POST['admission_no'] ?? '');
        $rollNo       = trim($_POST['roll_no'] ?? '');
        $classId      = (int)($_POST['class_id'] ?? 0);
        $sectionId    = (int)($_POST['section_id'] ?? 0);
        $parentId     = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $phone        = trim($_POST['phone'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $password     = trim($_POST['password'] ?? 'student123');

        // Auto-generate admission number if empty
        if (empty($admissionNo)) {
            $admissionNo = 'ADM-' . date('Y') . '-' . rand(100, 999);
        }

        if (empty($firstName) || empty($lastName) || empty($classId) || empty($sectionId) || empty($dob)) {
            setFlash('danger', 'Please complete all required fields.');
        } else {
            try {
                $pdo->beginTransaction();

                // Create user login account
                $studentUsername = strtolower(str_replace([' ', '-'], '', $admissionNo));
                $studentEmail = !empty($email) ? $email : $studentUsername . '@roots.edu';
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $userStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role, status) 
                    VALUES (?, ?, ?, 'student', 'active')
                ");
                $userStmt->execute([$studentUsername, $studentEmail, $hashedPassword]);
                $userId = $pdo->lastInsertId();

                // Handle Photo Upload
                $photoName = 'default_avatar.png';
                if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $photoName = 'student_' . $userId . '_' . time() . '.' . $ext;
                        move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/photos/' . $photoName);
                    }
                }

                // Insert into students table
                $stStmt = $pdo->prepare("
                    INSERT INTO students 
                    (user_id, admission_no, roll_no, class_id, section_id, parent_id, first_name, last_name, gender, dob, admission_date, phone, address, photo, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, 'active')
                ");
                $stStmt->execute([
                    $userId, $admissionNo, $rollNo, $classId, $sectionId, $parentId,
                    $firstName, $lastName, $gender, $dob, $phone, $address, $photoName
                ]);

                $pdo->commit();
                setFlash('success', "Student '{$firstName} {$lastName}' added successfully!");
                redirect(BASE_URL . 'admin/students.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setFlash('danger', 'Failed to add student: ' . $e->getMessage());
            }
        }
    }

    // 2. EDIT STUDENT
    if ($subAction === 'edit') {
        $studentId   = (int)($_POST['student_id'] ?? 0);
        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name'] ?? '');
        $gender      = trim($_POST['gender'] ?? 'Male');
        $dob         = trim($_POST['dob'] ?? '');
        $rollNo      = trim($_POST['roll_no'] ?? '');
        $classId     = (int)($_POST['class_id'] ?? 0);
        $sectionId   = (int)($_POST['section_id'] ?? 0);
        $parentId    = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $phone       = trim($_POST['phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $status      = trim($_POST['status'] ?? 'active');

        try {
            // Check for new photo
            $photoUpdateSql = "";
            $params = [
                $firstName, $lastName, $gender, $dob, $rollNo,
                $classId, $sectionId, $parentId, $phone, $address, $status
            ];

            if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $photoName = 'student_' . $studentId . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/photos/' . $photoName);
                    $photoUpdateSql = ", photo = ?";
                    $params[] = $photoName;
                }
            }

            $params[] = $studentId;

            $stmt = $pdo->prepare("
                UPDATE students SET 
                first_name = ?, last_name = ?, gender = ?, dob = ?, roll_no = ?,
                class_id = ?, section_id = ?, parent_id = ?, phone = ?, address = ?, status = ?
                {$photoUpdateSql}
                WHERE id = ?
            ");
            $stmt->execute($params);

            setFlash('success', "Student record updated successfully.");
            redirect(BASE_URL . 'admin/students.php');
        } catch (Exception $e) {
            setFlash('danger', 'Update error: ' . $e->getMessage());
        }
    }

    // 3. DELETE STUDENT
    if ($subAction === 'delete') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        try {
            // Retrieve associated user_id
            $uStmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
            $uStmt->execute([$studentId]);
            $uId = $uStmt->fetchColumn();

            if ($uId) {
                // Delete user which cascades or deletes student
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uId]);
            } else {
                $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
            }
            setFlash('success', "Student deleted successfully.");
            redirect(BASE_URL . 'admin/students.php');
        } catch (Exception $e) {
            setFlash('danger', 'Could not delete student: ' . $e->getMessage());
        }
    }
}

// Fetch sections for all classes (for JS dynamic selection)
$allSections = $pdo->query("SELECT id, class_id, section_name FROM sections ORDER BY section_name ASC")->fetchAll();

// =================== SEARCH & FILTER QUERY ===================
$searchKeyword = trim($_GET['search'] ?? '');
$filterClass   = (int)($_GET['class_id'] ?? 0);
$filterSection = (int)($_GET['section_id'] ?? 0);

$whereClauses = ["1=1"];
$queryParams = [];

if (!empty($searchKeyword)) {
    $whereClauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ? OR s.roll_no LIKE ?)";
    $term = "%{$searchKeyword}%";
    $queryParams = array_merge($queryParams, [$term, $term, $term, $term]);
}
if ($filterClass > 0) {
    $whereClauses[] = "s.class_id = ?";
    $queryParams[] = $filterClass;
}
if ($filterSection > 0) {
    $whereClauses[] = "s.section_id = ?";
    $queryParams[] = $filterSection;
}

$whereSql = implode(' AND ', $whereClauses);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE {$whereSql}");
$countStmt->execute($queryParams);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$query = "
    SELECT s.*, c.class_name, sec.section_name, p.father_name, p.phone as parent_phone
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    WHERE {$whereSql}
    ORDER BY s.id DESC
    LIMIT {$limit} OFFSET {$offset}
";
$studentsStmt = $pdo->prepare($query);
$studentsStmt->execute($queryParams);
$studentsList = $studentsStmt->fetchAll();

// If editing, fetch single student record
$editStudent = null;
if ($action === 'edit' && $editId > 0) {
    $editStmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $editStmt->execute([$editId]);
    $editStudent = $editStmt->fetch();
}
?>

<!-- Header & Add Button -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-0">Student Management</h3>
        <p class="text-muted small mb-0">Manage admissions, student directories, academic wings, and profiles.</p>
    </div>
    <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i class="bi bi-person-plus-fill me-1"></i> Register New Student
    </button>
</div>

<!-- Search & Filtering Bar -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <!-- Search -->
        <div class="col-12 col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by student name, roll no, or admission no..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
            </div>
        </div>

        <!-- Filter Class -->
        <div class="col-6 col-md-3">
            <select name="class_id" class="form-select form-select-sm" id="filterClassSelect">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($filterClass == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter Section -->
        <div class="col-6 col-md-2">
            <select name="section_id" class="form-select form-select-sm">
                <option value="">All Sections</option>
                <?php foreach ($allSections as $sec): ?>
                    <option value="<?php echo $sec['id']; ?>" <?php echo ($filterSection == $sec['id']) ? 'selected' : ''; ?>>
                        Section <?php echo htmlspecialchars($sec['section_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Submit & Reset -->
        <div class="col-12 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">Filter</button>
            <?php if (!empty($searchKeyword) || $filterClass > 0 || $filterSection > 0): ?>
                <a href="<?php echo BASE_URL; ?>admin/students.php" class="btn btn-light btn-sm border" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Student Directory Table Card -->
<div class="content-card">
    <div class="content-card-header">
        <span class="fw-bold text-dark">
            <i class="bi bi-people-fill text-primary me-2"></i>Students Directory
            <span class="badge bg-light text-muted border ms-2"><?php echo $totalRecords; ?> Total</span>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Student Info</th>
                    <th>Adm. No</th>
                    <th>Class / Sec</th>
                    <th>Roll No</th>
                    <th>Parent / Guardian</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($studentsList)): ?>
                    <?php foreach ($studentsList as $st): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                        <?php echo strtoupper(substr($st['first_name'], 0, 1) . substr($st['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark lh-1"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($st['gender']); ?> &bull; <?php echo formatDate($st['dob']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($st['admission_no']); ?></span></td>
                            <td>
                                <span class="fw-medium text-dark"><?php echo htmlspecialchars($st['class_name'] ?? 'N/A'); ?></span>
                                <span class="badge bg-secondary-subtle text-secondary ms-1">Sec <?php echo htmlspecialchars($st['section_name'] ?? '-'); ?></span>
                            </td>
                            <td><strong>#<?php echo htmlspecialchars($st['roll_no']); ?></strong></td>
                            <td>
                                <?php if (!empty($st['father_name'])): ?>
                                    <div class="lh-1 fw-medium"><?php echo htmlspecialchars($st['father_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($st['parent_phone'] ?? ''); ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">Not Linked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($st['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>admin/student_view.php?id=<?php echo $st['id']; ?>" class="btn btn-outline-secondary" title="View Profile">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button class="btn btn-outline-primary edit-student-btn" 
                                            data-id="<?php echo $st['id']; ?>"
                                            data-first="<?php echo htmlspecialchars($st['first_name']); ?>"
                                            data-last="<?php echo htmlspecialchars($st['last_name']); ?>"
                                            data-gender="<?php echo $st['gender']; ?>"
                                            data-dob="<?php echo $st['dob']; ?>"
                                            data-roll="<?php echo htmlspecialchars($st['roll_no']); ?>"
                                            data-class="<?php echo $st['class_id']; ?>"
                                            data-section="<?php echo $st['section_id']; ?>"
                                            data-parent="<?php echo $st['parent_id']; ?>"
                                            data-phone="<?php echo htmlspecialchars($st['phone'] ?? ''); ?>"
                                            data-address="<?php echo htmlspecialchars($st['address'] ?? ''); ?>"
                                            data-status="<?php echo $st['status']; ?>"
                                            title="Edit Student">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-student-btn" 
                                            data-id="<?php echo $st['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>"
                                            title="Delete Student">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 opacity-50 mb-2"></i>
                            <p class="mb-0">No student records matching your query.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> students</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchKeyword); ?>&class_id=<?php echo $filterClass; ?>&section_id=<?php echo $filterSection; ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchKeyword); ?>&class_id=<?php echo $filterClass; ?>&section_id=<?php echo $filterSection; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchKeyword); ?>&class_id=<?php echo $filterClass; ?>&section_id=<?php echo $filterSection; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ================= ADD STUDENT MODAL ================= -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold" id="addStudentModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Register New Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="add">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Personal Info -->
                        <div class="col-12"><h6 class="fw-bold text-primary border-bottom pb-1 mb-2">1. Personal Information</h6></div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control form-control-sm" required placeholder="e.g. Zain">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control form-control-sm" required placeholder="e.g. Tariq">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select form-select-sm" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="dob" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Contact Phone</label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="+92 300 0000000">
                        </div>

                        <!-- Academic Wing -->
                        <div class="col-12 mt-3"><h6 class="fw-bold text-primary border-bottom pb-1 mb-2">2. Academic Assignment</h6></div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Admission No (Optional)</label>
                            <input type="text" name="admission_no" class="form-control form-control-sm" placeholder="Auto-generated if empty">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select form-select-sm" id="modalClassSelect" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Section <span class="text-danger">*</span></label>
                            <select name="section_id" class="form-select form-select-sm" id="modalSectionSelect" required>
                                <option value="">Select Class First</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Class Roll No <span class="text-danger">*</span></label>
                            <input type="text" name="roll_no" class="form-control form-control-sm" required placeholder="e.g. 101">
                        </div>
                        <div class="col-12 col-sm-8">
                            <label class="form-label small fw-semibold">Link Parent / Guardian</label>
                            <select name="parent_id" class="form-select form-select-sm">
                                <option value="">-- No Linked Parent Record --</option>
                                <?php foreach ($parents as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['father_name']); ?> (<?php echo htmlspecialchars($p['phone']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Login Credentials & Photo -->
                        <div class="col-12 mt-3"><h6 class="fw-bold text-primary border-bottom pb-1 mb-2">3. Login Account &amp; Profile</h6></div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Student Institutional Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" placeholder="e.g. zain@roots.edu (Optional)">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Default Password</label>
                            <input type="text" name="password" class="form-control form-control-sm" value="student123">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Photograph</label>
                            <input type="file" name="photo" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Residential Address</label>
                            <input type="text" name="address" class="form-control form-control-sm" placeholder="Street, Sector, City">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-save me-1"></i>Save Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= EDIT STUDENT MODAL ================= -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Student Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="edit">
                <input type="hidden" name="student_id" id="editStudentId">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="editFirstName" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="editLastName" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="editGender" class="form-select form-select-sm" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="dob" id="editDob" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Roll Number <span class="text-danger">*</span></label>
                            <input type="text" name="roll_no" id="editRollNo" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                            <select name="class_id" id="editClassId" class="form-select form-select-sm" required>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Section <span class="text-danger">*</span></label>
                            <select name="section_id" id="editSectionId" class="form-select form-select-sm" required>
                                <?php foreach ($allSections as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" data-class="<?php echo $s['class_id']; ?>">
                                        <?php echo htmlspecialchars($s['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" id="editStatus" class="form-select form-select-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="graduated">Graduated</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Link Parent</label>
                            <select name="parent_id" id="editParentId" class="form-select form-select-sm">
                                <option value="">-- None --</option>
                                <?php foreach ($parents as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['father_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="text" name="phone" id="editPhone" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Update Photo (Optional)</label>
                            <input type="file" name="photo" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Address</label>
                            <input type="text" name="address" id="editAddress" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= DELETE CONFIRMATION MODAL ================= -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-2"></i>
                <h5 class="fw-bold">Confirm Deletion</h5>
                <p class="text-muted small">Are you sure you want to delete student <strong id="deleteStudentName"></strong>? This will remove all their associated records.</p>
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="sub_action" value="delete">
                    <input type="hidden" name="student_id" id="deleteStudentId">
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        <button type="button" class="btn btn-light btn-sm px-3 border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Delete Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Section Mapping Script -->
<script>
const allSections = <?php echo json_encode($allSections); ?>;

function populateSections(classSelectId, sectionSelectId, preselectedSec = null) {
    const classId = document.getElementById(classSelectId).value;
    const secSelect = document.getElementById(sectionSelectId);
    secSelect.innerHTML = '<option value="">Select Section</option>';

    const filtered = allSections.filter(s => s.class_id == classId);
    filtered.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = 'Section ' + s.section_name;
        if (preselectedSec && s.id == preselectedSec) {
            opt.selected = true;
        }
        secSelect.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Dynamic section dropdown for Add Modal
    const modalClass = document.getElementById('modalClassSelect');
    if (modalClass) {
        modalClass.addEventListener('change', () => populateSections('modalClassSelect', 'modalSectionSelect'));
    }

    // Dynamic section dropdown for Edit Modal
    const editClass = document.getElementById('editClassId');
    if (editClass) {
        editClass.addEventListener('change', () => populateSections('editClassId', 'editSectionId'));
    }

    // Populate Edit Modal
    const editBtns = document.querySelectorAll('.edit-student-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editStudentId').value = btn.dataset.id;
            document.getElementById('editFirstName').value = btn.dataset.first;
            document.getElementById('editLastName').value = btn.dataset.last;
            document.getElementById('editGender').value = btn.dataset.gender;
            document.getElementById('editDob').value = btn.dataset.dob;
            document.getElementById('editRollNo').value = btn.dataset.roll;
            document.getElementById('editClassId').value = btn.dataset.class;
            populateSections('editClassId', 'editSectionId', btn.dataset.section);
            document.getElementById('editParentId').value = btn.dataset.parent || '';
            document.getElementById('editPhone').value = btn.dataset.phone || '';
            document.getElementById('editAddress').value = btn.dataset.address || '';
            document.getElementById('editStatus').value = btn.dataset.status;

            new bootstrap.Modal(document.getElementById('editStudentModal')).show();
        });
    });

    // Populate Delete Modal
    const deleteBtns = document.querySelectorAll('.delete-student-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('deleteStudentId').value = btn.dataset.id;
            document.getElementById('deleteStudentName').textContent = btn.dataset.name;
            new bootstrap.Modal(document.getElementById('deleteStudentModal')).show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

