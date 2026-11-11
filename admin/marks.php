<?php
/**
 * Examination Marks Entry & Grading
 * Roots School System
 */

$pageTitle = "Enter Examination Marks";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

$examSubjectId = (int)($_GET['exam_subject_id'] ?? 0);
$selectedExamSubject = null;
$students = [];
$existingMarks = [];

// Fetch list of all scheduled exam subjects for dropdown switcher
$schedulesQuery = $pdo->query("
    SELECT es.id, e.exam_name, c.class_name, s.subject_name, es.max_marks, es.pass_marks
    FROM exam_subjects es
    JOIN examinations e ON es.exam_id = e.id
    JOIN classes c ON es.class_id = c.id
    JOIN subjects s ON es.subject_id = s.id
    ORDER BY e.start_date DESC, c.id ASC, s.subject_name ASC
");
$allSchedules = $schedulesQuery->fetchAll();

// If an exam subject is selected, load its details and students
if ($examSubjectId > 0) {
    $stmt = $pdo->prepare("
        SELECT es.*, e.exam_name, e.session_year, c.class_name, s.subject_name, s.subject_code
        FROM exam_subjects es
        JOIN examinations e ON es.exam_id = e.id
        JOIN classes c ON es.class_id = c.id
        JOIN subjects s ON es.subject_id = s.id
        WHERE es.id = ?
    ");
    $stmt->execute([$examSubjectId]);
    $selectedExamSubject = $stmt->fetch();

    if ($selectedExamSubject) {
        // Fetch students in this class
        $stStmt = $pdo->prepare("
            SELECT st.id, st.admission_no, st.roll_no, st.first_name, st.last_name, sec.section_name
            FROM students st
            JOIN sections sec ON st.section_id = sec.id
            WHERE st.class_id = ? AND st.status = 'Active'
            ORDER BY sec.section_name ASC, st.roll_no ASC, st.first_name ASC
        ");
        $stStmt->execute([$selectedExamSubject['class_id']]);
        $students = $stStmt->fetchAll();

        // Fetch existing marks for this exam paper
        $mStmt = $pdo->prepare("
            SELECT * FROM marks WHERE exam_subject_id = ?
        ");
        $mStmt->execute([$examSubjectId]);
        while ($row = $mStmt->fetch()) {
            $existingMarks[$row['student_id']] = $row;
        }
    }
}

// Handle Marks Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    verify_csrf();
    $postExamSubjectId = (int)($_POST['exam_subject_id'] ?? 0);
    $marksData = $_POST['marks'] ?? [];
    $absentData = $_POST['is_absent'] ?? [];
    $remarksData = $_POST['remarks'] ?? [];

    if ($postExamSubjectId > 0 && !empty($marksData)) {
        try {
            $pdo->beginTransaction();
            $upsertStmt = $pdo->prepare("
                INSERT INTO marks (exam_subject_id, student_id, marks_obtained, is_absent, remarks, entered_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    marks_obtained = VALUES(marks_obtained),
                    is_absent = VALUES(is_absent),
                    remarks = VALUES(remarks),
                    entered_by = VALUES(entered_by)
            ");

            $adminUserId = $currentUser['id'];

            foreach ($marksData as $studentId => $mark) {
                $studentId = (int)$studentId;
                $isAbsent = isset($absentData[$studentId]) ? 1 : 0;
                $obtained = $isAbsent ? 0.00 : (float)$mark;
                $remark = trim($remarksData[$studentId] ?? '');

                $upsertStmt->execute([$postExamSubjectId, $studentId, $obtained, $isAbsent, $remark, $adminUserId]);
            }

            $pdo->commit();
            setFlash('success', "Marks saved successfully for all students!");
            redirect(BASE_URL . 'admin/marks.php?exam_subject_id=' . $postExamSubjectId);
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', "Failed to save marks: " . $e->getMessage());
        }
    }
}
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">Enter Examination Marks</h2>
        <p class="text-muted small mb-0">Record and grade subject marks for scheduled examinations.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>admin/examinations.php" class="btn btn-outline-secondary btn-sm px-3 me-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Examinations
        </a>
    </div>
</div>

<!-- Paper Selection Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Scheduled Exam Paper</label>
                <select name="exam_subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Choose Exam &amp; Subject --</option>
                    <?php foreach ($allSchedules as $sc): ?>
                        <option value="<?php echo $sc['id']; ?>" <?php echo ($examSubjectId === (int)$sc['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sc['exam_name']); ?> | Class: <?php echo htmlspecialchars($sc['class_name']); ?> - <?php echo htmlspecialchars($sc['subject_name']); ?> (Max: <?php echo (int)$sc['max_marks']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> Load Students
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExamSubject): ?>
    <!-- Subject Summary Header -->
    <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
        <div class="card-body p-3 p-md-4">
            <div class="row align-items-center g-3">
                <div class="col-md-6">
                    <span class="badge bg-white text-primary mb-2 fw-semibold"><?php echo htmlspecialchars($selectedExamSubject['session_year']); ?></span>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($selectedExamSubject['exam_name']); ?></h4>
                    <div class="opacity-75 small">
                        Class: <strong><?php echo htmlspecialchars($selectedExamSubject['class_name']); ?></strong> &bull; Subject: <strong><?php echo htmlspecialchars($selectedExamSubject['subject_name']); ?></strong> (<?php echo htmlspecialchars($selectedExamSubject['subject_code']); ?>)
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-inline-flex gap-3 text-start">
                        <div class="bg-white bg-opacity-10 rounded px-3 py-2">
                            <small class="d-block text-white text-opacity-75">Max Marks</small>
                            <span class="fs-5 fw-bold"><?php echo (int)$selectedExamSubject['max_marks']; ?></span>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded px-3 py-2">
                            <small class="d-block text-white text-opacity-75">Passing Marks</small>
                            <span class="fs-5 fw-bold"><?php echo (int)$selectedExamSubject['pass_marks']; ?></span>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded px-3 py-2">
                            <small class="d-block text-white text-opacity-75">Total Students</small>
                            <span class="fs-5 fw-bold"><?php echo count($students); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Marks Entry Form -->
    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="save_marks" value="1">
        <input type="hidden" name="exam_subject_id" value="<?php echo $selectedExamSubject['id']; ?>">

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Student Score Sheet</h6>
                <span class="badge bg-light text-secondary border">Max Marks: <?php echo (int)$selectedExamSubject['max_marks']; ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Roll #</th>
                            <th>Student Info</th>
                            <th>Section</th>
                            <th style="width: 140px;">Marks Obtained</th>
                            <th style="width: 110px;" class="text-center">Absent?</th>
                            <th style="width: 120px;" class="text-center">Grade</th>
                            <th>Teacher Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $st): 
                                $rec = $existingMarks[$st['id']] ?? null;
                                $marksObt = $rec ? (float)$rec['marks_obtained'] : '';
                                $isAbsent = $rec ? (bool)$rec['is_absent'] : false;
                                $remarks  = $rec ? $rec['remarks'] : '';
                                $grade = '';
                                if ($rec && !$isAbsent) {
                                    $grade = calculateGrade($marksObt, (float)$selectedExamSubject['max_marks']);
                                } elseif ($rec && $isAbsent) {
                                    $grade = 'ABS';
                                }
                            ?>
                                <tr>
                                    <td class="fw-bold text-secondary"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                        <small class="text-muted">Adm #: <?php echo htmlspecialchars($st['admission_no']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">Sec <?php echo htmlspecialchars($st['section_name']); ?></span>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               step="0.5" 
                                               min="0" 
                                               max="<?php echo (float)$selectedExamSubject['max_marks']; ?>" 
                                               name="marks[<?php echo $st['id']; ?>]" 
                                               class="form-control form-control-sm mark-input" 
                                               data-student-id="<?php echo $st['id']; ?>"
                                               data-max="<?php echo (float)$selectedExamSubject['max_marks']; ?>"
                                               value="<?php echo ($marksObt !== '') ? $marksObt : ''; ?>" 
                                               placeholder="0 - <?php echo (int)$selectedExamSubject['max_marks']; ?>"
                                               <?php echo $isAbsent ? 'disabled' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input absent-toggle" 
                                                   type="checkbox" 
                                                   name="is_absent[<?php echo $st['id']; ?>]" 
                                                   id="absent_<?php echo $st['id']; ?>" 
                                                   data-student-id="<?php echo $st['id']; ?>"
                                                   value="1" 
                                                   <?php echo $isAbsent ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo ($grade === 'F' || $grade === 'ABS') ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> px-2 py-1 fs-6 grade-badge-<?php echo $st['id']; ?>">
                                            <?php echo $grade ?: '--'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="remarks[<?php echo $st['id']; ?>]" 
                                               class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars($remarks); ?>" 
                                               placeholder="Optional remark (e.g. Good performance)">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No active students enrolled in Class <?php echo htmlspecialchars($selectedExamSubject['class_name']); ?>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($students)): ?>
                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Remember to save before leaving this page.</span>
                    <button type="submit" class="btn btn-success px-4 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save All Marks
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <script>
    // Handle toggle between marks input and absent checkbox
    document.querySelectorAll('.absent-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var studentId = this.getAttribute('data-student-id');
            var markInput = document.querySelector('.mark-input[data-student-id="' + studentId + '"]');
            var gradeBadge = document.querySelector('.grade-badge-' + studentId);
            if (this.checked) {
                markInput.value = '0';
                markInput.disabled = true;
                if (gradeBadge) {
                    gradeBadge.textContent = 'ABS';
                    gradeBadge.className = 'badge bg-danger-subtle text-danger px-2 py-1 fs-6 grade-badge-' + studentId;
                }
            } else {
                markInput.disabled = false;
            }
        });
    });
    </script>
<?php else: ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
            <i class="bi bi-journal-check text-primary" style="font-size: 3rem;"></i>
            <h5 class="fw-bold mt-3">Select an Examination Paper</h5>
            <p class="text-muted small">Choose an exam and subject above to enter student marks.</p>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

