<?php
/**
 * Parent View Children Fee Vouchers & Receipts
 * Roots School System
 */

$pageTitle = "Fee Invoices & Receipts";
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
$childIds = array_column($children, 'id');

$filterChildId = (int)($_GET['child_id'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? '');

$invoices = [];
$statsTotalBilled = 0;
$statsTotalPaid   = 0;

if (!empty($childIds)) {
    $where = [];
    $params = [];

    if ($filterChildId > 0 && in_array($filterChildId, $childIds)) {
        $where[] = "f.student_id = ?";
        $params[] = $filterChildId;
    } else {
        $in = implode(',', array_map('intval', $childIds));
        $where[] = "f.student_id IN ({$in})";
    }

    if (!empty($filterStatus)) {
        $where[] = "f.status = ?";
        $params[] = $filterStatus;
    }

    $whereSql = implode(' AND ', $where);

    $fStmt = $pdo->prepare("
        SELECT f.*, s.first_name, s.last_name, s.admission_no, c.class_name, sec.section_name
        FROM fees f
        JOIN students s ON f.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE {$whereSql}
        ORDER BY f.id DESC
    ");
    $fStmt->execute($params);
    $invoices = $fStmt->fetchAll();

    // Summary calculation
    $inAll = implode(',', array_map('intval', $childIds));
    $sumStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(amount), 0) as total_billed,
            COALESCE(SUM(paid_amount), 0) as total_paid
        FROM fees 
        WHERE student_id IN ({$inAll})
    ")->fetch();

    $statsTotalBilled = (float)($sumStmt['total_billed'] ?? 0);
    $statsTotalPaid   = (float)($sumStmt['total_paid'] ?? 0);
}

$statsBalance = max(0, $statsTotalBilled - $statsTotalPaid);
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">Fee Invoices &amp; Receipts</h2>
        <p class="text-muted small mb-0">Track monthly tuition fee vouchers, payment receipts, and balance dues.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>parent/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <span class="stat-label">Total Invoiced</span>
                <h3 class="stat-value text-primary mt-1">PKR <?php echo number_format($statsTotalBilled); ?></h3>
                <small class="text-muted">Total fees billed for all children</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <span class="stat-label">Total Paid</span>
                <h3 class="stat-value text-success mt-1">PKR <?php echo number_format($statsTotalPaid); ?></h3>
                <small class="text-muted">Cleared school payments</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <span class="stat-label">Outstanding Balance</span>
                <h3 class="stat-value <?php echo ($statsBalance > 0) ? 'text-danger' : 'text-success'; ?> mt-1">
                    PKR <?php echo number_format($statsBalance); ?>
                </h3>
                <small class="text-muted"><?php echo ($statsBalance > 0) ? 'Pending payment' : 'All clear'; ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Child</label>
                <select name="child_id" class="form-select form-select-sm">
                    <option value="0">All Children</option>
                    <?php foreach ($children as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($filterChildId === (int)$c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?> (Class <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="Paid" <?php echo ($filterStatus === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Unpaid" <?php echo ($filterStatus === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="Partial" <?php echo ($filterStatus === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Invoices Table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 80px;">Inv #</th>
                    <th>Student Name</th>
                    <th>Fee Title</th>
                    <th>Month / Year</th>
                    <th>Amount</th>
                    <th>Paid Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-end">Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoices)): ?>
                    <?php foreach ($invoices as $inv): 
                        $bal = (float)$inv['amount'] + (float)$inv['fine'] - (float)$inv['discount'] - (float)$inv['paid_amount'];
                    ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?php echo $inv['id']; ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></div>
                                <small class="text-muted">Class <?php echo htmlspecialchars($inv['class_name'] . ' - ' . $inv['section_name']); ?></small>
                            </td>
                            <td>
                                <span class="fw-medium text-dark"><?php echo htmlspecialchars($inv['fee_title']); ?></span>
                            </td>
                            <td>
                                <small class="text-secondary"><?php echo htmlspecialchars($inv['month'] . ' ' . $inv['year']); ?></small>
                            </td>
                            <td class="fw-bold text-dark">PKR <?php echo number_format($inv['amount']); ?></td>
                            <td class="fw-bold text-success">PKR <?php echo number_format($inv['paid_amount']); ?></td>
                            <td>
                                <small class="text-secondary"><?php echo date('d M Y', strtotime($inv['due_date'])); ?></small>
                            </td>
                            <td>
                                <?php echo getStatusBadge($inv['status']); ?>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo BASE_URL; ?>admin/fee_receipt.php?fee_id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary px-2.5 py-1" title="Print Fee Voucher Receipt">
                                    <i class="bi bi-printer me-1"></i> Receipt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No fee voucher records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

