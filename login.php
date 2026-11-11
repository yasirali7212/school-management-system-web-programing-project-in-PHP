<?php
/**
 * Root Login Controller & View
 * Roots School System - Institutional Portal
 * Designed with Unslop principles (Clean, quiet chrome, deliberate typography)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to respective role dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'student';
    redirect(BASE_URL . $role . '/index.php');
}

$errorMessage = '';
$selectedRole = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity     = trim($_POST['identity'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $selectedRole = trim($_POST['role'] ?? 'admin');

    if (empty($identity) || empty($password)) {
        $errorMessage = "Please enter both username/email and password.";
    } else {
        $pdo = getDB();
        if (!$pdo) {
            $errorMessage = "Database connection offline. Ensure MySQL is running in XAMPP.";
        } else {
            try {
                // Query user by username or email and selected role
                $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ? LIMIT 1");
                $stmt->execute([$identity, $identity, $selectedRole]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $errorMessage = "Account deactivated. Please contact school administration.";
                    } else {
                        // Store session values
                        $_SESSION['user_id']   = $user['id'];
                        $_SESSION['username']  = $user['username'];
                        $_SESSION['email']     = $user['email'];
                        $_SESSION['user_role'] = $user['role'];

                        // Retrieve full name based on role
                        $fullName = $user['username'];
                        if ($user['role'] === 'student') {
                            $st = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM students WHERE user_id = ?");
                            $st->execute([$user['id']]);
                            $row = $st->fetch();
                            if ($row && !empty($row['name'])) $fullName = $row['name'];
                        } elseif ($user['role'] === 'teacher') {
                            $st = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM teachers WHERE user_id = ?");
                            $st->execute([$user['id']]);
                            $row = $st->fetch();
                            if ($row && !empty($row['name'])) $fullName = $row['name'];
                        } elseif ($user['role'] === 'parent') {
                            $st = $pdo->prepare("SELECT father_name AS name FROM parents WHERE user_id = ?");
                            $st->execute([$user['id']]);
                            $row = $st->fetch();
                            if ($row && !empty($row['name'])) $fullName = $row['name'];
                        }
                        $_SESSION['full_name'] = $fullName;

                        setFlash('success', "Signed in as {$fullName}.");
                        redirect(BASE_URL . $user['role'] . '/index.php');
                    }
                } else {
                    $errorMessage = "Invalid credentials for the " . ucfirst($selectedRole) . " portal.";
                }
            } catch (PDOException $e) {
                $errorMessage = "Authentication failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &bull; <?php echo APP_NAME; ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --accent: #1e3a8a;
            --accent-hover: #172554;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            color: var(--text-primary);
            background-image: 
                linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.85)),
                url('<?php echo BASE_URL; ?>assets/images/campus-library-bg.jpg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.35);
            max-width: 440px;
            width: 100%;
            margin: 0 auto;
            padding: 2.25rem 2rem;
        }

        /* Clean Segmented Role Controller */
        .role-segmented {
            display: flex;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 3px;
            gap: 2px;
        }

        .role-btn {
            flex: 1;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.45rem 0.25rem;
            border-radius: 6px;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .role-btn:hover {
            color: var(--text-primary);
        }

        .role-btn.active {
            background: #ffffff;
            color: var(--text-primary);
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        .form-control {
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.55rem 0.85rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            box-shadow: none !important;
        }

        .form-control:focus {
            border-color: #94a3b8;
            outline: none;
        }

        .input-group-text {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-right: none;
            color: var(--text-muted);
            border-radius: 6px 0 0 6px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 6px 6px 0;
        }

        .btn-submit {
            background-color: var(--accent);
            border: 1px solid var(--accent);
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.65rem 1rem;
            border-radius: 6px;
            transition: background-color 0.15s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: var(--accent-hover);
            color: #ffffff;
        }

        .demo-keycap {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.35rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: center;
        }

        .demo-keycap:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            color: var(--text-primary);
        }
    </style>
</head>
<body>

    <!-- Minimal Institutional Top Bar -->
    <header class="py-3 px-4 border-bottom border-light border-opacity-10 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2 text-white">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.svg" alt="Roots Crest" width="28" height="28" style="border-radius: 50%;">
            <span class="fw-semibold small tracking-wide"><?php echo APP_NAME; ?></span>
        </div>
        <div class="text-white text-opacity-75 small">
            Academic Session <?php echo APP_YEAR; ?>
        </div>
    </header>

    <!-- Main Container -->
    <main class="container my-auto py-4">
        <div class="auth-card">
            
            <!-- School Brand Identity -->
            <div class="text-center mb-4">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.svg" alt="Roots Crest" width="56" height="56" class="mb-2" style="border-radius: 50%;">
                <h4 class="fw-bold mb-1" style="color: #0f172a; letter-spacing: -0.01em;"><?php echo APP_NAME; ?></h4>
                <p class="text-muted small mb-0">Sign in to your portal account</p>
            </div>

            <!-- Error Notification -->
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger py-2 px-3 small border-0 mb-3" role="alert">
                    <i class="bi bi-exclamation-circle me-1.5"></i> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php echo displayFlash(); ?>

            <!-- Segmented Role Selector -->
            <div class="mb-3">
                <div class="role-segmented" id="roleSelectorGroup">
                    <button type="button" class="role-btn <?php echo ($selectedRole === 'admin') ? 'active' : ''; ?>" data-role="admin">Admin</button>
                    <button type="button" class="role-btn <?php echo ($selectedRole === 'teacher') ? 'active' : ''; ?>" data-role="teacher">Teacher</button>
                    <button type="button" class="role-btn <?php echo ($selectedRole === 'student') ? 'active' : ''; ?>" data-role="student">Student</button>
                    <button type="button" class="role-btn <?php echo ($selectedRole === 'parent') ? 'active' : ''; ?>" data-role="parent">Parent</button>
                </div>
            </div>

            <!-- Login Form -->
            <form method="POST" action="">
                <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($selectedRole); ?>">

                <!-- Identifier -->
                <div class="mb-3">
                    <label class="form-label small fw-medium" for="identity">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="identity" name="identity" placeholder="admin or admin@roots.edu" required autofocus value="<?php echo htmlspecialchars($_POST['identity'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-medium mb-0" for="password">Password</label>
                        <a href="#" class="small text-decoration-none text-muted" onclick="alert('Please contact the IT administrator to reset credentials.'); return false;">Forgot?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border-color: var(--border); border-left: none;">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-submit mt-2">Sign in</button>
            </form>

            <!-- Auto-fill Demo Accounts -->
            <div class="mt-4 pt-3 border-top">
                <div class="small text-muted mb-2 fw-medium" style="font-size: 0.75rem;">Demo access:</div>
                <div class="row g-2">
                    <div class="col-3">
                        <button type="button" class="demo-keycap w-100 demo-cred-btn" data-role="admin" data-user="admin" data-pass="admin123">Admin</button>
                    </div>
                    <div class="col-3">
                        <button type="button" class="demo-keycap w-100 demo-cred-btn" data-role="teacher" data-user="teacher1" data-pass="teacher123">Teacher</button>
                    </div>
                    <div class="col-3">
                        <button type="button" class="demo-keycap w-100 demo-cred-btn" data-role="student" data-user="student1" data-pass="student123">Student</button>
                    </div>
                    <div class="col-3">
                        <button type="button" class="demo-keycap w-100 demo-cred-btn" data-role="parent" data-user="parent1" data-pass="parent123">Parent</button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="py-3 text-center text-white text-opacity-50 small">
        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> &bull; Institutional Academic Portal
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Toggle password visibility
        const toggleBtn = document.getElementById('togglePassword');
        const passInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = passInput.type === 'password';
                passInput.type = isPassword ? 'text' : 'password';
                eyeIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        }

        // Role buttons toggle
        const roleBtns = document.querySelectorAll('.role-btn');
        const roleInput = document.getElementById('roleInput');
        const identityInput = document.getElementById('identity');

        roleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                roleBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const role = btn.getAttribute('data-role');
                roleInput.value = role;

                if (role === 'admin') identityInput.placeholder = 'admin or admin@roots.edu';
                else if (role === 'teacher') identityInput.placeholder = 'teacher1';
                else if (role === 'student') identityInput.placeholder = 'student1';
                else if (role === 'parent') identityInput.placeholder = 'parent1';
            });
        });

        // Demo credentials filler
        const demoBtns = document.querySelectorAll('.demo-cred-btn');
        demoBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const role = btn.getAttribute('data-role');
                const user = btn.getAttribute('data-user');
                const pass = btn.getAttribute('data-pass');

                const targetRoleBtn = document.querySelector(`.role-btn[data-role="${role}"]`);
                if (targetRoleBtn) targetRoleBtn.click();

                document.getElementById('identity').value = user;
                document.getElementById('password').value = pass;
            });
        });
    });
    </script>
</body>
</html>
