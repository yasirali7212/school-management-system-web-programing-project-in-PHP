<?php
/**
 * Teacher Dashboard & Faculty Desk
 * Roots School System
 * Unslop: Refined institutional craft, quiet chrome, deliberate typography & numbers
 */

$pageTitle = "Faculty Desk";
require_once __DIR__ . '/../includes/header.php';
requireRole('teacher');

$pdo = getDB();
$teacherUserId = $currentUser['id'];

// Get Teacher Record
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$teacherUserId]);
$teacher = $tStmt->fetch();

$teacherId = $teacher ? (int)$teacher['id'] : 0;

// Assigned Classes & Subjects
$assignedQuery = $pdo->prepare("
    SELECT ta.*, c.class_name, sec.section_name, s.subject_name, s.subject_code
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    JOIN sections sec ON ta.section_id = sec.id
    JOIN subjects s ON ta.subject_id = s.id
    WHERE ta.teacher_id = ?
");
$assignedQuery->execute([$teacherId]);
$assignments = $assignedQuery->fetchAll();

// Unique Classes count
$assignedClassIds = array_unique(array_column($assignments, 'class_id'));
$classCount = count($assignedClassIds);
$subjectCount = count(array_unique(array_column($assignments, 'subject_id')));

// Total Students in teacher's assigned classes
$totalStudents = 0;
if (!empty($assignedClassIds)) {
    $inClause = implode(',', array_map('intval', $assignedClassIds));
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id IN ({$inClause}) AND status = 'Active'")->fetchColumn();
}

// Today's Teaching Schedule
$todayName = date('l');
$todayScheduleStmt = $pdo->prepare("
    SELECT tt.*, c.class_name, sec.section_name, s.subject_name
    FROM timetables tt
    JOIN classes c ON tt.class_id = c.id
    JOIN sections sec ON tt.section_id = sec.id
    JOIN subjects s ON tt.subject_id = s.id
    WHERE tt.teacher_id = ? AND tt.day_of_week = ?
    ORDER BY tt.start_time ASC
");
$todayScheduleStmt->execute([$teacherId, $todayName]);
$todayPeriods = $todayScheduleStmt->fetchAll();

// Recent Homework Posted
$hwStmt = $pdo->prepare("
    SELECT hw.*, c.class_name, sec.section_name, s.subject_name,
           (SELECT COUNT(*) FROM homework_submissions hs WHERE hs.homework_id = hw.id) as submission_count
    FROM homework hw
    JOIN classes c ON hw.class_id = c.id
    JOIN sections sec ON hw.section_id = sec.id
    JOIN subjects s ON hw.subject_id = s.id
    WHERE hw.teacher_id = ?
    ORDER BY hw.id DESC
    LIMIT 5
");
$hwStmt->execute([$teacherId]);
$recentHomework = $hwStmt->fetchAll();

// Announcements for Teachers
$noticesStmt = $pdo->prepare("
    SELECT * FROM announcements 
    WHERE target_role IN ('all', 'teacher') AND expiry_date >= CURDATE()
    ORDER BY created_at DESC 
    LIMIT 4
");
$noticesStmt->execute();
$announcements = $noticesStmt->fetchAll();
?>

<!-- Section Header (Pass 4: No loud exclamation greeting banners) -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <div>
        <h2 class="page-title">Faculty Academic Desk</h2>
        <p class="page-subtitle">
            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?> &bull; 
            Employee ID: <strong><?php echo htmlspecialchars($teacher['employee_code']); ?></strong> &bull; 
            Designation: <strong><?php echo htmlspecialchars($teacher['designation'] ?: 'Faculty Member'); ?></strong>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>teacher/attendance.php" class="btn btn-primary btn-sm px-3">
            Mark attendance
        </a>
    </div>
</div>

<!-- Key Stat Cards (Pass 7: Numbers first, quiet chrome) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Assigned classes</span>
            <div class="stat-value"><?php echo $classCount; ?></div>
            <div class="stat-sub text-muted">Teaching wings &amp; sections</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Subjects</span>
            <div class="stat-value"><?php echo $subjectCount; ?></div>
            <div class="stat-sub text-muted">Curriculum disciplines</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Enrolled pupils</span>
            <div class="stat-value"><?php echo $totalStudents; ?></div>
            <div class="stat-sub text-success">Active class students</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Lectures today</span>
            <div class="stat-value"><?php echo count($todayPeriods); ?></div>
            <div class="stat-sub text-muted"><?php echo $todayName; ?> timetable periods</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Today's Teaching Schedule -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Today's teaching schedule (<?php echo $todayName; ?>)</span>
                <a href="<?php echo BASE_URL; ?>teacher/timetable.php" class="small text-decoration-none text-muted">Full schedule &rarr;</a>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($todayPeriods)): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($todayPeriods as $p): ?>
                            <div class="p-2.5 rounded border bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-secondary-subtle text-dark small me-2">
                                        <?php echo date('h:i A', strtotime($p['start_time'])); ?> - <?php echo date('h:i A', strtotime($p['end_time'])); ?>
                                    </span>
                                    <strong class="text-dark"><?php echo htmlspecialchars($p['subject_name']); ?></strong>
                                    <div class="small text-muted mt-0.5">
                                        Class <?php echo htmlspecialchars($p['class_name'] . ' - ' . $p['section_name']); ?>
                                        <?php if ($p['room_no']): ?>
                                            &bull; Room <?php echo htmlspecialchars($p['room_no']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="<?php echo BASE_URL; ?>teacher/attendance.php?class_id=<?php echo $p['class_id']; ?>&section_id=<?php echo $p['section_id']; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2.5">
                                    Roll call
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        No lectures scheduled for you today.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Circulars -->
    <div class="col-12 col-lg-5">
        <!-- Quick Operational Actions -->
        <div class="card mb-3">
            <div class="card-header">
                <span>Quick actions</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>teacher/attendance.php" class="btn btn-outline-secondary w-100 text-start p-2.5 d-block">
                            <div class="fw-semibold text-dark small">Attendance</div>
                            <span class="text-muted" style="font-size: 0.75rem;">Daily student roll call</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>teacher/marks.php" class="btn btn-outline-secondary w-100 text-start p-2.5 d-block">
                            <div class="fw-semibold text-dark small">Exam marks</div>
                            <span class="text-muted" style="font-size: 0.75rem;">Grade examination papers</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>teacher/homework.php" class="btn btn-outline-secondary w-100 text-start p-2.5 d-block">
                            <div class="fw-semibold text-dark small">Homework</div>
                            <span class="text-muted" style="font-size: 0.75rem;">Post assignments</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo BASE_URL; ?>teacher/students.php" class="btn btn-outline-secondary w-100 text-start p-2.5 d-block">
                            <div class="fw-semibold text-dark small">Class roster</div>
                            <span class="text-muted" style="font-size: 0.75rem;">Pupil directory</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff Circulars -->
        <div class="card">
            <div class="card-header">
                <span>Staff circulars</span>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($announcements)): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($announcements as $nt): ?>
                            <div class="pb-2 border-bottom">
                                <h6 class="fw-medium text-dark small mb-1"><?php echo htmlspecialchars($nt['title']); ?></h6>
                                <p class="text-muted small mb-1" style="font-size: 0.8rem; line-height: 1.4;">
                                    <?php echo htmlspecialchars(substr($nt['content'], 0, 90)) . (strlen($nt['content']) > 90 ? '...' : ''); ?>
                                </p>
                                <span class="text-muted" style="font-size: 0.72rem;">
                                    Published: <?php echo formatDate($nt['start_date']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted small">
                        No active circulars at this moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
