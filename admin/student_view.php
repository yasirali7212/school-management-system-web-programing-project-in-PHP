<?php
/**
 * Detailed Student Profile View
 * Roots School System
 */

$pageTitle = "Student Profile";
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$pdo = getDB();
$studentId = (int)($_GET['id'] ?? 0);

if ($studentId <= 0) {
    setFlash('danger', 'Invalid student identifier.');
    redirect(BASE_URL . 'admin/students.php');
}

// Fetch student profile with class, section, parent, and user info
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name, sec.section_name, sec.room_no,
           p.father_name, p.mother_name, p.phone as parent_phone, p.email as parent_email, p.occupation, p.address as parent_address,
           u.email as student_email, u.username as student_username
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('danger', 'Student not found.');
    redirect(BASE_URL . 'admin/students.php');
}

// 1. Attendance Summary for this student
$attStmt = $pdo->prepare("
    SELECT status, COUNT(*) as cnt 
    FROM attendance 
    WHERE student_id = ? 
    GROUP BY status
");
$attStmt->execute([$studentId]);
$attCounts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0];
$totalAttDays = 0;
while ($row = $attStmt->fetch()) {
    if (isset($attCounts[$row['status']])) {
        $attCounts[$row['status']] = (int)$row['cnt'];
    }
    $totalAttDays += (int)$row['cnt'];
}
$attRate = ($totalAttDays > 0) ? round(($attCounts['Present'] / $totalAttDays) * 100, 1) : 0;

// Recent Attendance List
$attRecent = $pdo->prepare("
    SELECT attendance_date, status, remarks 
    FROM attendance 
    WHERE student_id = ? 
    ORDER BY attendance_date DESC 
    LIMIT 10
");
$attRecent->execute([$studentId]);
$recentAttendance = $attRecent->fetchAll();

// 2. Examination Results
$examStmt = $pdo->prepare("
    SELECT m.marks_obtained, m.is_absent, m.remarks,
           es.max_marks, es.pass_marks, es.exam_date,
           e.exam_name, sub.subject_name
    FROM marks m
    JOIN exam_subjects es ON m.exam_subject_id = es.id
    JOIN examinations e ON es.exam_id = e.id
    JOIN subjects sub ON es.subject_id = sub.id
    WHERE m.student_id = ?
    ORDER BY es.exam_date DESC
");
$examStmt->execute([$studentId]);
$examMarks = $examStmt->fetchAll();

// 3. Fee Invoices
$feeStmt = $pdo->prepare("
    SELECT * FROM fees 
    WHERE student_id = ? 
    ORDER BY id DESC
");
$feeStmt->execute([$studentId]);
$feesList = $feeStmt->fetchAll();
?>

<!-- Breadcrumbs & Back Action -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>admin/students.php">Students</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold text-navy mb-0">Student Profile</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>admin/students.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Directory
        </a>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Profile
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Student ID Card & Key Indicators -->
    <div class="col-12 col-lg-4">
        <!-- Main Identity Card -->
        <div class="content-card text-center p-4 mb-4">
            <div class="position-relative d-inline-block mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width: 100px; height: 100px; font-size: 2.2rem;">
                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                </div>
                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-2 border border-2 border-white">
                    <span class="visually-hidden">Active</span>
                </span>
            </div>

            <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
            <div class="text-muted small mb-3">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1">
                    <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?> &bull; Section <?php echo htmlspecialchars($student['section_name'] ?? 'A'); ?>
                </span>
            </div>

            <div class="d-flex justify-content-center gap-2 mb-3">
                <span class="badge bg-light text-dark border px-2 py-1">Adm: <?php echo htmlspecialchars($student['admission_no']); ?></span>
                <span class="badge bg-light text-dark border px-2 py-1">Roll No: #<?php echo htmlspecialchars($student['roll_no']); ?></span>
            </div>

            <hr class="my-3 opacity-25">

            <div class="text-start small space-y-2">
                <div class="d-flex justify-content-between py-1 border-bottom border-light">
                    <span class="text-muted"><i class="bi bi-gender-ambiguous me-1.5"></i>Gender:</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom border-light">
                    <span class="text-muted"><i class="bi bi-cake2 me-1.5"></i>Date of Birth:</span>
                    <span class="fw-semibold"><?php echo formatDate($student['dob']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom border-light">
                    <span class="text-muted"><i class="bi bi-calendar-plus me-1.5"></i>Admission Date:</span>
                    <span class="fw-semibold"><?php echo formatDate($student['admission_date']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom border-light">
                    <span class="text-muted"><i class="bi bi-telephone me-1.5"></i>Phone:</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted"><i class="bi bi-envelope me-1.5"></i>Login Username:</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($student['student_username'] ?? $student['admission_no']); ?></span>
                </div>
            </div>
        </div>

        <!-- Attendance Summary Card -->
        <div class="content-card mb-4">
            <div class="content-card-header">
                <span class="fw-bold"><i class="bi bi-calendar-check-fill text-success me-2"></i>Attendance Overview</span>
                <span class="badge bg-success"><?php echo $attRate; ?>% Rate</span>
            </div>
            <div class="content-card-body">
                <div class="progress mb-3" style="height: 10px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attRate; ?>%;" aria-valuenow="<?php echo $attRate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="row g-2 text-center text-xs">
                    <div class="col-3">
                        <div class="p-2 rounded bg-success-subtle text-success">
                            <div class="fw-bold fs-6"><?php echo $attCounts['Present']; ?></div>
                            <small>Present</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded bg-danger-subtle text-danger">
                            <div class="fw-bold fs-6"><?php echo $attCounts['Absent']; ?></div>
                            <small>Absent</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded bg-warning-subtle text-warning-emphasis">
                            <div class="fw-bold fs-6"><?php echo $attCounts['Late']; ?></div>
                            <small>Late</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded bg-info-subtle text-info-emphasis">
                            <div class="fw-bold fs-6"><?php echo $attCounts['Leave']; ?></div>
                            <small>Leave</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parent / Guardian Information Card -->
        <div class="content-card">
            <div class="content-card-header">
                <span class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>Parent / Guardian</span>
            </div>
            <div class="content-card-body small">
                <?php if (!empty($student['father_name'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="rounded-circle bg-light p-2 text-primary fs-5">
                            <i class="bi bi-person-heart"></i>
                        </div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($student['father_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($student['occupation'] ?? 'Guardian'); ?></small>
                        </div>
                    </div>
                    <div class="mb-2"><i class="bi bi-telephone text-muted me-2"></i><?php echo htmlspecialchars($student['parent_phone']); ?></div>
                    <div class="mb-2"><i class="bi bi-envelope text-muted me-2"></i><?php echo htmlspecialchars($student['parent_email']); ?></div>
                    <div><i class="bi bi-geo-alt text-muted me-2"></i><?php echo htmlspecialchars($student['parent_address'] ?? $student['address'] ?? 'Not specified'); ?></div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <p class="mb-1">No linked parent record found.</p>
                        <a href="<?php echo BASE_URL; ?>admin/students.php?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-primary">Link Parent</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Academic Details, Exams & Fees Tabs -->
    <div class="col-12 col-lg-8">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs nav-fill mb-3" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="results-tab" data-bs-toggle="tab" data-bs-target="#results" type="button" role="tab">
                    <i class="bi bi-award-fill me-1.5"></i> Exam Results
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="fees-tab" data-bs-toggle="tab" data-bs-target="#fees" type="button" role="tab">
                    <i class="bi bi-cash-stack me-1.5"></i> Fee Invoices
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="att-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                    <i class="bi bi-calendar-check me-1.5"></i> Attendance Log
                </button>
            </li>
        </ul>

        <div class="tab-content" id="profileTabsContent">
            <!-- TAB 1: EXAM RESULTS -->
            <div class="tab-pane fade show active" id="results" role="tabpanel">
                <div class="content-card">
                    <div class="content-card-header">
                        <span class="fw-bold">Subject-wise Academic Performance</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Examination</th>
                                    <th>Subject</th>
                                    <th>Exam Date</th>
                                    <th>Marks</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($examMarks)): ?>
                                    <?php foreach ($examMarks as $m): 
                                        $pct = ($m['max_marks'] > 0) ? ($m['marks_obtained'] / $m['max_marks']) * 100 : 0;
                                        $gr = calculateGrade($pct);
                                    ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($m['exam_name']); ?></td>
                                            <td><?php echo htmlspecialchars($m['subject_name']); ?></td>
                                            <td class="text-muted small"><?php echo formatDate($m['exam_date']); ?></td>
                                            <td>
                                                <strong><?php echo number_format($m['marks_obtained'], 1); ?></strong>
                                                <small class="text-muted">/ <?php echo number_format($m['max_marks'], 0); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo ($pct >= 50) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                                                    <?php echo $gr['grade']; ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($m['remarks'] ?? $gr['remarks']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No examination marks recorded yet for this student.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: FEE INVOICES -->
            <div class="tab-pane fade" id="fees" role="tabpanel">
                <div class="content-card">
                    <div class="content-card-header">
                        <span class="fw-bold">Fee Status &amp; Invoices</span>
                        <a href="<?php echo BASE_URL; ?>admin/fees.php" class="btn btn-sm btn-outline-primary">Manage Fees</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Title / Month</th>
                                    <th>Total Due</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th class="text-end">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($feesList)): ?>
                                    <?php foreach ($feesList as $f): 
                                        $netAmount = ($f['amount'] + $f['fine']) - $f['discount'];
                                        $balance = max(0, $netAmount - $f['paid_amount']);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($f['fee_title']); ?></div>
                                                <small class="text-muted">Due: <?php echo formatDate($f['due_date']); ?></small>
                                            </td>
                                            <td><?php echo formatCurrency($netAmount); ?></td>
                                            <td class="text-success fw-semibold"><?php echo formatCurrency($f['paid_amount']); ?></td>
                                            <td class="text-danger fw-semibold"><?php echo formatCurrency($balance); ?></td>
                                            <td><?php echo getFeeBadge($f['status']); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo BASE_URL; ?>admin/fee_receipt.php?id=<?php echo $f['id']; ?>" class="btn btn-sm btn-light border text-primary" title="View Receipt">
                                                    <i class="bi bi-receipt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No fee records found for this student.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: ATTENDANCE LOG -->
            <div class="tab-pane fade" id="attendance" role="tabpanel">
                <div class="content-card">
                    <div class="content-card-header">
                        <span class="fw-bold">Recent Attendance Records (Last 10 Days)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentAttendance)): ?>
                                    <?php foreach ($recentAttendance as $att): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo formatDate($att['attendance_date'], 'l, d M Y'); ?></td>
                                            <td><?php echo getAttendanceBadge($att['status']); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($att['remarks'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No attendance logs available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

