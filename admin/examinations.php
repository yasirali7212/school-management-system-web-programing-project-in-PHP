<?php
/**
 * Examinations & Schedule Management
 * Roots School System
 */

$pageTitle = "Examinations Management";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subAction = $_POST['sub_action'] ?? '';

    // 1. CREATE EXAM
    if ($subAction === 'add_exam') {
        $examName    = trim($_POST['exam_name'] ?? '');
        $examType    = trim($_POST['exam_type'] ?? 'Term Exam');
        $sessionYear = trim($_POST['session_year'] ?? APP_YEAR);
        $startDate   = trim($_POST['start_date'] ?? date('Y-m-d'));
        $endDate     = trim($_POST['end_date'] ?? date('Y-m-d'));

        if (!empty($examName)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO examinations (exam_name, exam_type, session_year, start_date, end_date, is_published)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$examName, $examType, $sessionYear, $startDate, $endDate]);
                setFlash('success', "Examination '{$examName}' created successfully.");
                redirect(BASE_URL . 'admin/examinations.php');
            } catch (Exception $e) {
                setFlash('danger', "Error creating exam: " . $e->getMessage());
            }
        }
    }

    // 2. ADD EXAM SUBJECT SCHEDULE
    if ($subAction === 'add_schedule') {
        $examId    = (int)($_POST['exam_id'] ?? 0);
        $classId   = (int)($_POST['class_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $examDate  = trim($_POST['exam_date'] ?? date('Y-m-d'));
        $startTime = trim($_POST['start_time'] ?? '09:00:00');
        $endTime   = trim($_POST['end_time'] ?? '12:00:00');
        $maxMarks  = (float)($_POST['max_marks'] ?? 100);
        $passMarks = (float)($_POST['pass_marks'] ?? 33);

        if ($examId > 0 && $classId > 0 && $subjectId > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO exam_subjects (exam_id, class_id, subject_id, exam_date, start_time, end_time, max_marks, pass_marks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        exam_date = VALUES(exam_date), 
                        start_time = VALUES(start_time), 
                        end_time = VALUES(end_time),
                        max_marks = VALUES(max_marks),
                        pass_marks = VALUES(pass_marks)
                ");
                $stmt->execute([$examId, $classId, $subjectId, $examDate, $startTime, $endTime, $maxMarks, $passMarks]);
                setFlash('success', "Exam schedule added successfully.");
                redirect(BASE_URL . 'admin/examinations.php');
            } catch (Exception $e) {
                setFlash('danger', "Schedule error: " . $e->getMessage());
            }
        }
    }

    // 3. TOGGLE PUBLISH RESULT
    if ($subAction === 'toggle_publish') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $publishState = (int)($_POST['publish_state'] ?? 0);
        try {
            $pdo->prepare("UPDATE examinations SET is_published = ? WHERE id = ?")->execute([$publishState, $examId]);
            $msg = $publishState ? "Results published for students and parents!" : "Results unpublished.";
            setFlash('success', $msg);
            redirect(BASE_URL . 'admin/examinations.php');
        } catch (Exception $e) {
            setFlash('danger', "Error: " . $e->getMessage());
        }
    }

    // 4. DELETE EXAM
    if ($subAction === 'delete_exam') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM examinations WHERE id = ?")->execute([$examId]);
            setFlash('success', "Examination deleted.");
            redirect(BASE_URL . 'admin/examinations.php');
        } catch (Exception $e) {
            setFlash('danger', "Delete error: " . $e->getMessage());
        }
    }
}

// Fetch Exams List with scheduled subjects count
$exams = $pdo->query("
    SELECT e.*, COUNT(es.id) AS subject_count 
    FROM examinations e
    LEFT JOIN exam_subjects es ON e.id = es.exam_id
    GROUP BY e.id
    ORDER BY e.start_date DESC
")->fetchAll();

// Fetch Exam Schedule Papers
$schedules = $pdo->query("
    SELECT es.*, e.exam_name, c.class_name, sub.subject_name, sub.subject_code,
           COUNT(m.id) AS marks_entered_count
    FROM exam_subjects es
    JOIN examinations e ON es.exam_id = e.id
    JOIN classes c ON es.class_id = c.id
    JOIN subjects sub ON es.subject_id = sub.id
    LEFT JOIN marks m ON es.id = m.exam_subject_id
    GROUP BY es.id
    ORDER BY es.exam_date DESC, es.start_time ASC
")->fetchAll();

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$allSubjects = $pdo->query("SELECT * FROM subjects ORDER BY class_id ASC, subject_name ASC")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-0">Examinations &amp; Schedules</h3>
        <p class="text-muted small mb-0">Create examination terms, configure subject schedules, enter marks, and generate report cards.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>admin/marks.php" class="btn btn-outline-primary btn-sm px-3">
            <i class="bi bi-pencil-square me-1"></i> Enter Marks
        </a>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExamModal">
            <i class="bi bi-plus-circle me-1"></i> Create Exam Term
        </button>
        <button class="btn btn-success btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class="bi bi-calendar-plus me-1"></i> Add Subject Schedule
        </button>
    </div>
</div>

<!-- Examinations List -->
<div class="content-card mb-4">
    <div class="content-card-header">
        <span class="fw-bold text-dark"><i class="bi bi-award-fill text-primary me-2"></i>Active &amp; Past Examinations</span>
        <span class="badge bg-light text-muted border"><?php echo count($exams); ?> Exams</span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Type</th>
                    <th>Session</th>
                    <th>Duration</th>
                    <th>Papers</th>
                    <th>Result Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($exams)): ?>
                    <?php foreach ($exams as $ex): ?>
                        <tr>
                            <td><strong class="text-navy"><?php echo htmlspecialchars($ex['exam_name']); ?></strong></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ex['exam_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($ex['session_year']); ?></td>
                            <td>
                                <span class="small text-muted">
                                    <?php echo formatDate($ex['start_date']); ?> &rarr; <?php echo formatDate($ex['end_date']); ?>
                                </span>
                            </td>
                            <td><span class="badge bg-primary-subtle text-primary"><?php echo $ex['subject_count']; ?> Papers</span></td>
                            <td>
                                <?php if ($ex['is_published']): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Published</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-clock me-1"></i>Draft / Unpublished</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="sub_action" value="toggle_publish">
                                        <input type="hidden" name="exam_id" value="<?php echo $ex['id']; ?>">
                                        <input type="hidden" name="publish_state" value="<?php echo $ex['is_published'] ? '0' : '1'; ?>">
                                        <button type="submit" class="btn btn-outline-secondary" title="<?php echo $ex['is_published'] ? 'Unpublish' : 'Publish Result'; ?>">
                                            <i class="bi bi-<?php echo $ex['is_published'] ? 'eye-slash' : 'megaphone'; ?>"></i>
                                        </button>
                                    </form>
                                    <a href="<?php echo BASE_URL; ?>admin/report_card.php?exam_id=<?php echo $ex['id']; ?>" class="btn btn-outline-primary" title="Generate Report Cards">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this exam and all its marks?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="sub_action" value="delete_exam">
                                        <input type="hidden" name="exam_id" value="<?php echo $ex['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Delete Exam">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No examinations created yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scheduled Exam Papers & Marks Entry Links -->
<div class="content-card">
    <div class="content-card-header">
        <span class="fw-bold text-dark"><i class="bi bi-calendar3-event-fill text-success me-2"></i>Scheduled Exam Papers &amp; Marks Entry</span>
        <span class="badge bg-light text-muted border"><?php echo count($schedules); ?> Scheduled Papers</span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Exam Term</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Max / Pass</th>
                    <th>Marks Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($schedules)): ?>
                    <?php foreach ($schedules as $sc): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?php echo formatDate($sc['exam_date'], 'd M Y'); ?></div>
                                <small class="text-muted"><?php echo substr($sc['start_time'], 0, 5); ?> - <?php echo substr($sc['end_time'], 0, 5); ?></small>
                            </td>
                            <td><span class="fw-medium"><?php echo htmlspecialchars($sc['exam_name']); ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($sc['class_name']); ?></span></td>
                            <td>
                                <strong><?php echo htmlspecialchars($sc['subject_name']); ?></strong>
                                <small class="text-muted">(<?php echo htmlspecialchars($sc['subject_code']); ?>)</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <?php echo (int)$sc['max_marks']; ?> Max / <?php echo (int)$sc['pass_marks']; ?> Pass
                                </span>
                            </td>
                            <td>
                                <?php if ($sc['marks_entered_count'] > 0): ?>
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="bi bi-check-circle me-1"></i><?php echo $sc['marks_entered_count']; ?> Marks Recorded
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis">Pending Entry</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo BASE_URL; ?>admin/marks.php?exam_subject_id=<?php echo $sc['id']; ?>" class="btn btn-sm btn-primary px-3 shadow-sm">
                                    <i class="bi bi-pencil-square me-1"></i> Enter Marks
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No exam papers scheduled yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= ADD EXAM MODAL ================= -->
<div class="modal fade" id="addExamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Create Examination Term</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="add_exam">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Title <span class="text-danger">*</span></label>
                        <input type="text" name="exam_name" class="form-control form-control-sm" required placeholder="e.g. Mid-Term Examinations 2026">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Exam Type</label>
                            <select name="exam_type" class="form-select form-select-sm">
                                <option value="Term Exam">Term Exam</option>
                                <option value="Monthly Test">Monthly Test</option>
                                <option value="Annual Exam">Annual Exam</option>
                                <option value="Quiz / Assessment">Quiz / Assessment</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Session Year</label>
                            <input type="text" name="session_year" class="form-control form-control-sm" value="<?php echo APP_YEAR; ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Create Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= ADD SCHEDULE MODAL ================= -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus me-2"></i>Schedule Exam Paper</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="add_schedule">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Examination <span class="text-danger">*</span></label>
                        <select name="exam_id" class="form-select form-select-sm" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $ex): ?>
                                <option value="<?php echo $ex['id']; ?>"><?php echo htmlspecialchars($ex['exam_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                            <select name="class_id" id="schedClassSelect" class="form-select form-select-sm" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" id="schedSubjectSelect" class="form-select form-select-sm" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Exam Date <span class="text-danger">*</span></label>
                            <input type="date" name="exam_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Start Time</label>
                            <input type="time" name="start_time" class="form-control form-control-sm" value="09:00">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">End Time</label>
                            <input type="time" name="end_time" class="form-control form-control-sm" value="12:00">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Max Marks</label>
                            <input type="number" step="0.5" name="max_marks" class="form-control form-control-sm" value="100.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Pass Marks</label>
                            <input type="number" step="0.5" name="pass_marks" class="form-control form-control-sm" value="33.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Schedule Paper</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const allSubjects = <?php echo json_encode($allSubjects); ?>;

document.addEventListener('DOMContentLoaded', () => {
    const classSel = document.getElementById('schedClassSelect');
    const subSel = document.getElementById('schedSubjectSelect');

    if (classSel && subSel) {
        classSel.addEventListener('change', () => {
            const cId = classSel.value;
            subSel.innerHTML = '<option value="">Select Subject</option>';
            allSubjects.filter(s => s.class_id == cId).forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.subject_name + ' (' + s.subject_code + ')';
                subSel.appendChild(opt);
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

