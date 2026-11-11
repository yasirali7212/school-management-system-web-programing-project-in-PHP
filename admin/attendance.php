<?php
/**
 * Attendance Management Module
 * Roots School System
 */

$pageTitle = "Attendance Management";
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$pdo = getDB();

// Input Parameters
$selectedClass   = (int)($_GET['class_id'] ?? 9); // Default to Grade 9 if not set
$selectedSection = (int)($_GET['section_id'] ?? 1); // Default to Section A
$selectedDate    = trim($_GET['date'] ?? date('Y-m-d'));

// Handle Attendance Save POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $classId   = (int)($_POST['class_id'] ?? 0);
    $sectionId = (int)($_POST['section_id'] ?? 0);
    $attDate   = trim($_POST['attendance_date'] ?? date('Y-m-d'));
    $statuses  = $_POST['status'] ?? [];
    $remarks   = $_POST['remarks'] ?? [];

    if ($classId > 0 && $sectionId > 0 && !empty($attDate) && !empty($statuses)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, class_id, section_id, attendance_date, status, remarks, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    remarks = VALUES(remarks),
                    recorded_by = VALUES(recorded_by)
            ");

            $recordedBy = $_SESSION['user_id'] ?? null;
            $countSaved = 0;

            foreach ($statuses as $studentId => $status) {
                $rem = trim($remarks[$studentId] ?? '');
                $stmt->execute([(int)$studentId, $classId, $sectionId, $attDate, $status, $rem, $recordedBy]);
                $countSaved++;
            }

            $pdo->commit();
            setFlash('success', "Attendance records for {$countSaved} students recorded successfully for {$attDate}.");
            redirect(BASE_URL . "admin/attendance.php?class_id={$classId}&section_id={$sectionId}&date={$attDate}");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setFlash('danger', "Error saving attendance: " . $e->getMessage());
        }
    }
}

// Fetch Classes & Sections
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$allSections = $pdo->query("SELECT * FROM sections ORDER BY class_id ASC, section_name ASC")->fetchAll();

// Fetch Enrolled Students for the selected class & section
$students = [];
$existingAttendance = [];
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;
$leaveCount = 0;

if ($selectedClass > 0 && $selectedSection > 0) {
    // 1. Get Students
    $sStmt = $pdo->prepare("
        SELECT id, admission_no, roll_no, first_name, last_name, gender, photo
        FROM students
        WHERE class_id = ? AND section_id = ? AND status = 'active'
        ORDER BY CAST(roll_no AS UNSIGNED) ASC, first_name ASC
    ");
    $sStmt->execute([$selectedClass, $selectedSection]);
    $students = $sStmt->fetchAll();

    // 2. Get Existing Attendance for this date
    $aStmt = $pdo->prepare("
        SELECT student_id, status, remarks 
        FROM attendance 
        WHERE class_id = ? AND section_id = ? AND attendance_date = ?
    ");
    $aStmt->execute([$selectedClass, $selectedSection, $selectedDate]);
    while ($r = $aStmt->fetch()) {
        $existingAttendance[$r['student_id']] = $r;
        if ($r['status'] === 'Present') $presentCount++;
        elseif ($r['status'] === 'Absent') $absentCount++;
        elseif ($r['status'] === 'Late') $lateCount++;
        elseif ($r['status'] === 'Leave') $leaveCount++;
    }
}

$totalStudents = count($students);
$attendanceRate = ($totalStudents > 0) ? round(($presentCount / $totalStudents) * 100, 1) : 0;
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-0">Daily Student Attendance</h3>
        <p class="text-muted small mb-0">Mark and review daily attendance logs, track late arrivals, and calculate percentages.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Attendance Reports
        </a>
    </div>
</div>

<!-- Class & Date Selection Bar -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-end">
        <!-- Class -->
        <div class="col-12 col-sm-4 col-md-3">
            <label class="form-label small fw-semibold">Class</label>
            <select name="class_id" id="filterClass" class="form-select form-select-sm" required>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Section -->
        <div class="col-12 col-sm-4 col-md-3">
            <label class="form-label small fw-semibold">Section</label>
            <select name="section_id" id="filterSection" class="form-select form-select-sm" required>
                <?php foreach ($allSections as $s): ?>
                    <?php if ($s['class_id'] == $selectedClass): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($selectedSection == $s['id']) ? 'selected' : ''; ?>>
                            Section <?php echo htmlspecialchars($s['section_name']); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date -->
        <div class="col-12 col-sm-4 col-md-3">
            <label class="form-label small fw-semibold">Attendance Date</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
        </div>

        <!-- Submit Button -->
        <div class="col-12 col-md-3">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-search me-1"></i> Load Attendance Sheet
            </button>
        </div>
    </form>
</div>

<!-- Attendance Summary Card (If records loaded) -->
<?php if (!empty($students)): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="stat-card p-3 text-center">
                <small class="text-muted fw-bold">ENROLLED</small>
                <h4 class="fw-bold mb-0 text-dark"><?php echo $totalStudents; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card p-3 text-center border-start border-4 border-success">
                <small class="text-success fw-bold">PRESENT</small>
                <h4 class="fw-bold mb-0 text-success"><?php echo $presentCount; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card p-3 text-center border-start border-4 border-danger">
                <small class="text-danger fw-bold">ABSENT</small>
                <h4 class="fw-bold mb-0 text-danger"><?php echo $absentCount; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card p-3 text-center border-start border-4 border-warning">
                <small class="text-warning-emphasis fw-bold">LATE</small>
                <h4 class="fw-bold mb-0 text-warning-emphasis"><?php echo $lateCount; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card p-3 text-center border-start border-4 border-info">
                <small class="text-info-emphasis fw-bold">LEAVE</small>
                <h4 class="fw-bold mb-0 text-info-emphasis"><?php echo $leaveCount; ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card p-3 text-center">
                <small class="text-muted fw-bold">RATE</small>
                <h4 class="fw-bold mb-0 text-primary"><?php echo $attendanceRate; ?>%</h4>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Attendance Sheet Table -->
<div class="content-card">
    <div class="content-card-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <div>
            <span class="fw-bold text-dark">
                <i class="bi bi-calendar-check-fill text-primary me-2"></i>Attendance Sheet
                <span class="badge bg-light text-muted border ms-2"><?php echo formatDate($selectedDate); ?></span>
            </span>
        </div>
        <?php if (!empty($students)): ?>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" id="markAllPresentBtn">
                    <i class="bi bi-check-all me-1"></i> Mark All Present
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($students)): ?>
        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>">
            <input type="hidden" name="section_id" value="<?php echo $selectedSection; ?>">
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>">

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 70px;">Roll No</th>
                            <th>Student</th>
                            <th>Admission No</th>
                            <th style="width: 320px;" class="text-center">Attendance Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $st): 
                            $currStatus = $existingAttendance[$st['id']]['status'] ?? 'Present';
                            $currRemarks = $existingAttendance[$st['id']]['remarks'] ?? '';
                        ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($st['roll_no']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($st['first_name'], 0, 1) . substr($st['last_name'], 0, 1)); ?>
                                        </div>
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></span>
                                    </div>
                                </td>
                                <td><span class="text-muted small"><?php echo htmlspecialchars($st['admission_no']); ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <input type="radio" class="btn-check att-radio" name="status[<?php echo $st['id']; ?>]" id="pres_<?php echo $st['id']; ?>" value="Present" <?php echo ($currStatus === 'Present') ? 'checked' : ''; ?> autocomplete="off">
                                        <label class="btn btn-outline-success" for="pres_<?php echo $st['id']; ?>">Present</label>

                                        <input type="radio" class="btn-check att-radio" name="status[<?php echo $st['id']; ?>]" id="abs_<?php echo $st['id']; ?>" value="Absent" <?php echo ($currStatus === 'Absent') ? 'checked' : ''; ?> autocomplete="off">
                                        <label class="btn btn-outline-danger" for="abs_<?php echo $st['id']; ?>">Absent</label>

                                        <input type="radio" class="btn-check att-radio" name="status[<?php echo $st['id']; ?>]" id="late_<?php echo $st['id']; ?>" value="Late" <?php echo ($currStatus === 'Late') ? 'checked' : ''; ?> autocomplete="off">
                                        <label class="btn btn-outline-warning" for="late_<?php echo $st['id']; ?>">Late</label>

                                        <input type="radio" class="btn-check att-radio" name="status[<?php echo $st['id']; ?>]" id="leave_<?php echo $st['id']; ?>" value="Leave" <?php echo ($currStatus === 'Leave') ? 'checked' : ''; ?> autocomplete="off">
                                        <label class="btn btn-outline-info" for="leave_<?php echo $st['id']; ?>">Leave</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?php echo $st['id']; ?>]" class="form-control form-control-sm" placeholder="Optional notes" value="<?php echo htmlspecialchars($currRemarks); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Submit Attendance Footer -->
            <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                <span class="small text-muted">Records will be saved into the database for <?php echo formatDate($selectedDate); ?>.</span>
                <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm">
                    <i class="bi bi-save me-1"></i> Save Attendance Sheet
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="p-5 text-center text-muted">
            <i class="bi bi-calendar-x fs-1 opacity-50 mb-2"></i>
            <h5>No Enrolled Students Found</h5>
            <p class="small text-muted mb-0">Please select a class and section that contains registered students.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Dynamic Sections Script -->
<script>
const allSections = <?php echo json_encode($allSections); ?>;

document.addEventListener('DOMContentLoaded', () => {
    const classSelect = document.getElementById('filterClass');
    const secSelect = document.getElementById('filterSection');

    if (classSelect) {
        classSelect.addEventListener('change', () => {
            const classId = classSelect.value;
            secSelect.innerHTML = '';
            const filtered = allSections.filter(s => s.class_id == classId);
            filtered.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = 'Section ' + s.section_name;
                secSelect.appendChild(opt);
            });
        });
    }

    // Mark All Present Shortcut
    const markAllBtn = document.getElementById('markAllPresentBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', () => {
            document.querySelectorAll('input[type="radio"][value="Present"]').forEach(r => r.checked = true);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

