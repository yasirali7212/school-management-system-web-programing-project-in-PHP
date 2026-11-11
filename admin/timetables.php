<?php
/**
 * Class Timetable & Schedule Management
 * Roots School System
 */

$pageTitle = "Class Timetable Management";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. ADD TIMETABLE ENTRY
    if ($action === 'add_period') {
        $classId   = (int)($_POST['class_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $dayOfWeek = trim($_POST['day_of_week'] ?? 'Monday');
        $startTime = trim($_POST['start_time'] ?? '08:30');
        $endTime   = trim($_POST['end_time'] ?? '09:15');
        $roomNo    = trim($_POST['room_no'] ?? '');

        if ($classId > 0 && $sectionId > 0 && $subjectId > 0 && $teacherId > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO timetables (class_id, section_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$classId, $sectionId, $subjectId, $teacherId, $dayOfWeek, $startTime, $endTime, $roomNo]);
                setFlash('success', "Timetable period added successfully.");
                redirect(BASE_URL . "admin/timetables.php?class_id={$classId}&section_id={$sectionId}");
            } catch (Exception $e) {
                setFlash('danger', "Error adding period: " . $e->getMessage());
            }
        } else {
            setFlash('danger', "Please complete all required fields.");
        }
    }

    // 2. DELETE TIMETABLE ENTRY
    if ($action === 'delete_period') {
        $periodId = (int)($_POST['period_id'] ?? 0);
        $retClass = (int)($_POST['ret_class_id'] ?? 0);
        $retSec   = (int)($_POST['ret_section_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM timetables WHERE id = ?")->execute([$periodId]);
            setFlash('success', "Period removed from timetable.");
            redirect(BASE_URL . "admin/timetables.php?class_id={$retClass}&section_id={$retSec}");
        } catch (Exception $e) {
            setFlash('danger', "Delete failed: " . $e->getMessage());
        }
    }
}

// Fetch Classes & Sections
$allClasses = $pdo->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();
$allSections = $pdo->query("SELECT * FROM sections ORDER BY section_name ASC")->fetchAll();
$allSubjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();
$allTeachers = $pdo->query("SELECT id, first_name, last_name, employee_code FROM teachers WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();

// Selected Class & Section
$selectedClassId = (int)($_GET['class_id'] ?? ($allClasses[0]['id'] ?? 0));
$selectedSectionId = (int)($_GET['section_id'] ?? ($allSections[0]['id'] ?? 0));

// Fetch Timetable for Selected Class & Section
$periods = [];
if ($selectedClassId > 0 && $selectedSectionId > 0) {
    $tStmt = $pdo->prepare("
        SELECT t.*, s.subject_name, s.subject_code, tc.first_name as teacher_first, tc.last_name as teacher_last
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.id
        JOIN teachers tc ON t.teacher_id = tc.id
        WHERE t.class_id = ? AND t.section_id = ?
        ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.start_time ASC
    ");
    $tStmt->execute([$selectedClassId, $selectedSectionId]);
    $allPeriods = $tStmt->fetchAll();

    foreach ($allPeriods as $p) {
        $periods[$p['day_of_week']][] = $p;
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<div class="row align-items-center mb-4 g-3 d-print-none">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Class Timetable Management</h2>
        <p class="text-muted small mb-0">Organize weekly lecture periods, rooms, and assigned faculty.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-outline-secondary btn-sm px-3 me-2" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Timetable
        </button>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addPeriodModal">
            <i class="bi bi-plus-circle me-1"></i> Add Timetable Period
        </button>
    </div>
</div>

<!-- Class & Section Selector Bar -->
<div class="card border-0 shadow-sm mb-4 d-print-none">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Class</label>
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClassId === (int)$c['id']) ? 'selected' : ''; ?>>
                            Class <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Section</label>
                <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($allSections as $sec): ?>
                        <option value="<?php echo $sec['id']; ?>" <?php echo ($selectedSectionId === (int)$sec['id']) ? 'selected' : ''; ?>>
                            Section <?php echo htmlspecialchars($sec['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> View Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Timetable Display Grid -->
<div class="timetable-print-sheet">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Weekly Schedule: Class <?php 
                        foreach($allClasses as $c) if($c['id'] == $selectedClassId) echo htmlspecialchars($c['class_name']);
                    ?> - Section <?php 
                        foreach($allSections as $s) if($s['id'] == $selectedSectionId) echo htmlspecialchars($s['section_name']);
                    ?>
                </h5>
                <small class="text-muted">Academic Session <?php echo APP_YEAR; ?></small>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-4">
                <?php foreach ($days as $day): 
                    $daySlots = $periods[$day] ?? [];
                ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 border rounded-3 bg-light bg-opacity-50">
                            <div class="card-header bg-white border-bottom py-2.5 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary"><?php echo $day; ?></span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?php echo count($daySlots); ?> Period(s)
                                </span>
                            </div>
                            <div class="card-body p-2">
                                <?php if (!empty($daySlots)): ?>
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($daySlots as $slot): ?>
                                            <div class="p-2.5 bg-white rounded border shadow-sm position-relative">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <span class="badge bg-secondary-subtle text-secondary small mb-1">
                                                            <i class="bi bi-clock me-1"></i><?php echo date('h:i A', strtotime($slot['start_time'])); ?> - <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                                        </span>
                                                        <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($slot['subject_name']); ?></h6>
                                                        <small class="text-muted d-block">
                                                            <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($slot['teacher_first'] . ' ' . $slot['teacher_last']); ?>
                                                        </small>
                                                        <?php if ($slot['room_no']): ?>
                                                            <small class="text-primary fw-medium">
                                                                <i class="bi bi-geo-alt me-1"></i>Room <?php echo htmlspecialchars($slot['room_no']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <form method="POST" action="" class="d-print-none ms-2" onsubmit="return confirm('Remove this period?');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete_period">
                                                        <input type="hidden" name="period_id" value="<?php echo $slot['id']; ?>">
                                                        <input type="hidden" name="ret_class_id" value="<?php echo $selectedClassId; ?>">
                                                        <input type="hidden" name="ret_section_id" value="<?php echo $selectedSectionId; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm p-1 lh-1" title="Delete Period">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted small">
                                        <i class="bi bi-calendar-x d-block fs-4 text-secondary mb-1"></i>
                                        No periods scheduled.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL: ADD PERIOD ================= -->
<div class="modal fade" id="addPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add Timetable Period</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_period">
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select form-select-sm" required>
                                <?php foreach ($allClasses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClassId === (int)$c['id']) ? 'selected' : ''; ?>>
                                        Class <?php echo htmlspecialchars($c['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Section <span class="text-danger">*</span></label>
                            <select name="section_id" class="form-select form-select-sm" required>
                                <?php foreach ($allSections as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($selectedSectionId === (int)$s['id']) ? 'selected' : ''; ?>>
                                        Section <?php echo htmlspecialchars($s['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose Subject --</option>
                            <?php foreach ($allSubjects as $sub): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subject_name']); ?> (<?php echo htmlspecialchars($sub['subject_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Teacher <span class="text-danger">*</span></label>
                        <select name="teacher_id" class="form-select form-select-sm" required>
                            <option value="">-- Assign Faculty Member --</option>
                            <?php foreach ($allTeachers as $tc): ?>
                                <option value="<?php echo $tc['id']; ?>"><?php echo htmlspecialchars($tc['first_name'] . ' ' . $tc['last_name']); ?> (<?php echo htmlspecialchars($tc['employee_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Day of Week <span class="text-danger">*</span></label>
                            <select name="day_of_week" class="form-select form-select-sm" required>
                                <?php foreach ($days as $d): ?>
                                    <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Room No</label>
                            <input type="text" name="room_no" class="form-control form-control-sm" placeholder="e.g. Room 102">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control form-control-sm" value="08:30" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control form-control-sm" value="09:15" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Add Period</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white !important; font-size: 10pt; }
    .app-sidebar, .app-topbar, .app-footer, .sidebar-backdrop, .d-print-none { display: none !important; }
    .app-main, .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

