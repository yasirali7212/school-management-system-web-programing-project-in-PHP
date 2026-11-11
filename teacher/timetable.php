<?php
/**
 * Teacher Weekly Teaching Timetable
 * Roots School System
 */

$pageTitle = "My Teaching Timetable";
require_once __DIR__ . '/../includes/header.php';
requireRole('teacher');

$pdo = getDB();

// Get Teacher Record
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUser['id']]);
$teacher = $tStmt->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

// Fetch Timetable for this Teacher
$tQuery = $pdo->prepare("
    SELECT tt.*, c.class_name, sec.section_name, s.subject_name, s.subject_code
    FROM timetables tt
    JOIN classes c ON tt.class_id = c.id
    JOIN sections sec ON tt.section_id = sec.id
    JOIN subjects s ON tt.subject_id = s.id
    WHERE tt.teacher_id = ?
    ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), tt.start_time ASC
");
$tQuery->execute([$teacherId]);
$allPeriods = $tQuery->fetchAll();

$scheduleByDay = [];
foreach ($allPeriods as $p) {
    $scheduleByDay[$p['day_of_week']][] = $p;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<div class="row align-items-center mb-4 g-3 d-print-none">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">My Teaching Schedule</h2>
        <p class="text-muted small mb-0">Weekly lecture timetable across assigned classes and subjects.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="<?php echo BASE_URL; ?>teacher/index.php" class="btn btn-outline-secondary btn-sm px-3 me-2">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Timetable
        </button>
    </div>
</div>

<!-- Timetable Display Grid -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Faculty Weekly Schedule &bull; <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                </h5>
                <small class="text-muted">Designation: <?php echo htmlspecialchars($teacher['designation'] ?: 'Faculty'); ?> &bull; Session <?php echo APP_YEAR; ?></small>
            </div>
            <span class="badge bg-primary px-3 py-1.5 fw-semibold"><?php echo count($allPeriods); ?> Weekly Lectures</span>
        </div>
    </div>
    <div class="card-body p-3 p-md-4">
        <div class="row g-4">
            <?php foreach ($days as $day): 
                $slots = $scheduleByDay[$day] ?? [];
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 border rounded-3 bg-light bg-opacity-50">
                        <div class="card-header bg-white border-bottom py-2.5 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-primary"><?php echo $day; ?></span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <?php echo count($slots); ?> Period(s)
                            </span>
                        </div>
                        <div class="card-body p-2">
                            <?php if (!empty($slots)): ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($slots as $sl): ?>
                                        <div class="p-2.5 bg-white rounded border shadow-sm">
                                            <span class="badge bg-secondary-subtle text-secondary small mb-1">
                                                <i class="bi bi-clock me-1"></i><?php echo date('h:i A', strtotime($sl['start_time'])); ?> - <?php echo date('h:i A', strtotime($sl['end_time'])); ?>
                                            </span>
                                            <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($sl['subject_name']); ?></h6>
                                            <div class="small text-muted mt-1">
                                                Class <?php echo htmlspecialchars($sl['class_name'] . ' - ' . $sl['section_name']); ?>
                                            </div>
                                            <?php if ($sl['room_no']): ?>
                                                <small class="text-primary fw-medium d-block mt-0.5">
                                                    <i class="bi bi-geo-alt me-1"></i>Room: <?php echo htmlspecialchars($sl['room_no']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="bi bi-calendar-x d-block fs-4 text-secondary mb-1"></i>
                                    No lectures scheduled.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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

