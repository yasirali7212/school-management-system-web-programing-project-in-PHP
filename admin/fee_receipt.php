<?php
/**
 * Official Fee Payment Receipt Voucher
 * Roots School System
 */

$pageTitle = "Fee Payment Receipt";
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'parent', 'student']);

$pdo = getDB();

$paymentId = (int)($_GET['payment_id'] ?? 0);
$feeId     = (int)($_GET['fee_id'] ?? 0);

$payment = null;
$fee = null;

if ($paymentId > 0) {
    $pStmt = $pdo->prepare("
        SELECT p.*, f.student_id, f.fee_title, f.month, f.year, f.amount as invoice_amount, 
               f.discount, f.fine, f.paid_amount as total_paid, f.status as fee_status, f.due_date,
               s.admission_no, s.roll_no, s.first_name, s.last_name, c.class_name, sec.section_name,
               par.father_name, par.phone as parent_phone, u.username as cashier_name
        FROM payments p
        JOIN fees f ON p.fee_id = f.id
        JOIN students s ON f.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents par ON s.parent_id = par.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.id = ?
    ");
    $pStmt->execute([$paymentId]);
    $payment = $pStmt->fetch();
} elseif ($feeId > 0) {
    // If looking up directly by fee ID, get invoice and latest payment
    $fStmt = $pdo->prepare("
        SELECT f.*, f.amount as invoice_amount, f.paid_amount as total_paid, f.status as fee_status,
               s.admission_no, s.roll_no, s.first_name, s.last_name, c.class_name, sec.section_name,
               par.father_name, par.phone as parent_phone
        FROM fees f
        JOIN students s ON f.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents par ON s.parent_id = par.id
        WHERE f.id = ?
    ");
    $fStmt->execute([$feeId]);
    $fee = $fStmt->fetch();

    if ($fee) {
        // Fetch latest payment if exists
        $lpStmt = $pdo->prepare("
            SELECT p.*, u.username as cashier_name
            FROM payments p
            LEFT JOIN users u ON p.received_by = u.id
            WHERE p.fee_id = ?
            ORDER BY p.id DESC
            LIMIT 1
        ");
        $lpStmt->execute([$feeId]);
        $latestPay = $lpStmt->fetch();

        // Merge info
        $payment = array_merge($fee, $latestPay ?: [
            'payment_no'     => 'INV-' . $fee['id'],
            'amount_paid'    => $fee['paid_amount'],
            'payment_date'   => $fee['created_at'],
            'payment_method' => 'N/A',
            'transaction_id' => '',
            'cashier_name'   => 'Accounts Dept'
        ]);
    }
}
?>

<div class="row align-items-center mb-4 g-3 d-print-none">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Fee Payment Receipt</h2>
        <p class="text-muted small mb-0">Official student fee payment voucher and transaction proof.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if ($currentUser['role'] === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>admin/fees.php" class="btn btn-outline-secondary btn-sm px-3 me-2">
                <i class="bi bi-arrow-left me-1"></i> Back to Fees
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Receipt
        </button>
    </div>
</div>

<?php if ($payment): 
    $netPayable = (float)$payment['invoice_amount'] + (float)$payment['fine'] - (float)$payment['discount'];
    $currentPaid = (float)$payment['amount_paid'];
    $balance = max(0, $netPayable - (float)$payment['total_paid']);
?>
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white receipt-sheet" style="max-width: 800px; margin: 0 auto; border: 2px dashed #0f172a !important; border-radius: 8px;">
        <!-- Header -->
        <div class="row align-items-center pb-3 mb-3 border-bottom border-2 border-dark">
            <div class="col-2 text-center">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.svg" alt="Roots Crest" style="width: 75px; height: 75px;">
            </div>
            <div class="col-7 text-center">
                <h3 class="fw-bolder text-uppercase mb-0 text-primary" style="letter-spacing: 1px;"><?php echo APP_NAME; ?></h3>
                <small class="text-secondary fw-semibold">Main Campus &bull; Administration &amp; Accounts Division</small>
                <div class="fw-bold text-dark mt-1 text-uppercase" style="font-size: 0.95rem;">Fee Payment Receipt Voucher</div>
            </div>
            <div class="col-3 text-end">
                <span class="badge bg-light text-dark border px-2 py-1 mb-1 d-inline-block small">Student Copy</span>
                <div class="small fw-bold text-danger"><?php echo htmlspecialchars($payment['payment_no']); ?></div>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="row g-2 mb-3 p-3 bg-light rounded small border">
            <div class="col-6 col-sm-3">
                <span class="text-muted d-block">Receipt No:</span>
                <strong class="text-dark"><?php echo htmlspecialchars($payment['payment_no']); ?></strong>
            </div>
            <div class="col-6 col-sm-3">
                <span class="text-muted d-block">Payment Date:</span>
                <strong class="text-dark"><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></strong>
            </div>
            <div class="col-6 col-sm-3">
                <span class="text-muted d-block">Payment Method:</span>
                <strong class="text-dark"><?php echo htmlspecialchars($payment['payment_method']); ?></strong>
            </div>
            <div class="col-6 col-sm-3">
                <span class="text-muted d-block">Ref / Trans #:</span>
                <strong class="text-dark"><?php echo htmlspecialchars($payment['transaction_id'] ?: 'Cash Desk'); ?></strong>
            </div>
        </div>

        <!-- Student Information -->
        <div class="row g-2 mb-4 small">
            <div class="col-6">
                <div><strong>Student Name:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                <div><strong>Admission No:</strong> <?php echo htmlspecialchars($payment['admission_no']); ?></div>
                <div><strong>Father / Guardian:</strong> <?php echo htmlspecialchars($payment['father_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="col-6 text-end">
                <div><strong>Class &amp; Section:</strong> Class <?php echo htmlspecialchars($payment['class_name'] . ' - ' . $payment['section_name']); ?></div>
                <div><strong>Roll Number:</strong> <?php echo htmlspecialchars($payment['roll_no'] ?: 'N/A'); ?></div>
                <div><strong>Contact:</strong> <?php echo htmlspecialchars($payment['parent_phone'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <!-- Fee Items Breakdown -->
        <table class="table table-bordered align-middle mb-3 small" style="border-color: #cbd5e1;">
            <thead class="table-light">
                <tr>
                    <th>Particulars</th>
                    <th style="width: 120px;" class="text-end">Amount (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($payment['fee_title']); ?></span>
                        <small class="text-muted d-block">Session: <?php echo APP_YEAR; ?> &bull; Due Date: <?php echo date('d M Y', strtotime($payment['due_date'])); ?></small>
                    </td>
                    <td class="text-end fw-bold">PKR <?php echo number_format($payment['invoice_amount'], 2); ?></td>
                </tr>
                <?php if ((float)$payment['fine'] > 0): ?>
                    <tr>
                        <td class="text-danger">Late Payment Fine</td>
                        <td class="text-end text-danger">+ PKR <?php echo number_format($payment['fine'], 2); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ((float)$payment['discount'] > 0): ?>
                    <tr>
                        <td class="text-success">Concession / Sibling Discount</td>
                        <td class="text-end text-success">- PKR <?php echo number_format($payment['discount'], 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="fw-bold">
                <tr>
                    <td class="text-end">Net Payable Amount:</td>
                    <td class="text-end">PKR <?php echo number_format($netPayable, 2); ?></td>
                </tr>
                <tr class="table-success">
                    <td class="text-end">Amount Paid Now:</td>
                    <td class="text-end text-success fs-6">PKR <?php echo number_format($currentPaid, 2); ?></td>
                </tr>
                <tr class="<?php echo ($balance > 0) ? 'table-warning text-danger' : 'table-light'; ?>">
                    <td class="text-end">Remaining Outstanding Dues:</td>
                    <td class="text-end">PKR <?php echo number_format($balance, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer Notes & Signatures -->
        <div class="row align-items-end mt-4 pt-3 small">
            <div class="col-7">
                <div class="text-muted">
                    <em>* Computer-generated official receipt. Valid without signature when verified online. Fees once paid are non-refundable.</em>
                </div>
                <div class="mt-2 text-dark">
                    Cashier / Officer: <strong><?php echo htmlspecialchars($payment['cashier_name'] ?? 'Admin'); ?></strong>
                </div>
            </div>
            <div class="col-5 text-end">
                <div class="d-inline-block text-center border-top border-dark pt-2 px-3" style="min-width: 160px;">
                    <div class="fw-bold text-dark">Accounts Officer</div>
                    <small class="text-muted">Stamp &amp; Signature</small>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
            <h5 class="fw-bold mt-3">Receipt Record Not Found</h5>
            <p class="text-muted small">Please verify the payment ID or fee voucher ID.</p>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    body { background: white !important; font-size: 11pt; }
    .app-sidebar, .app-topbar, .app-footer, .sidebar-backdrop, .d-print-none { display: none !important; }
    .app-main, .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .receipt-sheet { border: 2px dashed #000 !important; box-shadow: none !important; margin: 0 auto !important; padding: 15px !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

