<?php
/**
 * Administrator Executive Overview
 * Roots School System
 * Unslop: Refined institutional craft, quiet chrome, deliberate typography & numbers
 */

$pageTitle = "Executive Overview";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Fetch Core Counts and Operations
$totalStudents = 0;
$totalTeachers = 0;
$totalClasses = 0;
$totalSections = 0;
$totalFeeCollected = 0.00;
$totalFeePending = 0.00;
$todayAttendanceRate = 0;
$todayPresent = 0;
$todayTotal = 0;

$recentStudents = [];
$recentAnnouncements = [];
$feeChartLabels = [];
$feeChartData = [];
$attendanceChartData = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0];

if ($pdo) {
    // 1. Total Active Students
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();

    // 2. Total Active Teachers
    $totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'Active'")->fetchColumn();

    // 3. Total Classes and Sections
    $totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $totalSections = (int)$pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();

    // 4. Financial Status
    $feeSummary = $pdo->query("
        SELECT 
            COALESCE(SUM(paid_amount), 0) AS total_collected,
            COALESCE(SUM(amount + fine - discount - paid_amount), 0) AS total_pending
        FROM fees
    ")->fetch();
    $totalFeeCollected = (float)($feeSummary['total_collected'] ?? 0);
    $totalFeePending   = max(0, (float)($feeSummary['total_pending'] ?? 0));

    // 5. Today's Attendance
    $todayDate = date('Y-m-d');
    $attStmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM attendance 
        WHERE date = ? 
        GROUP BY status
    ");
    $attStmt->execute([$todayDate]);
    while ($row = $attStmt->fetch()) {
        $st = $row['status'];
        if (isset($attendanceChartData[$st])) {
            $attendanceChartData[$st] = (int)$row['count'];
        }
        $todayTotal += (int)$row['count'];
        if ($st === 'Present') {
            $todayPresent = (int)$row['count'];
        }
    }
    if ($todayTotal > 0) {
        $todayAttendanceRate = round(($todayPresent / $todayTotal) * 100, 1);
    }

    // 6. Recent Student Admissions
    $recentStudents = $pdo->query("
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.admission_date, c.class_name, sec.section_name
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE s.status = 'Active'
        ORDER BY s.id DESC 
        LIMIT 5
    ")->fetchAll();

    // 7. Recent Announcements
    $recentAnnouncements = $pdo->query("
        SELECT * FROM announcements 
        ORDER BY id DESC 
        LIMIT 4
    ")->fetchAll();

    // 8. Monthly Fee Collection Data
    $feeMonthlyStmt = $pdo->query("
        SELECT month, SUM(paid_amount) as total 
        FROM fees 
        GROUP BY month 
        ORDER BY id ASC 
        LIMIT 6
    ");
    while ($r = $feeMonthlyStmt->fetch()) {
        $feeChartLabels[] = $r['month'];
        $feeChartData[] = (float)$r['total'];
    }
}
?>

<!-- Section Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <div>
        <h2 class="page-title">Executive Overview</h2>
        <p class="page-subtitle">Academic metrics, operations, and revenue records &bull; Session <?php echo APP_YEAR; ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>admin/attendance.php" class="btn btn-outline-secondary btn-sm px-3">
            Mark attendance
        </a>
        <a href="<?php echo BASE_URL; ?>admin/students.php?action=new" class="btn btn-primary btn-sm px-3">
            Admit student
        </a>
    </div>
</div>

<!-- Primary Metric Grid (Pass 7: Clean numbers first, no noisy pastel circles) -->
<div class="row g-3 mb-4">
    <!-- Total Students -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Total enrolled</span>
            <div class="stat-value"><?php echo number_format($totalStudents); ?></div>
            <div class="stat-sub text-success">Active pupils on roll</div>
        </div>
    </div>

    <!-- Faculty -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Teaching faculty</span>
            <div class="stat-value"><?php echo number_format($totalTeachers); ?></div>
            <div class="stat-sub text-muted">Instructors &amp; specialists</div>
        </div>
    </div>

    <!-- Academic Structure -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Academic wings</span>
            <div class="stat-value"><?php echo $totalClasses; ?> <span class="fs-6 fw-normal text-muted">classes</span></div>
            <div class="stat-sub text-muted"><?php echo $totalSections; ?> total active sections</div>
        </div>
    </div>

    <!-- Attendance Today -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <span class="stat-label">Attendance today</span>
            <div class="stat-value"><?php echo ($todayTotal > 0) ? $todayAttendanceRate . '%' : '--'; ?></div>
            <div class="stat-sub text-muted">
                <?php echo ($todayTotal > 0) ? "{$todayPresent} of {$todayTotal} present" : "Awaiting roll call"; ?>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Metric Row: Financial Ledger -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="stat-label">Total fees collected</span>
                    <div class="fs-4 fw-bold text-success mb-1"><?php echo formatCurrency($totalFeeCollected); ?></div>
                    <div class="stat-sub">Cumulative verified student deposits</div>
                </div>
                <div class="text-end">
                    <span class="badge bg-success-subtle text-success">Settled</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="stat-label">Outstanding balance</span>
                    <div class="fs-4 fw-bold <?php echo ($totalFeePending > 0) ? 'text-danger' : 'text-success'; ?> mb-1">
                        <?php echo formatCurrency($totalFeePending); ?>
                    </div>
                    <div class="stat-sub">Pending vouchers across all grades</div>
                </div>
                <div class="text-end">
                    <span class="badge <?php echo ($totalFeePending > 0) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?>">
                        <?php echo ($totalFeePending > 0) ? 'Action Required' : 'Cleared'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Visual Analytics: Restrained, One Accent, Tabular Data -->
<div class="row g-3 mb-4">
    <!-- Monthly Fee Collections -->
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Monthly revenue inflow</span>
                <span class="stat-sub">Session <?php echo APP_YEAR; ?></span>
            </div>
            <div class="card-body">
                <canvas id="feeCollectionChart" style="max-height: 260px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Attendance Breakdown -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Today's attendance</span>
                <span class="stat-sub"><?php echo date('d M Y'); ?></span>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <?php if ($todayTotal > 0): ?>
                    <div style="width: 200px; height: 200px;">
                        <canvas id="attendanceDoughnutChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x fs-2 opacity-50 mb-2 d-block"></i>
                        <p class="small mb-2">No attendance registered for today yet.</p>
                        <a href="<?php echo BASE_URL; ?>admin/attendance.php" class="btn btn-sm btn-outline-primary">Take attendance</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tables: Recent Admissions & Circulars -->
<div class="row g-3">
    <!-- Recent Student Admissions -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent admissions</span>
                <a href="<?php echo BASE_URL; ?>admin/students.php" class="small text-decoration-none text-muted">View all &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission #</th>
                            <th>Class &amp; Section</th>
                            <th>Date</th>
                            <th class="text-end">Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentStudents)): ?>
                            <?php foreach ($recentStudents as $st): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                                            </div>
                                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-secondary-subtle"><?php echo htmlspecialchars($st['admission_no']); ?></span></td>
                                    <td>Class <?php echo htmlspecialchars($st['class_name'] . ' - ' . $st['section_name']); ?></td>
                                    <td class="text-muted small"><?php echo formatDate($st['admission_date']); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>admin/student_view.php?id=<?php echo $st['id']; ?>" class="btn btn-sm btn-outline-secondary py-0.5 px-2">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No student admissions recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- School Circulars -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Active circulars</span>
                <a href="<?php echo BASE_URL; ?>admin/announcements.php" class="small text-decoration-none text-muted">Manage &rarr;</a>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($recentAnnouncements)): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($recentAnnouncements as $ann): ?>
                            <div class="pb-2.5 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="fw-medium text-dark small"><?php echo htmlspecialchars($ann['title']); ?></span>
                                    <span class="badge bg-secondary-subtle" style="font-size: 0.65rem;"><?php echo htmlspecialchars($ann['target_role']); ?></span>
                                </div>
                                <p class="text-muted small mb-1" style="font-size: 0.8rem; line-height: 1.4;">
                                    <?php echo htmlspecialchars(substr($ann['content'], 0, 95)) . (strlen($ann['content']) > 95 ? '...' : ''); ?>
                                </p>
                                <span class="text-muted" style="font-size: 0.72rem;">
                                    Published: <?php echo formatDate($ann['start_date']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        No active circulars at this moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Setup: Crisp Oxford Navy Accent, No Rainbow Clutter -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Fee Bar Chart
    const ctxFee = document.getElementById('feeCollectionChart');
    if (ctxFee) {
        new Chart(ctxFee, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(!empty($feeChartLabels) ? $feeChartLabels : ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov']); ?>,
                datasets: [{
                    data: <?php echo json_encode(!empty($feeChartData) ? $feeChartData : [0, 0, 0, 0, 0, 0]); ?>,
                    backgroundColor: '#1e3a8a',
                    borderRadius: 4,
                    barThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: value => 'PKR ' + value.toLocaleString(),
                            font: { size: 11 }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }

    // 2. Attendance Doughnut Chart: Meaning-only desaturated colors
    const ctxAtt = document.getElementById('attendanceDoughnutChart');
    if (ctxAtt) {
        new Chart(ctxAtt, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Leave'],
                datasets: [{
                    data: [
                        <?php echo $attendanceChartData['Present']; ?>,
                        <?php echo $attendanceChartData['Absent']; ?>,
                        <?php echo $attendanceChartData['Late']; ?>,
                        <?php echo $attendanceChartData['Leave']; ?>
                    ],
                    backgroundColor: ['#15803d', '#b91c1c', '#b45309', '#0369a1'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 11 } }
                    }
                },
                cutout: '72%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
