<?php
/**
 * Student Homework Portal & Submission Desk
 * Roots School System
 */

$pageTitle = "My Homework & Assignments";
require_once __DIR__ . '/../includes/header.php';
requireRole('student');

$pdo = getDB();

// Get Student Record
$stStmt = $pdo->prepare("SELECT id, first_name, last_name, class_id, section_id FROM students WHERE user_id = ?");
$stStmt->execute([$currentUser['id']]);
$student = $stStmt->fetch();
$studentId = $student ? (int)$student['id'] : 0;
$classId = $student ? (int)$student['class_id'] : 0;
$sectionId = $student ? (int)$student['section_id'] : 0;

// Handle Homework Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_homework'])) {
    verify_csrf();
    $homeworkId     = (int)($_POST['homework_id'] ?? 0);
    $submissionText = trim($_POST['submission_text'] ?? '');
    
    // File upload
    $filePath = null;
    if (!empty($_FILES['submission_file']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/homework/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        if (in_array($ext, $allowed)) {
            $newFileName = 'sub_' . $studentId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $uploadDir . $newFileName)) {
                $filePath = 'uploads/homework/' . $newFileName;
            }
        }
    }

    if ($homeworkId > 0 && (!empty($submissionText) || $filePath)) {
        try {
            $subStmt = $pdo->prepare("
                INSERT INTO homework_submissions (homework_id, student_id, submission_text, file_path, status)
                VALUES (?, ?, ?, ?, 'Submitted')
                ON DUPLICATE KEY UPDATE 
                    submission_text = VALUES(submission_text),
                    file_path = COALESCE(VALUES(file_path), file_path),
                    status = 'Submitted',
                    submission_date = CURRENT_TIMESTAMP
            ");
            $subStmt->execute([$homeworkId, $studentId, $submissionText, $filePath]);
            setFlash('success', "Your homework has been submitted successfully for teacher review!");
            redirect(BASE_URL . 'student/homework.php');
        } catch (Exception $e) {
            setFlash('danger', "Submission error: " . $e->getMessage());
        }
    } else {
        setFlash('danger', "Please provide either typed notes or upload your completed assignment file.");
    }
}

// Fetch All Homework for Student's Class
$hwStmt = $pdo->prepare("
    SELECT hw.*, s.subject_name, s.subject_code, tc.first_name as teacher_first, tc.last_name as teacher_last,
           hs.id as submission_id, hs.submission_date, hs.submission_text, hs.file_path, hs.status as submission_status, hs.grade, hs.feedback
    FROM homework hw
    JOIN subjects s ON hw.subject_id = s.id
    JOIN teachers tc ON hw.teacher_id = tc.id
    LEFT JOIN homework_submissions hs ON (hs.homework_id = hw.id AND hs.student_id = ?)
    WHERE hw.class_id = ? AND hw.section_id = ?
    ORDER BY hw.due_date ASC
");
$hwStmt->execute([$studentId, $classId, $sectionId]);
$assignments = $hwStmt->fetchAll();
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">My Homework &amp; Assignments</h2>
        <p class="text-muted small mb-0">Review instructions, submit completed work, and read teacher feedback.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>student/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-book-half me-2 text-primary"></i>Class Assignments (<?php echo count($assignments); ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Assignment Title &amp; Details</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Due Date</th>
                    <th>Your Status</th>
                    <th>Grade / Feedback</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assignments)): ?>
                    <?php $no = 1; foreach ($assignments as $a): 
                        $isSubmitted = !empty($a['submission_id']);
                        $isReviewed  = ($a['submission_status'] === 'Reviewed');
                        $isOverdue   = (!$isSubmitted && strtotime($a['due_date']) < strtotime(date('Y-m-d')));
                    ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo $no++; ?></td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($a['title']); ?></div>
                                <div class="text-muted small text-truncate" style="max-width: 320px;">
                                    <?php echo htmlspecialchars($a['description']); ?>
                                </div>
                                <?php if ($a['attachment']): ?>
                                    <a href="<?php echo BASE_URL . htmlspecialchars($a['attachment']); ?>" target="_blank" class="small text-primary text-decoration-none mt-1 d-inline-block">
                                        <i class="bi bi-paperclip me-0.5"></i> Download Worksheet
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($a['subject_name']); ?></span>
                            </td>
                            <td>
                                <small class="text-dark fw-medium"><?php echo htmlspecialchars($a['teacher_first'] . ' ' . $a['teacher_last']); ?></small>
                            </td>
                            <td>
                                <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : 'text-secondary'; ?> small">
                                    <i class="bi bi-clock me-1"></i><?php echo date('d M Y', strtotime($a['due_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isReviewed): ?>
                                    <span class="badge bg-success-subtle text-success">Reviewed</span>
                                <?php elseif ($isSubmitted): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis">Submitted</span>
                                <?php elseif ($isOverdue): ?>
                                    <span class="badge bg-danger-subtle text-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isReviewed): ?>
                                    <span class="badge bg-primary px-2 py-1"><?php echo htmlspecialchars($a['grade']); ?></span>
                                    <?php if ($a['feedback']): ?>
                                        <small class="d-block text-muted text-xs mt-1">"<?php echo htmlspecialchars($a['feedback']); ?>"</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">--</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" 
                                        class="btn btn-sm <?php echo $isSubmitted ? 'btn-outline-secondary' : 'btn-primary'; ?> px-3" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#submitHwModal"
                                        data-hw-id="<?php echo $a['id']; ?>"
                                        data-hw-title="<?php echo htmlspecialchars($a['title']); ?>"
                                        data-hw-desc="<?php echo htmlspecialchars($a['description']); ?>"
                                        data-hw-due="<?php echo date('d M Y', strtotime($a['due_date'])); ?>"
                                        data-prev-text="<?php echo htmlspecialchars($a['submission_text'] ?? ''); ?>">
                                    <i class="bi <?php echo $isSubmitted ? 'bi-pencil' : 'bi-upload'; ?> me-1"></i> <?php echo $isSubmitted ? 'Re-Submit' : 'Submit'; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No homework assignments posted for your class yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODAL: SUBMIT HOMEWORK ================= -->
<div class="modal fade" id="submitHwModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up me-2"></i>Submit Homework</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="submit_homework" value="1">
                <input type="hidden" name="homework_id" id="modalHwId" value="0">
                <div class="modal-body p-4">
                    <div class="p-3 mb-3 bg-light rounded border">
                        <small class="text-muted d-block">Assignment:</small>
                        <h6 class="fw-bold text-dark mb-1" id="modalHwTitle">--</h6>
                        <small class="text-muted d-block" id="modalHwDesc">--</small>
                        <small class="text-danger fw-semibold d-block mt-1">Due Date: <span id="modalHwDue">--</span></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Answers / Solution Notes</label>
                        <textarea name="submission_text" id="modalHwText" class="form-control form-control-sm" rows="5" placeholder="Type your answers, explanation, or notes for the teacher..."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Upload Homework Document / Photo (Optional)</label>
                        <input type="file" name="submission_file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <small class="text-muted">Accepted formats: PDF, DOC, Images (Max 5MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Submit Work</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var submitModal = document.getElementById('submitHwModal');
if (submitModal) {
    submitModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        document.getElementById('modalHwId').value = button.getAttribute('data-hw-id');
        document.getElementById('modalHwTitle').textContent = button.getAttribute('data-hw-title');
        document.getElementById('modalHwDesc').textContent = button.getAttribute('data-hw-desc');
        document.getElementById('modalHwDue').textContent = button.getAttribute('data-hw-due');
        document.getElementById('modalHwText').value = button.getAttribute('data-prev-text') || '';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

