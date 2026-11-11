<?php
/**
 * Parent School Notices & Circulars
 * Roots School System
 */

$pageTitle = "School Notices";
require_once __DIR__ . '/../includes/header.php';
requireRole('parent');

$pdo = getDB();

$notices = $pdo->query("
    SELECT a.*, u.name as author_name
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    WHERE a.target_role IN ('all', 'parent')
    ORDER BY a.created_at DESC
")->fetchAll();
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">School Notices &amp; Circulars</h2>
        <p class="text-muted small mb-0">Official updates, event invitations, fee circulars, and announcements for parents.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>parent/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="row g-4">
    <?php if (!empty($notices)): ?>
        <?php foreach ($notices as $n): 
            $isExpired = (strtotime($n['expiry_date']) < strtotime(date('Y-m-d')));
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm <?php echo $isExpired ? 'opacity-75' : ''; ?>">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge <?php echo $isExpired ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary'; ?> px-2.5 py-1">
                                <i class="bi bi-megaphone me-1"></i><?php echo $isExpired ? 'Archived' : 'Official Notice'; ?>
                            </span>
                            <small class="text-muted"><?php echo date('d M Y', strtotime($n['start_date'])); ?></small>
                        </div>
                        <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($n['title']); ?></h5>
                        <p class="text-secondary small flex-grow-1" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($n['content']); ?>
                        </p>
                        <div class="pt-3 border-top mt-auto small text-muted d-flex justify-content-between align-items-center">
                            <span>Author: <strong><?php echo htmlspecialchars($n['author_name']); ?></strong></span>
                            <span>Expires: <?php echo date('d M Y', strtotime($n['expiry_date'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="bi bi-megaphone text-muted" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mt-3">No Notices Posted</h5>
                    <p class="text-muted small">All circulars and official communications will appear here once posted by administration.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

