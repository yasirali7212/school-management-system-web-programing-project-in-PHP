<?php
/**
 * School Announcements & Notice Board Management
 * Roots School System
 */

$pageTitle = "Announcements Management";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. CREATE ANNOUNCEMENT
    if ($action === 'create_announcement') {
        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $targetRole = trim($_POST['target_role'] ?? 'all');
        $startDate  = trim($_POST['start_date'] ?? date('Y-m-d'));
        $expiryDate = trim($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+30 days')));

        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (title, content, target_role, start_date, expiry_date, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $content, $targetRole, $startDate, $expiryDate, $currentUser['id']]);
                setFlash('success', "Notice '{$title}' published successfully.");
                redirect(BASE_URL . 'admin/announcements.php');
            } catch (Exception $e) {
                setFlash('danger', "Failed to post announcement: " . $e->getMessage());
            }
        } else {
            setFlash('danger', "Title and content cannot be blank.");
        }
    }

    // 2. DELETE ANNOUNCEMENT
    if ($action === 'delete_announcement') {
        $noticeId = (int)($_POST['notice_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$noticeId]);
            setFlash('success', "Notice deleted.");
            redirect(BASE_URL . 'admin/announcements.php');
        } catch (Exception $e) {
            setFlash('danger', "Delete error: " . $e->getMessage());
        }
    }
}

// Fetch Announcements
$notices = $pdo->query("
    SELECT a.*, u.name as author_name
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
")->fetchAll();
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">School Announcements</h2>
        <p class="text-muted small mb-0">Publish official circulars and notices targeted by role (Teachers, Students, Parents).</p>
    </div>
    <div class="col-md-6 text-md-end">
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
            <i class="bi bi-megaphone me-1"></i> Post New Notice
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-bell-fill me-2 text-primary"></i>All Published Notices (<?php echo count($notices); ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Notice Title &amp; Details</th>
                    <th>Audience</th>
                    <th>Active Duration</th>
                    <th>Posted By</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($notices)): ?>
                    <?php $no = 1; foreach ($notices as $n): 
                        $isExpired = (strtotime($n['expiry_date']) < strtotime(date('Y-m-d')));
                    ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo $no++; ?></td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="text-muted small text-truncate" style="max-width: 450px;">
                                    <?php echo htmlspecialchars($n['content']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-uppercase px-2.5 py-1">
                                    <i class="bi bi-people me-1"></i><?php echo htmlspecialchars($n['target_role']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="d-block text-secondary">
                                    <i class="bi bi-calendar-event me-1"></i><?php echo date('d M Y', strtotime($n['start_date'])); ?> &rarr; <?php echo date('d M Y', strtotime($n['expiry_date'])); ?>
                                </small>
                            </td>
                            <td>
                                <span class="small fw-medium text-dark"><?php echo htmlspecialchars($n['author_name']); ?></span>
                            </td>
                            <td>
                                <?php if ($isExpired): ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Expired</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger px-2.5 py-1" title="Delete Announcement">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No school announcements posted yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODAL: POST NOTICE ================= -->
<div class="modal fade" id="createNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-megaphone me-2"></i>Post School Announcement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_announcement">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notice Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Annual Sports Day &amp; Parent-Teacher Meeting">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Target Audience <span class="text-danger">*</span></label>
                            <select name="target_role" class="form-select form-select-sm" required>
                                <option value="all">Everyone (All Roles)</option>
                                <option value="teacher">Teachers Only</option>
                                <option value="student">Students Only</option>
                                <option value="parent">Parents Only</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Start / Publish Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Notice Body / Description <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control form-control-sm" rows="5" required placeholder="Type the complete circular text, guidelines, instructions, or meeting schedule..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Publish Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

