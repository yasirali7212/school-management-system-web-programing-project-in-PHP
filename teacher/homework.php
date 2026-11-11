<?php
/**
 * Teacher Homework & Assignments Manager
 * Roots School System
 */

$pageTitle = "Homework & Assignments";
require_once __DIR__ . '/../includes/header.php';
requireRole('teacher');

$pdo = getDB();

// Get Teacher Record
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUser['id']]);
$teacher = $tStmt->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

// Fetch Teacher's Assigned Classes, Sections and Subjects
$courses = $pdo->prepare("
    SELECT ta.class_id, ta.section_id, ta.subject_id, c.class_name, sec.section_name, s.subject_name
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    JOIN sections sec ON ta.section_id = sec.id
    JOIN subjects s ON ta.subject_id = s.id
    WHERE ta.teacher_id = ?
");
$courses->execute([$teacherId]);
$assignedCourses = $courses->fetchAll();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. CREATE HOMEWORK
    if ($action === 'create_homework') {
        $courseData = explode('|', $_POST['course_info'] ?? '');
        $classId    = (int)($courseData[0] ?? 0);
        $sectionId  = (int)($courseData[1] ?? 0);
        $subjectId  = (int)($courseData[2] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $assignedDt = trim($_POST['assigned_date'] ?? date('Y-m-d'));
        $dueDt      = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+3 days')));

        // File upload if any
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/homework/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $newFileName = 'hw_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $newFileName)) {
                $attachmentPath = 'uploads/homework/' . $newFileName;
            }
        }

        if ($classId > 0 && $sectionId > 0 && $subjectId > 0 && !empty($title)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO homework (teacher_id, class_id, section_id, subject_id, title, description, assigned_date, due_date, attachment)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$teacherId, $classId, $sectionId, $subjectId, $title, $desc, $assignedDt, $dueDt, $attachmentPath]);
                setFlash('success', "Homework assignment posted successfully.");
                redirect(BASE_URL . 'teacher/homework.php');
            } catch (Exception $e) {
                setFlash('danger', "Error posting homework: " . $e->getMessage());
            }
        } else {
            setFlash('danger', "Please complete all mandatory fields.");
        }
    }

    // 2. REVIEW SUBMISSION (GRADE & FEEDBACK)
    if ($action === 'grade_submission') {
        $subId    = (int)($_POST['submission_id'] ?? 0);
        $grade    = trim($_POST['grade'] ?? '');
        $feedback = trim($_POST['feedback'] ?? '');
        $hwId     = (int)($_POST['homework_id'] ?? 0);

        try {
            $pdo->prepare("UPDATE homework_submissions SET grade = ?, feedback = ?, status = 'Reviewed' WHERE id = ?")
                ->execute([$grade, $feedback, $subId]);
            setFlash('success', "Submission reviewed and graded.");
            redirect(BASE_URL . 'teacher/homework.php?view_submissions=' . $hwId);
        } catch (Exception $e) {
            setFlash('danger', "Grading error: " . $e->getMessage());
        }
    }

    // 3. DELETE HOMEWORK
    if ($action === 'delete_homework') {
        $hwId = (int)($_POST['homework_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM homework WHERE id = ? AND teacher_id = ?")->execute([$hwId, $teacherId]);
            setFlash('success', "Homework task deleted.");
            redirect(BASE_URL . 'teacher/homework.php');
        } catch (Exception $e) {
            setFlash('danger', "Delete failed: " . $e->getMessage());
        }
    }
}

// Submissions View Mode
$viewHwId = (int)($_GET['view_submissions'] ?? 0);
$selectedHw = null;
$submissions = [];

if ($viewHwId > 0) {
    $hStmt = $pdo->prepare("
        SELECT hw.*, c.class_name, sec.section_name, s.subject_name
        FROM homework hw
        JOIN classes c ON hw.class_id = c.id
        JOIN sections sec ON hw.section_id = sec.id
        JOIN subjects s ON hw.subject_id = s.id
        WHERE hw.id = ? AND hw.teacher_id = ?
    ");
    $hStmt->execute([$viewHwId, $teacherId]);
    $selectedHw = $hStmt->fetch();

    if ($selectedHw) {
        $sStmt = $pdo->prepare("
            SELECT hs.*, st.first_name, st.last_name, st.admission_no, st.roll_no
            FROM homework_submissions hs
            JOIN students st ON hs.student_id = st.id
            WHERE hs.homework_id = ?
            ORDER BY hs.submission_date DESC
        ");
        $sStmt->execute([$viewHwId]);
        $submissions = $sStmt->fetchAll();
    }
}

// All Teacher's Homework Tasks
$allHwStmt = $pdo->prepare("
    SELECT hw.*, c.class_name, sec.section_name, s.subject_name,
           (SELECT COUNT(*) FROM homework_submissions hs WHERE hs.homework_id = hw.id) as total_submitted,
           (SELECT COUNT(*) FROM students st WHERE st.class_id = hw.class_id AND st.section_id = hw.section_id AND st.status = 'Active') as total_students
    FROM homework hw
    JOIN classes c ON hw.class_id = c.id
    JOIN sections sec ON hw.section_id = sec.id
    JOIN subjects s ON hw.subject_id = s.id
    WHERE hw.teacher_id = ?
    ORDER BY hw.id DESC
");
$allHwStmt->execute([$teacherId]);
$homeworkList = $allHwStmt->fetchAll();
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Homework &amp; Assignments</h2>
        <p class="text-muted small mb-0">Assign student homework, specify deadlines, and grade submitted tasks.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if ($viewHwId > 0): ?>
            <a href="<?php echo BASE_URL; ?>teacher/homework.php" class="btn btn-outline-secondary btn-sm px-3 me-2">
                <i class="bi bi-arrow-left me-1"></i> All Homework
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createHwModal">
            <i class="bi bi-plus-circle me-1"></i> Assign New Homework
        </button>
    </div>
</div>

<?php if ($selectedHw): ?>
    <!-- Submissions Management View -->
    <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
        <div class="card-body p-4">
            <span class="badge bg-white text-primary mb-2 fw-semibold">Class <?php echo htmlspecialchars($selectedHw['class_name'] . ' - ' . $selectedHw['section_name']); ?></span>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($selectedHw['title']); ?></h4>
            <div class="opacity-75 small mb-2">Subject: <strong><?php echo htmlspecialchars($selectedHw['subject_name']); ?></strong> &bull; Due: <strong><?php echo date('d M Y', strtotime($selectedHw['due_date'])); ?></strong></div>
            <p class="small mb-0 text-white text-opacity-90"><?php echo nl2br(htmlspecialchars($selectedHw['description'])); ?></p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-inbox me-2 text-primary"></i>Student Submissions (<?php echo count($submissions); ?>)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">Roll #</th>
                        <th>Student Name</th>
                        <th>Submission Date</th>
                        <th>Student Work / Attachment</th>
                        <th>Status</th>
                        <th>Grade</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($submissions)): ?>
                        <?php foreach ($submissions as $sub): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($sub['roll_no'] ?: '-'); ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></div>
                                    <small class="text-muted">Adm #: <?php echo htmlspecialchars($sub['admission_no']); ?></small>
                                </td>
                                <td>
                                    <small class="text-secondary"><?php echo date('d M Y, h:i A', strtotime($sub['submission_date'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($sub['submission_text']): ?>
                                        <div class="small text-dark mb-1 bg-light p-2 rounded"><?php echo nl2br(htmlspecialchars($sub['submission_text'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($sub['file_path']): ?>
                                        <a href="<?php echo BASE_URL . htmlspecialchars($sub['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-xs py-0.5 px-2">
                                            <i class="bi bi-file-earmark-arrow-down me-1"></i> Download File
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sub['status'] === 'Reviewed'): ?>
                                        <span class="badge bg-success-subtle text-success">Reviewed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Pending Review</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($sub['grade'] ?: '--'); ?></td>
                                <td class="text-end">
                                    <button type="button" 
                                            class="btn btn-sm btn-primary px-2.5 py-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#gradeModal"
                                            data-sub-id="<?php echo $sub['id']; ?>"
                                            data-student="<?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?>"
                                            data-grade="<?php echo htmlspecialchars($sub['grade'] ?? ''); ?>"
                                            data-feedback="<?php echo htmlspecialchars($sub['feedback'] ?? ''); ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Grade
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No student submissions received yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- All Homework Assignments Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Assignment Title</th>
                        <th>Class &amp; Section</th>
                        <th>Subject</th>
                        <th>Due Date</th>
                        <th class="text-center">Submissions</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($homeworkList)): ?>
                        <?php $hNo = 1; foreach ($homeworkList as $h): 
                            $isOverdue = (strtotime($h['due_date']) < strtotime(date('Y-m-d')));
                        ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo $hNo++; ?></td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($h['title']); ?></div>
                                    <small class="text-muted d-block text-truncate" style="max-width: 320px;"><?php echo htmlspecialchars($h['description']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        Class <?php echo htmlspecialchars($h['class_name'] . ' - ' . $h['section_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($h['subject_name']); ?></span>
                                </td>
                                <td>
                                    <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : 'text-secondary'; ?> small">
                                        <i class="bi bi-clock me-1"></i><?php echo date('d M Y', strtotime($h['due_date'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo BASE_URL; ?>teacher/homework.php?view_submissions=<?php echo $h['id']; ?>" class="btn btn-outline-primary btn-sm px-2.5 py-1">
                                        <i class="bi bi-folder-check me-1"></i> <?php echo $h['total_submitted']; ?> / <?php echo $h['total_students']; ?>
                                    </a>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?php echo BASE_URL; ?>teacher/homework.php?view_submissions=<?php echo $h['id']; ?>" class="btn btn-sm btn-primary px-2.5 py-1" title="Review Submissions">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this homework task?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_homework">
                                            <input type="hidden" name="homework_id" value="<?php echo $h['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2.5 py-1" title="Delete Task">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No homework assignments created yet. Click "Assign New Homework" above.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ================= MODAL: CREATE HOMEWORK ================= -->
<div class="modal fade" id="createHwModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Assign Homework Task</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_homework">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Class &amp; Subject <span class="text-danger">*</span></label>
                        <select name="course_info" class="form-select form-select-sm" required>
                            <option value="">-- Choose Class, Section &amp; Subject --</option>
                            <?php foreach ($assignedCourses as $ac): 
                                $val = $ac['class_id'] . '|' . $ac['section_id'] . '|' . $ac['subject_id'];
                            ?>
                                <option value="<?php echo $val; ?>">
                                    Class <?php echo htmlspecialchars($ac['class_name'] . ' - ' . $ac['section_name']); ?> : <?php echo htmlspecialchars($ac['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assignment Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Chapter 4 Practice Exercises &amp; Summary">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Assigned Date <span class="text-danger">*</span></label>
                            <input type="date" name="assigned_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Submission Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Detailed Instructions / Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-sm" rows="4" required placeholder="Describe questions to solve, textbook pages, formatting requirements..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Attachment / Worksheet (Optional)</label>
                        <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.png">
                        <small class="text-muted">PDF or Word document (Max 5MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Post Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL: GRADE SUBMISSION ================= -->
<div class="modal fade" id="gradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Review &amp; Grade Task</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="grade_submission">
                <input type="hidden" name="submission_id" id="modalSubId" value="0">
                <input type="hidden" name="homework_id" value="<?php echo $viewHwId; ?>">
                <div class="modal-body p-4">
                    <div class="p-2 mb-3 bg-light rounded border">
                        <small class="text-muted d-block">Student:</small>
                        <strong class="text-dark fs-6" id="modalStudentName">--</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Grade / Score <span class="text-danger">*</span></label>
                        <input type="text" name="grade" id="modalGradeInput" class="form-control form-control-sm" required placeholder="e.g. A+, 9/10, Excellent">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Teacher Remarks / Feedback</label>
                        <textarea name="feedback" id="modalFeedbackInput" class="form-control form-control-sm" rows="3" placeholder="Constructive remarks and feedback for student..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4">Save Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var gradeModal = document.getElementById('gradeModal');
if (gradeModal) {
    gradeModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        document.getElementById('modalSubId').value = button.getAttribute('data-sub-id');
        document.getElementById('modalStudentName').textContent = button.getAttribute('data-student');
        document.getElementById('modalGradeInput').value = button.getAttribute('data-grade');
        document.getElementById('modalFeedbackInput').value = button.getAttribute('data-feedback');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

