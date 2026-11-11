<?php
/**
 * Consolidated School Analytics & Reports
 * Roots School System
 */

$pageTitle = "Reports & Analytics";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

$reportType = $_GET['type'] ?? 'enrollment';
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterMonth = $_GET['month'] ?? date('F');
$filterYear  = (int)($_GET['year'] ?? date('Y'));

// 1. Enrollment Summary
$enrollmentStats = $pdo->query("
    SELECT c.id as class_id, c.class_name,
           COUNT(s.id) as total_students,
           SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
           SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as female_count,
           SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    GROUP BY c.id, c.class_name
    ORDER BY c.id ASC
")->fetchAll();

// 2. Attendance Summary for Date
$attStmt = $pdo->prepare("
    SELECT c.id as class_id, c.class_name,
           COUNT(a.id) as marked_count,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
           SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count,
           SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_count
    FROM classes c
    LEFT JOIN attendance a ON (a.class_id = c.id AND a.date = ?)
    GROUP BY c.id, c.class_name
    ORDER BY c.id ASC
");
$attStmt->execute([$filterDate]);
$attendanceStats = $attStmt->fetchAll();

// 3. Fee Collection Summary for Month
$feeStmt = $pdo->prepare("
    SELECT c.id as class_id, c.class_name,
           COUNT(f.id) as total_vouchers,
           COALESCE(SUM(f.amount), 0) as total_invoiced,
           COALESCE(SUM(f.paid_amount), 0) as total_paid,
           SUM(CASE WHEN f.status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
           SUM(CASE WHEN f.status = 'Unpaid' THEN 1 ELSE 0 END) as unpaid_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    LEFT JOIN fees f ON (f.student_id = s.id AND f.month = ? AND f.year = ?)
    GROUP BY c.id, c.class_name
    ORDER BY c.id ASC
");
$feeStmt->execute([$filterMonth, $filterYear]);
$feeStats = $feeStmt->fetchAll();
?>

<div class="row align-items-center mb-4 g-3 d-print-none">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
        <p class="text-muted small mb-0">Institutional summaries for student admissions, daily attendance, and revenue collections.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-outline-secondary btn-sm px-3 shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Report Navigation Tabs -->
<ul class="nav nav-pills mb-4 border-bottom pb-2 d-print-none">
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-semibold <?php echo ($reportType === 'enrollment') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/reports.php?type=enrollment">
            <i class="bi bi-people me-1.5"></i> Student Enrollment
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-semibold <?php echo ($reportType === 'attendance') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/reports.php?type=attendance">
            <i class="bi bi-calendar-check me-1.5"></i> Daily Attendance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-semibold <?php echo ($reportType === 'fees') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/reports.php?type=fees">
            <i class="bi bi-cash-stack me-1.5"></i> Fee Collections
        </a>
    </li>
</ul>

<?php if ($reportType === 'enrollment'): ?>
    <!-- 1. ENROLLMENT REPORT -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-mortarboard me-2 text-primary"></i>Class-Wise Student Enrollment Summary</h5>
            <small class="text-muted">Total active school admissions by class &amp; gender</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Class Name</th>
                        <th class="text-center">Boys</th>
                        <th class="text-center">Girls</th>
                        <th class="text-center">Active Enrolled</th>
                        <th class="text-center">Total Students</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotal = 0; $grandBoys = 0; $grandGirls = 0; $grandActive = 0;
                    $idx = 1;
                    foreach ($enrollmentStats as $es): 
                        $grandTotal += (int)$es['total_students'];
                        $grandBoys += (int)$es['male_count'];
                        $grandGirls += (int)$es['female_count'];
                        $grandActive += (int)$es['active_count'];
                    ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo $idx++; ?></td>
                            <td class="fw-bold text-dark">Class <?php echo htmlspecialchars($es['class_name']); ?></td>
                            <td class="text-center"><?php echo (int)$es['male_count']; ?></td>
                            <td class="text-center"><?php echo (int)$es['female_count']; ?></td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success px-2 py-1">
                                    <?php echo (int)$es['active_count']; ?> Active
                                </span>
                            </td>
                            <td class="text-center fw-bold fs-6 text-primary"><?php echo (int)$es['total_students']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="2" class="text-end text-uppercase">Grand Total:</td>
                        <td class="text-center"><?php echo $grandBoys; ?></td>
                        <td class="text-center"><?php echo $grandGirls; ?></td>
                        <td class="text-center text-success"><?php echo $grandActive; ?> Active</td>
                        <td class="text-center text-primary fs-5"><?php echo $grandTotal; ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php elseif ($reportType === 'attendance'): ?>
    <!-- 2. ATTENDANCE REPORT -->
    <div class="card border-0 shadow-sm mb-4 d-print-none">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <input type="hidden" name="type" value="attendance">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-secondary mb-1">Select Attendance Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDate); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar-check me-2 text-primary"></i>Daily Attendance Report - <?php echo date('d F Y', strtotime($filterDate)); ?></h5>
            <small class="text-muted">Breakdown of students present, absent, late, or on approved leave</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Class</th>
                        <th class="text-center">Total Marked</th>
                        <th class="text-center text-success">Present</th>
                        <th class="text-center text-danger">Absent</th>
                        <th class="text-center text-warning">Late</th>
                        <th class="text-center text-info">Leave</th>
                        <th class="text-center">Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $tMarked = 0; $tPresent = 0; $tAbsent = 0; $tLate = 0; $tLeave = 0;
                    $idx = 1;
                    foreach ($attendanceStats as $as): 
                        $marked = (int)$as['marked_count'];
                        $present = (int)$as['present_count'];
                        $absent = (int)$as['absent_count'];
                        $late = (int)$as['late_count'];
                        $leave = (int)$as['leave_count'];
                        $ratio = ($marked > 0) ? round(($present / $marked) * 100, 1) : 0;

                        $tMarked += $marked; $tPresent += $present; $tAbsent += $absent; $tLate += $late; $tLeave += $leave;
                    ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo $idx++; ?></td>
                            <td class="fw-bold text-dark">Class <?php echo htmlspecialchars($as['class_name']); ?></td>
                            <td class="text-center fw-semibold"><?php echo $marked; ?></td>
                            <td class="text-center text-success fw-bold"><?php echo $present; ?></td>
                            <td class="text-center text-danger fw-bold"><?php echo $absent; ?></td>
                            <td class="text-center text-warning fw-bold"><?php echo $late; ?></td>
                            <td class="text-center text-info fw-bold"><?php echo $leave; ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo ($ratio >= 75) ? 'bg-success' : 'bg-warning text-dark'; ?> px-2 py-1">
                                    <?php echo $ratio; ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="2" class="text-end text-uppercase">Summary:</td>
                        <td class="text-center"><?php echo $tMarked; ?></td>
                        <td class="text-center text-success"><?php echo $tPresent; ?></td>
                        <td class="text-center text-danger"><?php echo $tAbsent; ?></td>
                        <td class="text-center text-warning"><?php echo $tLate; ?></td>
                        <td class="text-center text-info"><?php echo $tLeave; ?></td>
                        <td class="text-center text-primary fs-6">
                            <?php echo ($tMarked > 0) ? round(($tPresent / $tMarked) * 100, 1) : 0; ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php elseif ($reportType === 'fees'): ?>
    <!-- 3. FEE COLLECTIONS REPORT -->
    <div class="card border-0 shadow-sm mb-4 d-print-none">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <input type="hidden" name="type" value="fees">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Month</label>
                    <select name="month" class="form-select form-select-sm">
                        <?php 
                        $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        foreach ($months as $m): 
                        ?>
                            <option value="<?php echo $m; ?>" <?php echo ($filterMonth === $m) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Year</label>
                    <input type="number" name="year" class="form-control form-control-sm" value="<?php echo $filterYear; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-cash-stack me-2 text-primary"></i>Fee Collections Report - <?php echo htmlspecialchars($filterMonth . ' ' . $filterYear); ?></h5>
            <small class="text-muted">Invoiced amounts, collected receipts, and recovery percentages</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Class</th>
                        <th class="text-center">Vouchers</th>
                        <th>Total Invoiced</th>
                        <th class="text-success">Total Collected</th>
                        <th class="text-danger">Unpaid Balance</th>
                        <th class="text-center">Recovery %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $gtInvoiced = 0; $gtPaid = 0; $gtVouchers = 0;
                    $idx = 1;
                    foreach ($feeStats as $fs): 
                        $invoiced = (float)$fs['total_invoiced'];
                        $paid = (float)$fs['total_paid'];
                        $bal = max(0, $invoiced - $paid);
                        $recovery = ($invoiced > 0) ? round(($paid / $invoiced) * 100, 1) : 0;

                        $gtInvoiced += $invoiced; $gtPaid += $paid; $gtVouchers += (int)$fs['total_vouchers'];
                    ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo $idx++; ?></td>
                            <td class="fw-bold text-dark">Class <?php echo htmlspecialchars($fs['class_name']); ?></td>
                            <td class="text-center"><?php echo (int)$fs['total_vouchers']; ?></td>
                            <td class="fw-semibold">PKR <?php echo number_format($invoiced); ?></td>
                            <td class="fw-bold text-success">PKR <?php echo number_format($paid); ?></td>
                            <td class="fw-bold text-danger">PKR <?php echo number_format($bal); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo ($recovery >= 80) ? 'bg-success' : 'bg-warning text-dark'; ?> px-2 py-1">
                                    <?php echo $recovery; ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="2" class="text-end text-uppercase">Grand Total:</td>
                        <td class="text-center"><?php echo $gtVouchers; ?></td>
                        <td>PKR <?php echo number_format($gtInvoiced); ?></td>
                        <td class="text-success fs-6">PKR <?php echo number_format($gtPaid); ?></td>
                        <td class="text-danger fs-6">PKR <?php echo number_format($gtInvoiced - $gtPaid); ?></td>
                        <td class="text-center text-primary fs-6">
                            <?php echo ($gtInvoiced > 0) ? round(($gtPaid / $gtInvoiced) * 100, 1) : 0; ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    body { background: white !important; font-size: 11pt; }
    .app-sidebar, .app-topbar, .app-footer, .sidebar-backdrop, .d-print-none { display: none !important; }
    .app-main, .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

