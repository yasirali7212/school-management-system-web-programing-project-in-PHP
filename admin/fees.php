<?php
/**
 * School Fee Management & Invoicing
 * Roots School System
 */

$pageTitle = "Fee Management";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. ADD / UPDATE CLASS FEE STRUCTURE
    if ($action === 'save_structure') {
        $classId  = (int)($_POST['class_id'] ?? 0);
        $feeTitle = trim($_POST['fee_title'] ?? 'Monthly Tuition Fee');
        $amount   = (float)($_POST['amount'] ?? 0);

        if ($classId > 0 && $amount > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO fee_structures (class_id, fee_title, amount)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$classId, $feeTitle, $amount]);
                setFlash('success', "Fee structure added successfully.");
                redirect(BASE_URL . 'admin/fees.php?tab=structures');
            } catch (Exception $e) {
                setFlash('danger', "Error saving structure: " . $e->getMessage());
            }
        }
    }

    // 2. GENERATE MONTHLY VOUCHERS IN BULK
    if ($action === 'generate_vouchers') {
        $genClassId = (int)($_POST['class_id'] ?? 0); // 0 = all classes
        $month      = trim($_POST['month'] ?? date('F'));
        $year       = (int)($_POST['year'] ?? date('Y'));
        $dueDate    = trim($_POST['due_date'] ?? date('Y-m-10', strtotime('+1 month')));

        try {
            // Find target students
            $sql = "
                SELECT s.id as student_id, s.class_id, fs.id as fee_structure_id, fs.fee_title, fs.amount
                FROM students s
                JOIN fee_structures fs ON s.class_id = fs.class_id
                WHERE s.status = 'Active'
            ";
            $params = [];
            if ($genClassId > 0) {
                $sql .= " AND s.class_id = ?";
                $params[] = $genClassId;
            }

            $stStmt = $pdo->prepare($sql);
            $stStmt->execute($params);
            $targets = $stStmt->fetchAll();

            $insertedCount = 0;
            $chkStmt = $pdo->prepare("SELECT id FROM fees WHERE student_id = ? AND month = ? AND year = ?");
            $insStmt = $pdo->prepare("
                INSERT INTO fees (student_id, fee_structure_id, fee_title, month, year, amount, discount, fine, paid_amount, status, due_date)
                VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00, 0.00, 'Unpaid', ?)
            ");

            foreach ($targets as $tgt) {
                // Check if already generated
                $chkStmt->execute([$tgt['student_id'], $month, $year]);
                if (!$chkStmt->fetch()) {
                    $feeTitle = $tgt['fee_title'] . " ({$month} {$year})";
                    $insStmt->execute([$tgt['student_id'], $tgt['fee_structure_id'], $feeTitle, $month, $year, $tgt['amount'], $dueDate]);
                    $insertedCount++;
                }
            }

            setFlash('success', "Generated {$insertedCount} fee voucher(s) for {$month} {$year}.");
            redirect(BASE_URL . 'admin/fees.php');
        } catch (Exception $e) {
            setFlash('danger', "Error generating fee vouchers: " . $e->getMessage());
        }
    }

    // 3. RECORD FEE PAYMENT
    if ($action === 'record_payment') {
        $feeId         = (int)($_POST['fee_id'] ?? 0);
        $amountPaid    = (float)($_POST['amount_paid'] ?? 0);
        $paymentDate   = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
        $transactionId = trim($_POST['transaction_id'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');

        if ($feeId > 0 && $amountPaid > 0) {
            try {
                $pdo->beginTransaction();

                // Fetch fee invoice
                $fStmt = $pdo->prepare("SELECT * FROM fees WHERE id = ? FOR UPDATE");
                $fStmt->execute([$feeId]);
                $feeRec = $fStmt->fetch();

                if ($feeRec) {
                    $newPaidAmount = (float)$feeRec['paid_amount'] + $amountPaid;
                    $totalPayable  = (float)$feeRec['amount'] + (float)$feeRec['fine'] - (float)$feeRec['discount'];

                    $newStatus = 'Unpaid';
                    if ($newPaidAmount >= $totalPayable) {
                        $newStatus = 'Paid';
                    } elseif ($newPaidAmount > 0) {
                        $newStatus = 'Partial';
                    }

                    // Update fees table
                    $updStmt = $pdo->prepare("UPDATE fees SET paid_amount = ?, status = ? WHERE id = ?");
                    $updStmt->execute([$newPaidAmount, $newStatus, $feeId]);

                    // Generate unique payment number
                    $paymentNo = 'PAY-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));

                    // Insert payment
                    $payStmt = $pdo->prepare("
                        INSERT INTO payments (fee_id, payment_no, amount_paid, payment_date, payment_method, transaction_id, notes, received_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $payStmt->execute([$feeId, $paymentNo, $amountPaid, $paymentDate, $paymentMethod, $transactionId, $notes, $currentUser['id']]);

                    $paymentId = $pdo->lastInsertId();
                    $pdo->commit();

                    setFlash('success', "Payment of PKR " . number_format($amountPaid) . " recorded. Receipt No: {$paymentNo}");
                    redirect(BASE_URL . 'admin/fee_receipt.php?payment_id=' . $paymentId);
                } else {
                    $pdo->rollBack();
                    setFlash('danger', "Invoice record not found.");
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', "Payment failed: " . $e->getMessage());
            }
        }
    }
}

// Active Tab
$tab = $_GET['tab'] ?? 'invoices';

// Filters for Invoices
$filterStatus = $_GET['status'] ?? '';
$filterClass  = (int)($_GET['class_id'] ?? 0);
$filterMonth  = $_GET['month'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');

// Invoices Query
$whereClause = ["1=1"];
$params = [];

if (!empty($filterStatus)) {
    $whereClause[] = "f.status = ?";
    $params[] = $filterStatus;
}
if ($filterClass > 0) {
    $whereClause[] = "s.class_id = ?";
    $params[] = $filterClass;
}
if (!empty($filterMonth)) {
    $whereClause[] = "f.month = ?";
    $params[] = $filterMonth;
}
if (!empty($filterSearch)) {
    $whereClause[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
    $like = "%{$filterSearch}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $whereClause);

$invoicesStmt = $pdo->prepare("
    SELECT f.*, s.first_name, s.last_name, s.admission_no, s.roll_no, c.class_name, sec.section_name
    FROM fees f
    JOIN students s ON f.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE {$whereSql}
    ORDER BY f.id DESC
    LIMIT 100
");
$invoicesStmt->execute($params);
$invoices = $invoicesStmt->fetchAll();

// KPI Stats
$statsTotalBilled = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fees")->fetchColumn();
$statsTotalPaid   = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM fees")->fetchColumn();
$statsTotalDue    = max(0, $statsTotalBilled - $statsTotalPaid);

// Load all classes for selectors
$allClasses = $pdo->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();

// Load Fee Structures
$structures = $pdo->query("
    SELECT fs.*, c.class_name 
    FROM fee_structures fs
    JOIN classes c ON fs.class_id = c.id
    ORDER BY c.id ASC
")->fetchAll();
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Fee Management &amp; Invoices</h2>
        <p class="text-muted small mb-0">Manage tuition fee structures, generate monthly vouchers, and collect payments.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-outline-primary btn-sm px-3 me-2" data-bs-toggle="modal" data-bs-target="#generateVouchersModal">
            <i class="bi bi-magic me-1"></i> Generate Monthly Vouchers
        </button>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStructureModal">
            <i class="bi bi-plus-circle me-1"></i> Add Fee Structure
        </button>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="stat-label">Total Invoiced</span>
                        <h3 class="stat-value text-primary mt-1">PKR <?php echo number_format($statsTotalBilled); ?></h3>
                        <small class="text-muted">Total student dues billed</small>
                    </div>
                    <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                        <i class="bi bi-receipt-cutoff"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="stat-label">Total Collected</span>
                        <h3 class="stat-value text-success mt-1">PKR <?php echo number_format($statsTotalPaid); ?></h3>
                        <small class="text-muted">Net payments received</small>
                    </div>
                    <div class="stat-icon-wrapper bg-success-subtle text-success">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="stat-label">Outstanding Balance</span>
                        <h3 class="stat-value text-danger mt-1">PKR <?php echo number_format($statsTotalDue); ?></h3>
                        <small class="text-muted">Pending unpaid invoices</small>
                    </div>
                    <div class="stat-icon-wrapper bg-danger-subtle text-danger">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-pills mb-3 border-bottom pb-2">
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-semibold <?php echo ($tab === 'invoices') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/fees.php?tab=invoices">
            <i class="bi bi-receipt me-1.5"></i> Fee Invoices (<?php echo count($invoices); ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-2 px-3 fw-semibold <?php echo ($tab === 'structures') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/fees.php?tab=structures">
            <i class="bi bi-sliders me-1.5"></i> Class Fee Structures (<?php echo count($structures); ?>)
        </a>
    </li>
</ul>

<?php if ($tab === 'invoices'): ?>
    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="invoices">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Search Student</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or Adm #" value="<?php echo htmlspecialchars($filterSearch); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Class</label>
                    <select name="class_id" class="form-select form-select-sm">
                        <option value="">All Classes</option>
                        <?php foreach ($allClasses as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($filterClass === (int)$c['id']) ? 'selected' : ''; ?>>
                                Class <?php echo htmlspecialchars($c['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="Paid" <?php echo ($filterStatus === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="Partial" <?php echo ($filterStatus === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                        <option value="Unpaid" <?php echo ($filterStatus === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Month</label>
                    <select name="month" class="form-select form-select-sm">
                        <option value="">All Months</option>
                        <?php 
                        $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        foreach ($months as $m): 
                        ?>
                            <option value="<?php echo $m; ?>" <?php echo ($filterMonth === $m) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="<?php echo BASE_URL; ?>admin/fees.php" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                        <th>Student</th>
                        <th>Class</th>
                        <th>Fee Title</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
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
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></div>
                                    <small class="text-muted">Adm #: <?php echo htmlspecialchars($inv['admission_no']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($inv['class_name'] . ' - ' . $inv['section_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($inv['fee_title']); ?></span>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($inv['month'] . ' ' . $inv['year']); ?></small>
                                </td>
                                <td class="fw-bold text-dark">PKR <?php echo number_format($inv['amount']); ?></td>
                                <td class="text-success fw-bold">PKR <?php echo number_format($inv['paid_amount']); ?></td>
                                <td>
                                    <small class="text-secondary"><?php echo date('d M Y', strtotime($inv['due_date'])); ?></small>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($inv['status']); ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($inv['status'] !== 'Paid'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success px-2.5 py-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#payModal" 
                                                    data-fee-id="<?php echo $inv['id']; ?>"
                                                    data-student="<?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?>"
                                                    data-title="<?php echo htmlspecialchars($inv['fee_title']); ?>"
                                                    data-balance="<?php echo max(0, $bal); ?>">
                                                <i class="bi bi-wallet2 me-1"></i> Pay
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?php echo BASE_URL; ?>admin/fee_receipt.php?fee_id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-secondary px-2.5 py-1" title="View / Print Receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No fee invoice records found. Click "Generate Monthly Vouchers" above to create them.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'structures'): ?>
    <!-- Fee Structures Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-layers me-2 text-primary"></i>Configured Class Fee Structures</h6>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addStructureModal">
                <i class="bi bi-plus-circle me-1"></i> Add Structure
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Target Class</th>
                        <th>Fee Head / Description</th>
                        <th>Monthly Amount (PKR)</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($structures)): ?>
                        <?php $sno = 1; foreach ($structures as $st): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo $sno++; ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 fw-semibold">
                                        Class <?php echo htmlspecialchars($st['class_name']); ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($st['fee_title']); ?></td>
                                <td class="fw-bold text-success fs-6">PKR <?php echo number_format($st['amount'], 2); ?></td>
                                <td class="text-muted small"><?php echo date('d M Y', strtotime($st['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No fee structures configured yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ================= MODAL: ADD FEE STRUCTURE ================= -->
<div class="modal fade" id="addStructureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add Class Fee Structure</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_structure">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($allClasses as $c): ?>
                                <option value="<?php echo $c['id']; ?>">Class <?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fee Title <span class="text-danger">*</span></label>
                        <input type="text" name="fee_title" class="form-control form-control-sm" required value="Monthly Tuition Fee" placeholder="e.g. Monthly Tuition Fee">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Amount (PKR) <span class="text-danger">*</span></label>
                        <input type="number" step="100" min="0" name="amount" class="form-control form-control-sm" required placeholder="e.g. 4500">
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Save Structure</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL: GENERATE MONTHLY VOUCHERS ================= -->
<div class="modal fade" id="generateVouchersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-magic me-2"></i>Generate Monthly Fee Vouchers</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="generate_vouchers">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 small mb-3">
                        <i class="bi bi-info-circle me-1"></i> Automatically generates fee vouchers for all active students based on their class fee structure. Existing vouchers for this month are preserved.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Class</label>
                        <select name="class_id" class="form-select form-select-sm">
                            <option value="0">All Classes (School-Wide)</option>
                            <?php foreach ($allClasses as $c): ?>
                                <option value="<?php echo $c['id']; ?>">Class <?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fee Month <span class="text-danger">*</span></label>
                            <select name="month" class="form-select form-select-sm" required>
                                <?php foreach ($months as $m): ?>
                                    <option value="<?php echo $m; ?>" <?php echo (date('F') === $m) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Year <span class="text-danger">*</span></label>
                            <input type="number" name="year" class="form-control form-control-sm" value="<?php echo date('Y'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Payment Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" class="form-control form-control-sm" value="<?php echo date('Y-m-10', strtotime('+10 days')); ?>" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Generate Vouchers</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL: RECORD PAYMENT ================= -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Record Fee Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="fee_id" id="payFeeId" value="0">
                <div class="modal-body p-4">
                    <div class="p-3 mb-3 bg-light rounded border">
                        <div class="small text-muted">Student:</div>
                        <div class="fw-bold text-dark fs-6" id="payStudentName">--</div>
                        <div class="small text-secondary mt-1" id="payFeeTitle">--</div>
                        <div class="small fw-semibold text-danger mt-1">Outstanding Due: PKR <span id="payBalance">0</span></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount Paid (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="50" min="1" name="amount_paid" id="payAmountInput" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select form-select-sm">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Online">Online</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Transaction / Ref #</label>
                            <input type="text" name="transaction_id" class="form-control form-control-sm" placeholder="Optional reference">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Payment Notes / Remarks</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4">Confirm &amp; Print Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Pass invoice data to payment modal
var payModal = document.getElementById('payModal');
if (payModal) {
    payModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var feeId = button.getAttribute('data-fee-id');
        var studentName = button.getAttribute('data-student');
        var feeTitle = button.getAttribute('data-title');
        var balance = button.getAttribute('data-balance');

        document.getElementById('payFeeId').value = feeId;
        document.getElementById('payStudentName').textContent = studentName;
        document.getElementById('payFeeTitle').textContent = feeTitle;
        document.getElementById('payBalance').textContent = parseFloat(balance).toLocaleString();
        document.getElementById('payAmountInput').value = balance;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

