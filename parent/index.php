<?php
/**
 * Parent / Guardian Overview
 * Roots School System
 * Unslop: Refined institutional craft, quiet chrome, deliberate typography & numbers
 */

$pageTitle = "Guardian Overview";
require_once __DIR__ . '/../includes/header.php';
requireRole('parent');

$pdo = getDB();

// Get Parent Record
$pStmt = $pdo->prepare("SELECT * FROM parents WHERE user_id = ?");
$pStmt->execute([$currentUser['id']]);
$parent = $pStmt->fetch();
$parentId = $parent ? (int)$parent['id'] : 0;

// Fetch Children enrolled under this parent
$cStmt = $pdo->prepare("
    SELECT s.*, c.class_name, sec.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.parent_id = ? AND s.status = 'Active'
    ORDER BY s.id ASC
");
$cStmt->execute([$parentId]);
$children = $cStmt->fetchAll();

$childIds = array_column($children, 'id');

// Total Outstanding Fees across all children
$totalDue = 0;
if (!empty($childIds)) {
    $in = implode(',', array_map('intval', $childIds));
    $feeSum = $pdo->query("
        SELECT COALESCE(SUM(amount + fine - discount - paid_amount), 0)
        FROM fees
        WHERE student_id IN ({$in}) AND status != 'Paid'
    ")->fetchColumn();
    $totalDue = max(0, (float)$feeSum);
}

// Announcements for Parents
$notices = $pdo->query("
    SELECT * FROM announcements 
    WHERE target_role IN ('all', 'parent') AND expiry_date >= CURDATE()
    ORDER BY created_at DESC 
    LIMIT 3
")->fetchAll();
?>

<!-- Section Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <div>
        <h2 class="page-title">Guardian Overview</h2>
        <p class="page-subtitle">
            Guardian: <strong><?php echo htmlspecialchars($parent['father_name'] ?: $currentUser['name']); ?></strong> &bull; 
            Enrolled children monitoring &bull; Session <?php echo APP_YEAR; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>parent/fees.php" class="btn btn-outline-secondary btn-sm px-3">
            Fee vouchers
        </a>
    </div>
</div>

<!-- Primary Metric Grid (Pass 7: Clean numbers first, no noisy pastel circles) -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <span class="stat-label">Enrolled children</span>
            <div class="stat-value"><?php echo count($children); ?></div>
            <div class="stat-sub text-muted">Pupils under your guardianship</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <span class="stat-label">Outstanding fee balance</span>
            <div class="stat-value <?php echo ($totalDue > 0) ? 'text-danger' : 'text-success'; ?>">
                PKR <?php echo number_format($totalDue); ?>
            </div>
            <div class="stat-sub text-muted"><?php echo ($totalDue > 0) ? 'Pending payment across vouchers' : 'All accounts settled'; ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <span class="stat-label">Academic session</span>
            <div class="stat-value"><?php echo APP_YEAR; ?></div>
            <div class="stat-sub text-muted">Roots School System</div>
        </div>
    </div>
</div>

<!-- Children Profiles Section -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="fw-semibold text-dark small text-uppercase tracking-wider">Enrolled children</span>
    <span class="stat-sub"><?php echo count($children); ?> active student(s)</span>
</div>

<div class="row g-3 mb-4">
    <?php if (!empty($children)): ?>
        <?php foreach ($children as $ch): 
            // Calculate child attendance
            $cAtt = $pdo->prepare("
                SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
                FROM attendance WHERE student_id = ?
            ");
            $cAtt->execute([$ch['id']]);
            $attRow = $cAtt->fetch();
            $tot = (int)($attRow['total'] ?? 0);
            $prs = (int)($attRow['present'] ?? 0);
            $attRatio = ($tot > 0) ? round(($prs / $tot) * 100, 1) : 100;

            // Calculate child fee balance
            $cFee = $pdo->prepare("
                SELECT COALESCE(SUM(amount + fine - discount - paid_amount), 0)
                FROM fees WHERE student_id = ? AND status != 'Paid'
            ");
            $cFee->execute([$ch['id']]);
            $chDue = max(0, (float)$cFee->fetchColumn());
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-3">
                    <div class="d-flex align-items-center gap-2.5 mb-3">
                        <div class="user-avatar" style="width: 36px; height: 36px; font-size: 0.95rem;">
                            <?php echo strtoupper(substr($ch['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="fw-semibold text-dark mb-0"><?php echo htmlspecialchars($ch['first_name'] . ' ' . $ch['last_name']); ?></h6>
                            <span class="text-muted small">
                                Class <?php echo htmlspecialchars($ch['class_name'] . ' - ' . $ch['section_name']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-2.5 rounded border bg-light small mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Admission #:</span>
                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($ch['admission_no']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Roll number:</span>
                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($ch['roll_no'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Attendance rate:</span>
                            <span class="badge <?php echo ($attRatio >= 75) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                                <?php echo $attRatio; ?>%
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Dues:</span>
                            <span class="fw-medium <?php echo ($chDue > 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo ($chDue > 0) ? 'PKR ' . number_format($chDue) : 'Cleared'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-auto">
                        <a href="<?php echo BASE_URL; ?>parent/attendance.php?child_id=<?php echo $ch['id']; ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                            Attendance
                        </a>
                        <a href="<?php echo BASE_URL; ?>parent/results.php?child_id=<?php echo $ch['id']; ?>" class="btn btn-primary btn-sm flex-fill">
                            Report card
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card text-center py-4 text-muted small">
                No student records currently linked to your guardian account. Please contact school administration.
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- School Notices -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>School circulars</span>
        <a href="<?php echo BASE_URL; ?>parent/announcements.php" class="small text-decoration-none text-muted">All notices &rarr;</a>
    </div>
    <div class="card-body p-3">
        <?php if (!empty($notices)): ?>
            <div class="row g-3">
                <?php foreach ($notices as $nt): ?>
                    <div class="col-md-4">
                        <div class="p-2.5 rounded border bg-light h-100">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge bg-secondary-subtle" style="font-size: 0.65rem;">Circular</span>
                                <small class="text-muted"><?php echo formatDate($nt['start_date']); ?></small>
                            </div>
                            <h6 class="fw-medium text-dark small mb-1"><?php echo htmlspecialchars($nt['title']); ?></h6>
                            <p class="text-muted small mb-0" style="font-size: 0.8rem; line-height: 1.4;">
                                <?php echo htmlspecialchars(substr($nt['content'], 0, 95)) . (strlen($nt['content']) > 95 ? '...' : ''); ?>
                            </p>
                        </div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
