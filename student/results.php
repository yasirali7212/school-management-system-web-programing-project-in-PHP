<?php
/**
 * Student Examination Results & Report Cards
 * Roots School System
 */

$pageTitle = "Examination Results";
require_once __DIR__ . '/../includes/header.php';
requireRole('student');

$pdo = getDB();

// Get Student Record
$stStmt = $pdo->prepare("
    SELECT s.*, c.class_name, sec.section_name 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.user_id = ?
");
$stStmt->execute([$currentUser['id']]);
$student = $stStmt->fetch();
$studentId = $student ? (int)$student['id'] : 0;
$classId = $student ? (int)$student['class_id'] : 0;

// Fetch Published Examinations
$allExams = $pdo->query("
    SELECT * FROM examinations 
    WHERE is_published = 1 
    ORDER BY start_date DESC
")->fetchAll();

$selectedExamId = (int)($_GET['exam_id'] ?? ($allExams[0]['id'] ?? 0));
$selectedExam = null;
$subjectResults = [];
$totalMax = 0;
$totalObtained = 0;
$hasFailed = false;

if ($selectedExamId > 0 && $studentId > 0) {
    $eStmt = $pdo->prepare("SELECT * FROM examinations WHERE id = ?");
    $eStmt->execute([$selectedExamId]);
    $selectedExam = $eStmt->fetch();

    if ($selectedExam) {
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
        $resStmt->execute([$studentId, $selectedExamId, $classId]);
        $subjectResults = $resStmt->fetchAll();

        foreach ($subjectResults as $sr) {
            $totalMax += (float)$sr['max_marks'];
            if ($sr['marks_obtained'] !== null && !$sr['is_absent']) {
                $totalObtained += (float)$sr['marks_obtained'];
                if ((float)$sr['marks_obtained'] < (float)$sr['pass_marks']) {
                    $hasFailed = true;
                }
            } else {
                $hasFailed = true;
            }
        }
    }
}

$overallPercentage = ($totalMax > 0) ? round(($totalObtained / $totalMax) * 100, 2) : 0;
$overallGrade = calculateGrade($totalObtained, $totalMax ?: 100);
$overallStatus = (!$hasFailed && $overallPercentage >= 33) ? 'Passed' : 'Needs Improvement';
?>

<div class="row align-items-center mb-4 g-3 d-print-none">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Examination Results</h2>
        <p class="text-muted small mb-0">Official academic performance and report cards for published examinations.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if ($selectedExam): ?>
            <a href="<?php echo BASE_URL; ?>admin/report_card.php?student_id=<?php echo $studentId; ?>&exam_id=<?php echo $selectedExamId; ?>" target="_blank" class="btn btn-primary btn-sm px-3 shadow-sm">
                <i class="bi bi-printer me-1"></i> Print Official Report Card
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Exam Selector Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Published Examination</label>
                <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php if (empty($allExams)): ?>
                        <option value="">No examination results published yet</option>
                    <?php endif; ?>
                    <?php foreach ($allExams as $ex): ?>
                        <option value="<?php echo $ex['id']; ?>" <?php echo ($selectedExamId === (int)$ex['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ex['exam_name']); ?> (<?php echo htmlspecialchars($ex['session_year']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> View Result
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam): ?>
    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <span class="stat-label">Total Marks</span>
                    <h3 class="stat-value text-primary mt-1"><?php echo (float)$totalObtained; ?> / <?php echo (int)$totalMax; ?></h3>
                    <small class="text-muted">Total obtained score</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <span class="stat-label">Percentage</span>
                    <h3 class="stat-value text-success mt-1"><?php echo $overallPercentage; ?>%</h3>
                    <small class="text-muted">Aggregate result</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <span class="stat-label">Overall Grade</span>
                    <h3 class="stat-value text-purple mt-1" style="color: #6366f1;"><?php echo $overallGrade; ?></h3>
                    <small class="text-muted">Academic rating</small>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <span class="stat-label">Result Status</span>
                    <h3 class="stat-value <?php echo ($overallStatus === 'Passed') ? 'text-success' : 'text-danger'; ?> mt-1"><?php echo $overallStatus; ?></h3>
                    <small class="text-muted">Final evaluation</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Subject Scores Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Subject-Wise Performance Breakdown</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th class="text-start">Subject</th>
                        <th>Max Marks</th>
                        <th>Passing</th>
                        <th>Marks Obtained</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th class="text-start">Teacher Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($subjectResults)): ?>
                        <?php $sno = 1; foreach ($subjectResults as $sub): 
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
                                <td class="fw-bold text-secondary"><?php echo $sno++; ?></td>
                                <td class="text-start fw-semibold">
                                    <?php echo htmlspecialchars($sub['subject_name']); ?>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($sub['subject_code']); ?></small>
                                </td>
                                <td><?php echo (int)$sub['max_marks']; ?></td>
                                <td><?php echo (int)$sub['pass_marks']; ?></td>
                                <td class="fw-bold <?php echo $pass ? 'text-primary' : 'text-danger'; ?>">
                                    <?php 
                                    if ($isAbs) echo '<span class="text-danger fw-bold">Absent</span>';
                                    elseif ($obt !== null) echo (float)$obt;
                                    else echo '<span class="text-muted">--</span>';
                                    ?>
                                </td>
                                <td class="fw-bold"><?php echo $grade; ?></td>
                                <td>
                                    <?php if ($isAbs): ?>
                                        <span class="badge bg-danger-subtle text-danger">Absent</span>
                                    <?php elseif ($obt !== null): ?>
                                        <span class="badge <?php echo $pass ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                                            <?php echo $pass ? 'Pass' : 'Fail'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start small text-muted">
                                    <?php echo htmlspecialchars($sub['remarks'] ?: ($pass ? 'Good' : 'Needs extra effort')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No subject scores recorded for this exam.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

