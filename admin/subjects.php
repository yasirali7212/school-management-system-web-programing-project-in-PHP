<?php
/**
 * Subject Management & Teacher Assignments
 * Roots School System
 */

$pageTitle = "Subjects & Assignments";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subAction = $_POST['sub_action'] ?? '';

    // 1. ADD SUBJECT
    if ($subAction === 'add_subject') {
        $subjectName = trim($_POST['subject_name'] ?? '');
        $subjectCode = strtoupper(trim($_POST['subject_code'] ?? ''));
        $classId     = (int)($_POST['class_id'] ?? 0);

        if (!empty($subjectName) && !empty($subjectCode) && $classId > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, class_id) VALUES (?, ?, ?)");
                $stmt->execute([$subjectName, $subjectCode, $classId]);
                setFlash('success', "Subject '{$subjectName}' added successfully.");
                redirect(BASE_URL . 'admin/subjects.php');
            } catch (Exception $e) {
                setFlash('danger', "Error adding subject: " . $e->getMessage());
            }
        }
    }

    // 2. ASSIGN TEACHER TO SUBJECT & SECTION
    if ($subAction === 'assign_teacher') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $classId   = (int)($_POST['class_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);

        if ($teacherId > 0 && $classId > 0 && $sectionId > 0 && $subjectId > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO teacher_assignments (teacher_id, class_id, section_id, subject_id)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)
                ");
                $stmt->execute([$teacherId, $classId, $sectionId, $subjectId]);
                setFlash('success', "Teacher assigned to subject successfully.");
                redirect(BASE_URL . 'admin/subjects.php');
            } catch (Exception $e) {
                setFlash('danger', "Assignment error: " . $e->getMessage());
            }
        }
    }

    // 3. DELETE SUBJECT
    if ($subAction === 'delete_subject') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$subjectId]);
            setFlash('success', "Subject deleted.");
            redirect(BASE_URL . 'admin/subjects.php');
        } catch (Exception $e) {
            setFlash('danger', "Delete error: " . $e->getMessage());
        }
    }

    // 4. REMOVE ASSIGNMENT
    if ($subAction === 'delete_assignment') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM teacher_assignments WHERE id = ?")->execute([$assignmentId]);
            setFlash('success', "Assignment removed.");
            redirect(BASE_URL . 'admin/subjects.php');
        } catch (Exception $e) {
            setFlash('danger', "Error removing assignment: " . $e->getMessage());
        }
    }
}

// Fetch Classes, Sections, Teachers
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$allSections = $pdo->query("SELECT * FROM sections ORDER BY class_id ASC, section_name ASC")->fetchAll();
$teachers = $pdo->query("SELECT id, first_name, last_name, employee_id FROM teachers ORDER BY first_name ASC")->fetchAll();

// Fetch Subjects with Class Name
$subjects = $pdo->query("
    SELECT sub.*, c.class_name
    FROM subjects sub
    JOIN classes c ON sub.class_id = c.id
    ORDER BY c.numeric_level ASC, sub.subject_name ASC
")->fetchAll();

// Fetch Current Assignments
$assignments = $pdo->query("
    SELECT ta.id, t.first_name, t.last_name, t.employee_id,
           c.class_name, sec.section_name, sub.subject_name, sub.subject_code
    FROM teacher_assignments ta
    JOIN teachers t ON ta.teacher_id = t.id
    JOIN classes c ON ta.class_id = c.id
    JOIN sections sec ON ta.section_id = sec.id
    JOIN subjects sub ON ta.subject_id = sub.id
    ORDER BY c.numeric_level ASC, sec.section_name ASC, sub.subject_name ASC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-0">Subjects &amp; Faculty Assignments</h3>
        <p class="text-muted small mb-0">Manage course syllabi, subject codes, and assign dedicated teachers to classes and sections.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="bi bi-journal-plus me-1"></i> Add Subject
        </button>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#assignTeacherModal">
            <i class="bi bi-person-check-fill me-1"></i> Assign Faculty
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Subjects Directory -->
    <div class="col-12 col-lg-5">
        <div class="content-card h-100">
            <div class="content-card-header">
                <span class="fw-bold text-dark"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>Curriculum Subjects</span>
                <span class="badge bg-light text-muted border"><?php echo count($subjects); ?> Subjects</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Code</th>
                            <th>Class</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($subjects)): ?>
                            <?php foreach ($subjects as $s): ?>
                                <tr>
                                    <td><strong class="text-navy"><?php echo htmlspecialchars($s['subject_name']); ?></strong></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($s['subject_code']); ?></span></td>
                                    <td><?php echo htmlspecialchars($s['class_name']); ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this subject?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="sub_action" value="delete_subject">
                                            <input type="hidden" name="subject_id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Subject">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No subjects added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Teacher Assignments -->
    <div class="col-12 col-lg-7">
        <div class="content-card h-100">
            <div class="content-card-header">
                <span class="fw-bold text-dark"><i class="bi bi-diagram-3-fill text-success me-2"></i>Faculty Subject Assignments</span>
                <span class="badge bg-light text-muted border"><?php echo count($assignments); ?> Assignments</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Class &amp; Section</th>
                            <th>Subject</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assignments)): ?>
                            <?php foreach ($assignments as $a): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($a['employee_id']); ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-medium"><?php echo htmlspecialchars($a['class_name']); ?></span>
                                        <span class="badge bg-secondary-subtle text-secondary ms-1">Sec <?php echo htmlspecialchars($a['section_name']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <?php echo htmlspecialchars($a['subject_name']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Remove this faculty assignment?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="sub_action" value="delete_assignment">
                                            <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Assignment">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No faculty assignments allocated yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= ADD SUBJECT MODAL ================= -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-plus me-2"></i>Add Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="add_subject">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" class="form-control form-control-sm" required placeholder="e.g. Mathematics, English, Biology">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" name="subject_code" class="form-control form-control-sm text-uppercase" required placeholder="e.g. MATH-09">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Class Level <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select form-select-sm" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= ASSIGN TEACHER MODAL ================= -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-check-fill me-2"></i>Assign Faculty Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="assign_teacher">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Teacher <span class="text-danger">*</span></label>
                        <select name="teacher_id" class="form-select form-select-sm" required>
                            <option value="">Choose Teacher</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name'] . ' (' . $t['employee_id'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                            <select name="class_id" id="assignClassSelect" class="form-select form-select-sm" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Section <span class="text-danger">*</span></label>
                            <select name="section_id" id="assignSectionSelect" class="form-select form-select-sm" required>
                                <option value="">Select Section</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" id="assignSubjectSelect" class="form-select form-select-sm" required>
                            <option value="">Select Subject</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const allSections = <?php echo json_encode($allSections); ?>;
const allSubjects = <?php echo json_encode($subjects); ?>;

document.addEventListener('DOMContentLoaded', () => {
    const classSelect = document.getElementById('assignClassSelect');
    const secSelect = document.getElementById('assignSectionSelect');
    const subSelect = document.getElementById('assignSubjectSelect');

    if (classSelect) {
        classSelect.addEventListener('change', () => {
            const classId = classSelect.value;

            // Filter sections
            secSelect.innerHTML = '<option value="">Select Section</option>';
            allSections.filter(s => s.class_id == classId).forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = 'Section ' + s.section_name;
                secSelect.appendChild(opt);
            });

            // Filter subjects
            subSelect.innerHTML = '<option value="">Select Subject</option>';
            allSubjects.filter(sub => sub.class_id == classId).forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.textContent = sub.subject_name + ' (' + sub.subject_code + ')';
                subSelect.appendChild(opt);
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

