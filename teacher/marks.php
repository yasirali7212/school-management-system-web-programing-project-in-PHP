<?php
/**
 * Teacher Examination Marks Entry
 * Roots School System
 */

$pageTitle = "Enter Examination Marks";
require_once __DIR__ . '/../includes/header.php';
requireRole('teacher');

$pdo = getDB();

// Get Teacher Record
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUser['id']]);
$teacher = $tStmt->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

// Fetch Exam Subjects matching teacher's assigned subjects
$schedulesQuery = $pdo->prepare("
    SELECT DISTINCT es.id, e.exam_name, c.class_name, s.subject_name, es.max_marks, es.pass_marks, es.class_id
    FROM exam_subjects es
    JOIN examinations e ON es.exam_id = e.id
    JOIN classes c ON es.class_id = c.id
    JOIN subjects s ON es.subject_id = s.id
    JOIN teacher_assignments ta ON (ta.class_id = es.class_id AND ta.subject_id = es.subject_id)
    WHERE ta.teacher_id = ?
    ORDER BY e.start_date DESC, c.id ASC
");
$schedulesQuery->execute([$teacherId]);
$teacherExamPapers = $schedulesQuery->fetchAll();

$examSubjectId = (int)($_GET['exam_subject_id'] ?? ($teacherExamPapers[0]['id'] ?? 0));
$selectedExamSubject = null;
$students = [];
$existingMarks = [];

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
        $stStmt = $pdo->prepare("
            SELECT st.id, st.admission_no, st.roll_no, st.first_name, st.last_name, sec.section_name
            FROM students st
            JOIN sections sec ON st.section_id = sec.id
            WHERE st.class_id = ? AND st.status = 'Active'
            ORDER BY sec.section_name ASC, st.roll_no ASC, st.first_name ASC
        ");
        $stStmt->execute([$selectedExamSubject['class_id']]);
        $students = $stStmt->fetchAll();

        $mStmt = $pdo->prepare("SELECT * FROM marks WHERE exam_subject_id = ?");
        $mStmt->execute([$examSubjectId]);
        while ($row = $mStmt->fetch()) {
            $existingMarks[$row['student_id']] = $row;
        }
    }
}

// Handle Form Submission
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

            foreach ($marksData as $studentId => $mark) {
                $studentId = (int)$studentId;
                $isAbsent = isset($absentData[$studentId]) ? 1 : 0;
                $obtained = $isAbsent ? 0.00 : (float)$mark;
                $remark = trim($remarksData[$studentId] ?? '');

                $upsertStmt->execute([$postExamSubjectId, $studentId, $obtained, $isAbsent, $remark, $currentUser['id']]);
            }

            $pdo->commit();
            setFlash('success', "Exam marks successfully updated.");
            redirect(BASE_URL . 'teacher/marks.php?exam_subject_id=' . $postExamSubjectId);
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', "Save error: " . $e->getMessage());
        }
    }
}
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">Enter Examination Marks</h2>
        <p class="text-muted small mb-0">Record exam marks and feedback for your assigned subjects.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>teacher/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Exam Paper Switcher -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Scheduled Exam Subject</label>
                <select name="exam_subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php if (empty($teacherExamPapers)): ?>
                        <option value="">No scheduled exams found for your assigned subjects</option>
                    <?php endif; ?>
                    <?php foreach ($teacherExamPapers as $ep): ?>
                        <option value="<?php echo $ep['id']; ?>" <?php echo ($examSubjectId === (int)$ep['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ep['exam_name']); ?> | Class: <?php echo htmlspecialchars($ep['class_name']); ?> - <?php echo htmlspecialchars($ep['subject_name']); ?> (Max: <?php echo (int)$ep['max_marks']; ?>)
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
    <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
        <div class="card-body p-3 p-md-4">
            <div class="row align-items-center g-3">
                <div class="col-md-7">
                    <span class="badge bg-white text-primary mb-2 fw-semibold"><?php echo htmlspecialchars($selectedExamSubject['session_year']); ?></span>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($selectedExamSubject['exam_name']); ?></h4>
                    <div class="opacity-75 small">
                        Class: <strong><?php echo htmlspecialchars($selectedExamSubject['class_name']); ?></strong> &bull; Subject: <strong><?php echo htmlspecialchars($selectedExamSubject['subject_name']); ?></strong> (<?php echo htmlspecialchars($selectedExamSubject['subject_code']); ?>)
                    </div>
                </div>
                <div class="col-md-5 text-md-end">
                    <div class="d-inline-flex gap-3 text-start">
                        <div class="bg-white bg-opacity-10 rounded px-3 py-2">
                            <small class="d-block text-white text-opacity-75">Max Marks</small>
                            <span class="fs-5 fw-bold"><?php echo (int)$selectedExamSubject['max_marks']; ?></span>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded px-3 py-2">
                            <small class="d-block text-white text-opacity-75">Pass Marks</small>
                            <span class="fs-5 fw-bold"><?php echo (int)$selectedExamSubject['pass_marks']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="save_marks" value="1">
        <input type="hidden" name="exam_subject_id" value="<?php echo $selectedExamSubject['id']; ?>">

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>Student Marks Sheet (<?php echo count($students); ?> Enrolled)</h6>
                <span class="badge bg-light text-secondary border">Max: <?php echo (int)$selectedExamSubject['max_marks']; ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Roll #</th>
                            <th>Student Name</th>
                            <th>Section</th>
                            <th style="width: 140px;">Marks Obtained</th>
                            <th style="width: 100px;" class="text-center">Absent?</th>
                            <th style="width: 100px;" class="text-center">Grade</th>
                            <th>Teacher Feedback</th>
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
                                               value="<?php echo ($marksObt !== '') ? $marksObt : ''; ?>" 
                                               placeholder="0 - <?php echo (int)$selectedExamSubject['max_marks']; ?>"
                                               <?php echo $isAbsent ? 'disabled' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input absent-toggle" 
                                               type="checkbox" 
                                               name="is_absent[<?php echo $st['id']; ?>]" 
                                               data-student-id="<?php echo $st['id']; ?>"
                                               value="1" 
                                               <?php echo $isAbsent ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo ($grade === 'F' || $grade === 'ABS') ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> px-2 py-1 fs-6">
                                            <?php echo $grade ?: '--'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="remarks[<?php echo $st['id']; ?>]" 
                                               class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars($remarks); ?>" 
                                               placeholder="e.g. Good progress">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No students enrolled in this class.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($students)): ?>
                <div class="card-footer bg-white p-3 text-end">
                    <button type="submit" class="btn btn-success px-4 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save All Marks
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <script>
    document.querySelectorAll('.absent-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var studentId = this.getAttribute('data-student-id');
            var markInput = document.querySelector('.mark-input[data-student-id="' + studentId + '"]');
            if (this.checked) {
                markInput.value = '0';
                markInput.disabled = true;
            } else {
                markInput.disabled = false;
            }
        });
    });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

