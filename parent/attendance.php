<?php
/**
 * Parent View Children Attendance
 * Roots School System
 */

$pageTitle = "Children Attendance";
require_once __DIR__ . '/../includes/header.php';
requireRole('parent');

$pdo = getDB();

// Get Parent Record
$pStmt = $pdo->prepare("SELECT * FROM parents WHERE user_id = ?");
$pStmt->execute([$currentUser['id']]);
$parent = $pStmt->fetch();
$parentId = $parent ? (int)$parent['id'] : 0;

// Fetch Children
$cStmt = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.admission_no, c.class_name, sec.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.parent_id = ?
    ORDER BY s.id ASC
");
$cStmt->execute([$parentId]);
$children = $cStmt->fetchAll();

$selectedChildId = (int)($_GET['child_id'] ?? ($children[0]['id'] ?? 0));
$filterMonth = (int)($_GET['month'] ?? date('m'));
$filterYear  = (int)($_GET['year'] ?? date('Y'));

$records = [];
$selectedChild = null;

if ($selectedChildId > 0) {
    foreach ($children as $ch) {
        if ($ch['id'] == $selectedChildId) {
            $selectedChild = $ch;
            break;
        }
    }

    $attStmt = $pdo->prepare("
        SELECT * FROM attendance
        WHERE student_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
        ORDER BY date DESC
    ");
    $attStmt->execute([$selectedChildId, $filterMonth, $filterYear]);
    $records = $attStmt->fetchAll();
}

// KPI Stats
$totalMarked = count($records);
$presentCount = 0; $absentCount = 0; $lateCount = 0; $leaveCount = 0;
foreach ($records as $r) {
    if ($r['status'] === 'Present') $presentCount++;
    elseif ($r['status'] === 'Absent') $absentCount++;
    elseif ($r['status'] === 'Late') $lateCount++;
    elseif ($r['status'] === 'Leave') $leaveCount++;
}
$attPercentage = ($totalMarked > 0) ? round(($presentCount / $totalMarked) * 100, 1) : 100;
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Children Attendance</h2>
        <p class="text-muted small mb-0">Track daily class presence, punctuality, and leaves for your children.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="<?php echo BASE_URL; ?>parent/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Child & Month Switcher Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Select Child</label>
                <select name="child_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($children as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedChildId === (int)$c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?> (Class <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Month</label>
                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php 
                    for ($m = 1; $m <= 12; $m++): 
                        $mName = date('F', mktime(0, 0, 0, $m, 10));
                    ?>
                        <option value="<?php echo $m; ?>" <?php echo ($filterMonth === $m) ? 'selected' : ''; ?>><?php echo $mName; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">Year</label>
                <input type="number" name="year" class="form-control form-control-sm" value="<?php echo $filterYear; ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> View Log
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <span class="stat-label">Attendance Rate</span>
                <h3 class="stat-value text-primary mt-1"><?php echo $attPercentage; ?>%</h3>
                <small class="text-muted">Target minimum: 75%</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <span class="stat-label">Days Present</span>
                <h3 class="stat-value text-success mt-1"><?php echo $presentCount; ?></h3>
                <small class="text-muted">Out of <?php echo $totalMarked; ?> working days</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <span class="stat-label">Days Absent</span>
                <h3 class="stat-value text-danger mt-1"><?php echo $absentCount; ?></h3>
                <small class="text-muted">Unexcused missed days</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <span class="stat-label">Late / Leaves</span>
                <h3 class="stat-value text-warning mt-1"><?php echo $lateCount + $leaveCount; ?></h3>
                <small class="text-muted"><?php echo $lateCount; ?> late, <?php echo $leaveCount; ?> leave</small>
            </div>
        </div>
    </div>
</div>

<!-- Table Log -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-calendar-check me-2 text-primary"></i>Daily Attendance Sheet: <?php echo htmlspecialchars($selectedChild['first_name'] . ' ' . $selectedChild['last_name']); ?> (<?php echo date('F Y', mktime(0, 0, 0, $filterMonth, 10, $filterYear)); ?>)
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 140px;">Date</th>
                    <th style="width: 120px;">Day</th>
                    <th style="width: 140px;">Status</th>
                    <th>Teacher Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $r): 
                        $status = $r['status'];
                        $badgeClass = 'bg-secondary';
                        if ($status === 'Present') $badgeClass = 'bg-success';
                        elseif ($status === 'Absent') $badgeClass = 'bg-danger';
                        elseif ($status === 'Late') $badgeClass = 'bg-warning text-dark';
                        elseif ($status === 'Leave') $badgeClass = 'bg-info text-dark';
                    ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                            <td class="text-secondary"><?php echo date('l', strtotime($r['date'])); ?></td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?> px-2.5 py-1">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?php echo htmlspecialchars($r['remarks'] ?: '--'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">No attendance marked for this child in this month.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

