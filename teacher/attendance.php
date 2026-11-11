<?php
/**
 * Teacher Student Attendance Marking
 * Roots School System
 */

$pageTitle = "Mark Class Attendance";
require_once __DIR__ . '/../includes/header.php';
requireRole('teacher');

$pdo = getDB();

// Get Teacher Record
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUser['id']]);
$teacher = $tStmt->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

// Fetch Teacher's Assigned Classes and Sections
$classesStmt = $pdo->prepare("
    SELECT DISTINCT ta.class_id, ta.section_id, c.class_name, sec.section_name
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    JOIN sections sec ON ta.section_id = sec.id
    WHERE ta.teacher_id = ?
    ORDER BY c.id ASC, sec.section_name ASC
");
$classesStmt->execute([$teacherId]);
$teacherClasses = $classesStmt->fetchAll();

// Handle selection
$selectedClassId = (int)($_GET['class_id'] ?? ($teacherClasses[0]['class_id'] ?? 0));
$selectedSectionId = (int)($_GET['section_id'] ?? ($teacherClasses[0]['section_id'] ?? 0));
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Handle Attendance Save Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postClassId = (int)($_POST['class_id'] ?? 0);
    $postSectionId = (int)($_POST['section_id'] ?? 0);
    $postDate = trim($_POST['date'] ?? date('Y-m-d'));
    $attendanceData = $_POST['attendance'] ?? [];
    $remarksData = $_POST['remarks'] ?? [];

    if ($postClassId > 0 && $postSectionId > 0 && !empty($attendanceData)) {
        try {
            $pdo->beginTransaction();
            $upsertStmt = $pdo->prepare("
                INSERT INTO attendance (student_id, class_id, section_id, date, status, remarks, marked_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status),
                    remarks = VALUES(remarks),
                    marked_by = VALUES(marked_by)
            ");

            foreach ($attendanceData as $studentId => $status) {
                $studentId = (int)$studentId;
                $status = in_array($status, ['Present', 'Absent', 'Late', 'Leave']) ? $status : 'Present';
                $remark = trim($remarksData[$studentId] ?? '');

                $upsertStmt->execute([
                    $studentId,
                    $postClassId,
                    $postSectionId,
                    $postDate,
                    $status,
                    $remark,
                    $currentUser['id']
                ]);
            }

            $pdo->commit();
            setFlash('success', "Attendance saved successfully for " . date('d F Y', strtotime($postDate)));
            redirect(BASE_URL . "teacher/attendance.php?class_id={$postClassId}&section_id={$postSectionId}&date={$postDate}");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', "Error saving attendance: " . $e->getMessage());
        }
    }
}

// Fetch Students & Existing Attendance
$students = [];
$existingAttendance = [];

if ($selectedClassId > 0 && $selectedSectionId > 0) {
    // Active students in class & section
    $stStmt = $pdo->prepare("
        SELECT id, admission_no, roll_no, first_name, last_name, gender, photo
        FROM students
        WHERE class_id = ? AND section_id = ? AND status = 'Active'
        ORDER BY roll_no ASC, first_name ASC
    ");
    $stStmt->execute([$selectedClassId, $selectedSectionId]);
    $students = $stStmt->fetchAll();

    // Fetch existing attendance for this date
    $attStmt = $pdo->prepare("
        SELECT student_id, status, remarks
        FROM attendance
        WHERE class_id = ? AND section_id = ? AND date = ?
    ");
    $attStmt->execute([$selectedClassId, $selectedSectionId, $selectedDate]);
    while ($row = $attStmt->fetch()) {
        $existingAttendance[$row['student_id']] = $row;
    }
}
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">Mark Class Attendance</h2>
        <p class="text-muted small mb-0">Record daily student attendance for your assigned courses.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>teacher/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Class & Date Selector Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-secondary mb-1">My Assigned Class &amp; Section</label>
                <select name="class_and_sec" class="form-select form-select-sm" onchange="
                    var parts = this.value.split('|');
                    this.form.class_id.value = parts[0];
                    this.form.section_id.value = parts[1];
                    this.form.submit();
                ">
                    <?php if (empty($teacherClasses)): ?>
                        <option value="">No classes currently assigned</option>
                    <?php endif; ?>
                    <?php foreach ($teacherClasses as $tc): 
                        $val = $tc['class_id'] . '|' . $tc['section_id'];
                        $isSel = ($selectedClassId == $tc['class_id'] && $selectedSectionId == $tc['section_id']);
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo $isSel ? 'selected' : ''; ?>>
                            Class <?php echo htmlspecialchars($tc['class_name'] . ' - Section ' . $tc['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                <input type="hidden" name="section_id" value="<?php echo $selectedSectionId; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Attendance Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($selectedDate); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> Load Roll Sheet
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClassId > 0 && $selectedSectionId > 0): ?>
    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
        <input type="hidden" name="section_id" value="<?php echo $selectedSectionId; ?>">
        <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Student Roll Sheet (<?php echo count($students); ?> Students)</h6>
                    <small class="text-muted">Date: <?php echo date('l, d F Y', strtotime($selectedDate)); ?></small>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="markAll('Present')">
                        <i class="bi bi-check-all me-1"></i> Mark All Present
                    </button>
                    <button type="submit" class="btn btn-success btn-sm px-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save Attendance
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Roll #</th>
                            <th>Student Name</th>
                            <th style="width: 320px;" class="text-center">Attendance Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $st): 
                                $rec = $existingAttendance[$st['id']] ?? null;
                                $status = $rec ? $rec['status'] : 'Present';
                                $remarks = $rec ? $rec['remarks'] : '';
                            ?>
                                <tr>
                                    <td class="fw-bold text-secondary"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                                <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                                <small class="text-muted">Adm #: <?php echo htmlspecialchars($st['admission_no']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check att-radio" name="attendance[<?php echo $st['id']; ?>]" id="p_<?php echo $st['id']; ?>" value="Present" <?php echo ($status === 'Present') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-success btn-sm py-1.5" for="p_<?php echo $st['id']; ?>">P</label>

                                            <input type="radio" class="btn-check att-radio" name="attendance[<?php echo $st['id']; ?>]" id="l_<?php echo $st['id']; ?>" value="Late" <?php echo ($status === 'Late') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-warning btn-sm py-1.5" for="l_<?php echo $st['id']; ?>">L</label>

                                            <input type="radio" class="btn-check att-radio" name="attendance[<?php echo $st['id']; ?>]" id="a_<?php echo $st['id']; ?>" value="Absent" <?php echo ($status === 'Absent') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-danger btn-sm py-1.5" for="a_<?php echo $st['id']; ?>">A</label>

                                            <input type="radio" class="btn-check att-radio" name="attendance[<?php echo $st['id']; ?>]" id="lv_<?php echo $st['id']; ?>" value="Leave" <?php echo ($status === 'Leave') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-info btn-sm py-1.5" for="lv_<?php echo $st['id']; ?>">Leave</label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="remarks[<?php echo $st['id']; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($remarks); ?>" placeholder="Optional remark">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No active students enrolled in this section.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($students)): ?>
                <div class="card-footer bg-white p-3 text-end">
                    <button type="submit" class="btn btn-success px-4 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save Attendance
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <script>
    function markAll(status) {
        document.querySelectorAll('.att-radio').forEach(function(radio) {
            if (radio.value === status) {
                radio.checked = true;
            }
        });
    }
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

