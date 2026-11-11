<?php
/**
 * Official Student Examination Report Card
 * Roots School System
 */

$pageTitle = "Student Report Card";
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$pdo = getDB();

$studentId = (int)($_GET['student_id'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? 0);

// Load all students and examinations for selector
$allStudents = $pdo->query("
    SELECT s.id, s.admission_no, s.first_name, s.last_name, c.class_name, sec.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.status = 'Active'
    ORDER BY c.id ASC, s.first_name ASC
")->fetchAll();

$allExams = $pdo->query("
    SELECT id, exam_name, session_year, start_date 
    FROM examinations 
    ORDER BY start_date DESC
")->fetchAll();

// If not provided, select first available student and exam
if ($studentId === 0 && !empty($allStudents)) {
    $studentId = (int)$allStudents[0]['id'];
}
if ($examId === 0 && !empty($allExams)) {
    $examId = (int)$allExams[0]['id'];
}

$student = null;
$exam = null;
$subjectResults = [];
$totalMax = 0;
$totalObtained = 0;
$hasFailedSubject = false;

if ($studentId > 0 && $examId > 0) {
    // Student Details
    $sStmt = $pdo->prepare("
        SELECT s.*, c.class_name, sec.section_name, p.father_name, p.mother_name, p.phone as parent_phone
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        WHERE s.id = ?
    ");
    $sStmt->execute([$studentId]);
    $student = $sStmt->fetch();

    // Exam Details
    $eStmt = $pdo->prepare("SELECT * FROM examinations WHERE id = ?");
    $eStmt->execute([$examId]);
    $exam = $eStmt->fetch();

    if ($student && $exam) {
        // Fetch scheduled subjects for this class and any marks recorded
        $resStmt = $pdo->prepare("
            SELECT es.id as exam_subject_id, es.max_marks, es.pass_marks,
                   sub.subject_name, sub.subject_code,
                   m.marks_obtained, m.is_absent, m.remarks
            FROM exam_subjects es
            JOIN subjects sub ON es.subject_id = sub.id
            LEFT JOIN marks m ON (m.exam_subject_id = es.id AND m.student_id = ?)
            WHERE es.exam_id = ? AND es.class_id = ?
            ORDER BY sub.subject_name ASC
        ");
        $resStmt->execute([$studentId, $examId, $student['class_id']]);
        $subjectResults = $resStmt->fetchAll();

        foreach ($subjectResults as $sr) {
            $totalMax += (float)$sr['max_marks'];
            if ($sr['marks_obtained'] !== null && !$sr['is_absent']) {
                $totalObtained += (float)$sr['marks_obtained'];
                if ((float)$sr['marks_obtained'] < (float)$sr['pass_marks']) {
                    $hasFailedSubject = true;
                }
            } else {
                $hasFailedSubject = true;
            }
        }
    }
}

$overallPercentage = ($totalMax > 0) ? round(($totalObtained / $totalMax) * 100, 2) : 0;
$overallGrade = calculateGrade($totalObtained, $totalMax ?: 100);
$overallStatus = (!$hasFailedSubject && $overallPercentage >= 33) ? 'Passed' : 'Needs Improvement';
?>

<!-- Filter & Actions Bar (Hidden during Print) -->
<div class="row align-items-center mb-4 g-3 d-print-none">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Student Report Card</h2>
        <p class="text-muted small mb-0">Generate and print institutional academic performance report cards.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="<?php echo BASE_URL; ?>admin/examinations.php" class="btn btn-outline-secondary btn-sm px-3 me-2">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Report Card
        </button>
    </div>
</div>

<!-- Switcher Bar (Hidden during Print) -->
<div class="card border-0 shadow-sm mb-4 d-print-none">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Student</label>
                <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($allStudents as $st): ?>
                        <option value="<?php echo $st['id']; ?>" <?php echo ($studentId === (int)$st['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?> (<?php echo htmlspecialchars($st['admission_no']); ?>) - Class <?php echo htmlspecialchars($st['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Examination</label>
                <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($allExams as $ex): ?>
                        <option value="<?php echo $ex['id']; ?>" <?php echo ($examId === (int)$ex['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ex['exam_name']); ?> (<?php echo htmlspecialchars($ex['session_year']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-arrow-repeat me-1"></i> Refresh
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($student && $exam): ?>
    <!-- Printable Official Report Card Template -->
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white report-card-sheet" style="border: 2px solid #0f172a !important; border-radius: 8px;">
        <!-- Header -->
        <div class="row align-items-center pb-4 mb-4 border-bottom border-2 border-dark">
            <div class="col-3 text-center">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.svg" alt="Roots Crest" style="width: 85px; height: 85px;">
            </div>
            <div class="col-9 text-center text-md-start">
                <h2 class="fw-bolder text-uppercase tracking-wide mb-1" style="color: #1e3a8a; letter-spacing: 1px;"><?php echo APP_NAME; ?></h2>
                <div class="text-secondary small fw-medium mb-1">Excellence in Quality Education &bull; Registered &amp; Recognized</div>
                <div class="text-muted small">Session: <strong><?php echo htmlspecialchars($exam['session_year']); ?></strong> &bull; Official Student Performance Transcript</div>
                <h4 class="mt-2 fw-bold text-dark text-uppercase"><?php echo htmlspecialchars($exam['exam_name']); ?></h4>
            </div>
        </div>

        <!-- Student & Academic Meta Information -->
        <div class="row g-3 mb-4 p-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Student Name</small>
                <span class="fw-bold fs-6 text-dark"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
            </div>
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Admission No</small>
                <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['admission_no']); ?></span>
            </div>
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Class &amp; Section</small>
                <span class="fw-bold text-dark">Class <?php echo htmlspecialchars($student['class_name']); ?> - <?php echo htmlspecialchars($student['section_name']); ?></span>
            </div>
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Roll Number</small>
                <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['roll_no'] ?: 'N/A'); ?></span>
            </div>
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Father's Name</small>
                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['father_name'] ?: 'N/A'); ?></span>
            </div>
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Date of Birth</small>
                <span class="fw-semibold text-dark"><?php echo $student['dob'] ? date('d M Y', strtotime($student['dob'])) : 'N/A'; ?></span>
            </div>
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Date of Issue</small>
                <span class="fw-semibold text-dark"><?php echo date('d F Y'); ?></span>
            </div>
            <div class="col-sm-6 col-md-3">
                <small class="text-muted d-block">Result Status</small>
                <span class="badge <?php echo ($overallStatus === 'Passed') ? 'bg-success' : 'bg-danger'; ?> px-2 py-1">
                    <?php echo $overallStatus; ?>
                </span>
            </div>
        </div>

        <!-- Subject Scores Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle text-center mb-0" style="border-color: #cbd5e1;">
                <thead style="background-color: #1e3a8a; color: white;">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th class="text-start">Subject</th>
                        <th style="width: 100px;">Max Marks</th>
                        <th style="width: 100px;">Pass Marks</th>
                        <th style="width: 120px;">Marks Obtained</th>
                        <th style="width: 80px;">Grade</th>
                        <th style="width: 100px;">Status</th>
                        <th class="text-start">Teacher Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($subjectResults)): ?>
                        <?php $sn = 1; foreach ($subjectResults as $sub): 
                            $obt = $sub['marks_obtained'];
                            $isAbs = (bool)$sub['is_absent'];
                            $pass = false;
                            $grade = '--';

                            if ($obt !== null && !$isAbs) {
                                $obtVal = (float)$obt;
                                $pass = ($obtVal >= (float)$sub['pass_marks']);
                                $grade = calculateGrade($obtVal, (float)$sub['max_marks']);
                            } elseif ($isAbs) {
                                $grade = 'ABS';
                            }
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo $sn++; ?></td>
                                <td class="text-start fw-semibold">
                                    <?php echo htmlspecialchars($sub['subject_name']); ?>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($sub['subject_code']); ?></small>
                                </td>
                                <td><?php echo (int)$sub['max_marks']; ?></td>
                                <td><?php echo (int)$sub['pass_marks']; ?></td>
                                <td class="fw-bold <?php echo ($pass) ? 'text-primary' : 'text-danger'; ?>">
                                    <?php 
                                    if ($isAbs) echo '<span class="text-danger fw-bold">Absent</span>';
                                    elseif ($obt !== null) echo (float)$obt;
                                    else echo '<span class="text-muted">--</span>';
                                    ?>
                                </td>
                                <td class="fw-bold"><?php echo $grade; ?></td>
                                <td>
                                    <?php if ($isAbs): ?>
                                        <span class="badge bg-danger-subtle text-danger border">Absent</span>
                                    <?php elseif ($obt !== null): ?>
                                        <span class="badge <?php echo $pass ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> border">
                                            <?php echo $pass ? 'Pass' : 'Fail'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start small text-secondary">
                                    <?php echo htmlspecialchars($sub['remarks'] ?: ($pass ? 'Satisfactory' : 'Needs attention')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="py-3 text-muted">No scheduled subjects found for this exam.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="2" class="text-end text-uppercase">Grand Total:</td>
                        <td><?php echo (int)$totalMax; ?></td>
                        <td>--</td>
                        <td class="text-primary fs-6"><?php echo (float)$totalObtained; ?></td>
                        <td class="fs-6"><?php echo $overallGrade; ?></td>
                        <td colspan="2" class="text-start">
                            Percentage: <span class="text-primary"><?php echo $overallPercentage; ?>%</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Summary & Grading Scale Info -->
        <div class="row g-4 mt-2 align-items-end">
            <div class="col-md-7">
                <div class="p-3 rounded border bg-light small">
                    <div class="fw-bold mb-1 text-dark">Grading Scheme:</div>
                    <div class="text-muted">
                        A+ (80% - 100%) &bull; A (70% - 79%) &bull; B (60% - 69%) &bull; C (50% - 59%) &bull; D (33% - 49%) &bull; F (Below 33%)
                    </div>
                </div>
            </div>
            <div class="col-md-5 text-end">
                <div class="d-inline-block text-center border-top border-dark pt-2 px-4 mt-4" style="min-width: 180px;">
                    <div class="fw-bold small text-dark">Class Teacher</div>
                    <small class="text-muted">Signature &amp; Stamp</small>
                </div>
                <div class="d-inline-block text-center border-top border-dark pt-2 px-4 mt-4 ms-3" style="min-width: 180px;">
                    <div class="fw-bold small text-dark">Principal / Headmaster</div>
                    <small class="text-muted">Signature &amp; Seal</small>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
            <i class="bi bi-file-earmark-person text-secondary" style="font-size: 3rem;"></i>
            <h5 class="fw-bold mt-3">Select Student &amp; Examination</h5>
            <p class="text-muted small">Choose an enrolled student and examination from the dropdown above to render the report card.</p>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    body { background: white !important; font-size: 12pt; }
    .app-sidebar, .app-topbar, .app-footer, .sidebar-backdrop, .d-print-none { display: none !important; }
    .app-main, .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .report-card-sheet { border: 2px solid #000 !important; box-shadow: none !important; margin: 0 !important; padding: 20px !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

