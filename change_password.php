<?php
/**
 * Universal Change Password
 * Roots School System
 * Unslop: Crisp typography, clear feedback, secure credential handling
 */

$pageTitle = "Change Password";
require_once __DIR__ . '/includes/header.php';

// Ensure user is authenticated
if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

$currentUser = getCurrentUser();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPass = trim($_POST['current_password'] ?? '');
    $newPass     = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        setFlash('danger', 'Please enter your current password, new password, and confirmation.');
    } elseif ($newPass !== $confirmPass) {
        setFlash('danger', 'The new password and confirmation password do not match.');
    } elseif (strlen($newPass) < 6) {
        setFlash('danger', 'Your new password must be at least 6 characters long.');
    } else {
        try {
            // Verify current password against database
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $currentHash = $stmt->fetchColumn();

            if (!$currentHash || !password_verify($currentPass, $currentHash)) {
                setFlash('danger', 'The current password you entered is incorrect.');
            } else {
                // Update with new hashed password
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $currentUser['id']]);

                setFlash('success', 'Your password has been updated successfully. Please use your new password next time you sign in.');
                redirect(BASE_URL . 'change_password.php');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error updating password: ' . $e->getMessage());
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <!-- Breadcrumb / Header -->
        <div class="mb-4">
            <h2 class="page-title">Change Password</h2>
            <p class="page-subtitle">Update your portal security credentials &bull; <?php echo htmlspecialchars($currentUser['username']); ?> (<?php echo ucfirst($currentUser['role']); ?>)</p>
        </div>

        <!-- Identity Summary Card -->
        <div class="card border mb-4">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar" style="width: 42px; height: 42px; font-size: 1rem;">
                        <?php echo strtoupper(substr($currentUser['name'] ?? $currentUser['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($currentUser['name'] ?? $currentUser['username']); ?></div>
                        <div class="small text-muted font-monospace"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                    </div>
                </div>
                <span class="badge bg-secondary-subtle text-capitalize px-2.5 py-1">
                    <?php echo htmlspecialchars($currentUser['role']); ?>
                </span>
            </div>
        </div>

        <!-- Change Password Form Card -->
        <div class="card border shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold text-dark"><i class="bi bi-shield-lock me-2 text-primary"></i>Security Credentials</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label small fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Enter current password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('current_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label small fw-semibold">New Password <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required placeholder="Minimum 6 characters">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">Must be at least 6 characters long.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label small fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required placeholder="Repeat new password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <a href="<?php echo BASE_URL . $currentUser['role'] . '/index.php'; ?>" class="btn btn-outline-secondary btn-sm px-3">
                            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary btn-sm px-4">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
