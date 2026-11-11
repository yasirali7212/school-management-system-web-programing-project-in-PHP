<?php
/**
 * Parent View Children Profiles
 * Roots School System
 */

$pageTitle = "My Children Profiles";
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
    SELECT s.*, c.class_name, sec.section_name
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE s.parent_id = ?
    ORDER BY s.id ASC
");
$cStmt->execute([$parentId]);
$children = $cStmt->fetchAll();
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">My Children Profiles</h2>
        <p class="text-muted small mb-0">Detailed academic enrolment records and institutional data for your children.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>parent/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="row g-4">
    <?php if (!empty($children)): ?>
        <?php foreach ($children as $ch): ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="badge bg-primary px-2.5 py-1 text-uppercase fw-semibold">Class <?php echo htmlspecialchars($ch['class_name'] . ' - ' . $ch['section_name']); ?></span>
                        <span class="badge bg-success-subtle text-success">Active Enrolled</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="avatar-lg bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-3 shadow-sm" style="width: 65px; height: 65px;">
                                <?php echo strtoupper(substr($ch['first_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($ch['first_name'] . ' ' . $ch['last_name']); ?></h4>
                                <div class="text-muted small">Admission #: <strong><?php echo htmlspecialchars($ch['admission_no']); ?></strong></div>
                                <div class="text-muted small">Roll #: <strong><?php echo htmlspecialchars($ch['roll_no'] ?: 'N/A'); ?></strong></div>
                            </div>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-6">
                                <span class="text-muted d-block">Date of Birth:</span>
                                <strong class="text-dark"><?php echo $ch['dob'] ? date('d M Y', strtotime($ch['dob'])) : 'N/A'; ?></strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Gender:</span>
                                <strong class="text-dark"><?php echo htmlspecialchars($ch['gender']); ?></strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Blood Group:</span>
                                <strong class="text-dark"><?php echo htmlspecialchars($ch['blood_group'] ?: 'N/A'); ?></strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Admission Date:</span>
                                <strong class="text-dark"><?php echo $ch['admission_date'] ? date('d M Y', strtotime($ch['admission_date'])) : 'N/A'; ?></strong>
                            </div>
                            <div class="col-12">
                                <span class="text-muted d-block">Address:</span>
                                <span class="text-dark"><?php echo htmlspecialchars($ch['address'] ?: 'On File'); ?></span>
                            </div>
                        </div>

                        <div class="pt-4 border-top mt-4 d-flex gap-2">
                            <a href="<?php echo BASE_URL; ?>parent/attendance.php?child_id=<?php echo $ch['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="bi bi-calendar-check me-1"></i> Attendance Log
                            </a>
                            <a href="<?php echo BASE_URL; ?>parent/results.php?child_id=<?php echo $ch['id']; ?>" class="btn btn-outline-success btn-sm flex-fill">
                                <i class="bi bi-award me-1"></i> Exam Results
                            </a>
                            <a href="<?php echo BASE_URL; ?>parent/fees.php" class="btn btn-outline-secondary btn-sm flex-fill">
                                <i class="bi bi-receipt me-1"></i> Fee Invoices
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="bi bi-people text-muted fs-1 mb-2 d-block"></i>
                    <h5 class="fw-bold">No Children Found</h5>
                    <p class="text-muted small">Please contact school administration to link student records to your guardian profile.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

