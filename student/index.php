<?php
/**
 * Student Academic Dashboard
 * Roots School System
 * Unslop: Refined institutional craft, quiet chrome, deliberate typography & numbers
 */

$pageTitle = "Student Dashboard";
require_once __DIR__ . '/../includes/header.php';
requireRole('student');

$pdo = getDB();
$studentUserId = $currentUser['id'];

// Get Student Record
$stStmt = $pdo->prepare("
    SELECT s.*, c.class_name, sec.section_name, p.father_name, p.phone as parent_phone
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents p ON s.parent_id = p.id
    WHERE s.user_id = ?
");
$stStmt->execute([$studentUserId]);
$student = $stStmt->fetch();

$studentId = $student ? (int)$student['id'] : 0;
$classId = $student ? (int)$student['class_id'] : 0;
$sectionId = $student ? (int)$student['section_id'] : 0;

// 1. Attendance KPI
$attStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days
    FROM attendance
    WHERE student_id = ?
");
$attStats->execute([$studentId]);
$att = $attStats->fetch();

$totalDays = (int)($att['total_days'] ?? 0);
$presentDays = (int)($att['present_days'] ?? 0);
$attPercent = ($totalDays > 0) ? round(($presentDays / $totalDays) * 100, 1) : 100;

// 2. Pending Homework Tasks
$hwStmt = $pdo->prepare("
    SELECT hw.*, s.subject_name,
           hs.id as submission_id, hs.status as submission_status, hs.grade
    FROM homework hw
    JOIN subjects s ON hw.subject_id = s.id
    LEFT JOIN homework_submissions hs ON (hs.homework_id = hw.id AND hs.student_id = ?)
    WHERE hw.class_id = ? AND hw.section_id = ?
    ORDER BY hw.due_date ASC
    LIMIT 5
");
$hwStmt->execute([$studentId, $classId, $sectionId]);
$homeworkItems = $hwStmt->fetchAll();

$pendingHwCount = 0;
foreach ($homeworkItems as $item) {
    if (!$item['submission_id']) {
        $pendingHwCount++;
    }
}

// 3. Today's Class Timetable
$todayName = date('l');
$ttStmt = $pdo->prepare("
    SELECT tt.*, s.subject_name, tc.first_name as teacher_first, tc.last_name as teacher_last
    FROM timetables tt
    JOIN subjects s ON tt.subject_id = s.id
    JOIN teachers tc ON tt.teacher_id = tc.id
    WHERE tt.class_id = ? AND tt.section_id = ? AND tt.day_of_week = ?
    ORDER BY tt.start_time ASC
");
$ttStmt->execute([$classId, $sectionId, $todayName]);
$todayClasses = $ttStmt->fetchAll();

// 4. Latest Exam Results
$examResStmt = $pdo->prepare("
    SELECT e.exam_name, es.max_marks, es.pass_marks, sub.subject_name, m.marks_obtained, m.is_absent
    FROM marks m
    JOIN exam_subjects es ON m.exam_subject_id = es.id
    JOIN examinations e ON es.exam_id = e.id
    JOIN subjects sub ON es.subject_id = sub.id
    WHERE m.student_id = ? AND e.is_published = 1
    ORDER BY e.start_date DESC
    LIMIT 5
");
$examResStmt->execute([$studentId]);
$latestMarks = $examResStmt->fetchAll();

// 5. School Announcements
$notices = $pdo->query("
    SELECT * FROM announcements 
    WHERE target_role IN ('all', 'student') AND expiry_date >= CURDATE()
    ORDER BY created_at DESC 
    LIMIT 3
")->fetchAll();
?>

<!-- Section Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <div>
        <h2 class="page-title">Student Academic Dashboard</h2>
        <p class="page-subtitle">
            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> &bull; 
            Class <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?> &bull; 
            Roll #: <strong><?php echo htmlspecialchars($student['roll_no'] ?: 'N/A'); ?></strong> &bull; 
            Adm #: <strong><?php echo htmlspecialchars($student['admission_no']); ?></strong>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>student/profile.php" class="btn btn-outline-secondary btn-sm px-3">
            My profile
        </a>
    </div>
</div>

<!-- Key Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Attendance rate</span>
            <div class="stat-value"><?php echo $attPercent; ?>%</div>
            <div class="stat-sub text-muted"><?php echo $presentDays; ?> of <?php echo $totalDays; ?> days present</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Pending homework</span>
            <div class="stat-value <?php echo ($pendingHwCount > 0) ? 'text-warning' : 'text-success'; ?>"><?php echo $pendingHwCount; ?></div>
            <div class="stat-sub text-muted">Awaiting your submission</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Lectures today</span>
            <div class="stat-value"><?php echo count($todayClasses); ?></div>
            <div class="stat-sub text-muted"><?php echo $todayName; ?> periods</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Enrolment status</span>
            <div class="stat-value text-success">Active</div>
            <div class="stat-sub text-muted">Academic good standing</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Today's Schedule -->
    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Class schedule (<?php echo $todayName; ?>)</span>
                <a href="<?php echo BASE_URL; ?>student/timetable.php" class="small text-decoration-none text-muted">Timetable &rarr;</a>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($todayClasses)): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($todayClasses as $c): ?>
                            <div class="p-2.5 rounded border bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-secondary-subtle text-dark small me-2">
                                        <?php echo date('h:i A', strtotime($c['start_time'])); ?> - <?php echo date('h:i A', strtotime($c['end_time'])); ?>
                                    </span>
                                    <strong class="text-dark"><?php echo htmlspecialchars($c['subject_name']); ?></strong>
                                    <div class="small text-muted mt-0.5">
                                        Instructor: <?php echo htmlspecialchars($c['teacher_first'] . ' ' . $c['teacher_last']); ?>
                                        <?php if ($c['room_no']): ?>
                                            &bull; Room <?php echo htmlspecialchars($c['room_no']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        No scheduled classes today.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Latest Results Snapshot -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Published examination scores</span>
                <a href="<?php echo BASE_URL; ?>student/results.php" class="small text-decoration-none text-muted">All scores &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Subject &amp; Exam</th>
                            <th>Max</th>
                            <th>Obtained</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($latestMarks)): ?>
                            <?php foreach ($latestMarks as $lm): 
                                $obt = (float)$lm['marks_obtained'];
                                $grade = calculateGrade($obt, (float)$lm['max_marks']);
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium text-dark"><?php echo htmlspecialchars($lm['subject_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($lm['exam_name']); ?></small>
                                    </td>
                                    <td><?php echo (int)$lm['max_marks']; ?></td>
                                    <td class="fw-semibold text-primary"><?php echo $obt; ?></td>
                                    <td>
                                        <span class="badge <?php echo ($grade === 'F') ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?>">
                                            <?php echo $grade; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">No published examination results available yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Homework & Circulars Sidebar -->
    <div class="col-12 col-lg-5">
        <!-- Homework Tasks -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Homework assignments</span>
                <a href="<?php echo BASE_URL; ?>student/homework.php" class="small text-decoration-none text-muted">All tasks &rarr;</a>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($homeworkItems)): ?>
                    <div class="d-flex flex-column gap-2.5">
                        <?php foreach ($homeworkItems as $hw): ?>
                            <div class="p-2.5 rounded border bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="badge bg-secondary-subtle text-dark small"><?php echo htmlspecialchars($hw['subject_name']); ?></span>
                                    <small class="text-muted">Due: <?php echo date('d M', strtotime($hw['due_date'])); ?></small>
                                </div>
                                <h6 class="fw-medium text-dark mb-1 small"><?php echo htmlspecialchars($hw['title']); ?></h6>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php if ($hw['submission_id']): ?>
                                        <span class="badge bg-success-subtle text-success small">Submitted</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning small">Pending</span>
                                        <a href="<?php echo BASE_URL; ?>student/homework.php" class="btn btn-outline-secondary btn-sm py-0.5 px-2">Submit</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted small">
                        No pending homework assignments.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- School Circulars -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>School notices</span>
                <a href="<?php echo BASE_URL; ?>student/announcements.php" class="small text-decoration-none text-muted">Notice board &rarr;</a>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($notices)): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($notices as $nt): ?>
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
                        No notices posted.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
