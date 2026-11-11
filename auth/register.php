<?php
/**
 * Roots School System - Registration
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already authenticated
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'student';
    redirect(BASE_URL . ($role === 'parent' ? 'student' : $role) . '/index.php');
}

$errorMessage = '';
$selectedRole = 'student';

// Check DB connection status
$dbWarning = ($pdo === null) ? "MySQL is offline. Start MySQL in XAMPP to enable new account creation." : null;

// Handle Registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($pdo === null) {
        $errorMessage = "Cannot complete registration: MySQL service is offline. Please start MySQL in XAMPP.";
    } else {
        $fullName        = trim($_POST['fullName'] ?? '');
        $email           = trim($_POST['schoolEmail'] ?? '');
        $enrollmentId    = trim($_POST['enrollmentId'] ?? '');
        $password        = trim($_POST['password'] ?? '');
        $confirmPassword = trim($_POST['confirmPassword'] ?? '');
        $rawRole         = trim($_POST['role'] ?? 'student');

        // Role mapping
        $roleMap = [
            'student' => 'student',
            'faculty' => 'teacher',
            'parent'  => 'parent'
        ];
        $selectedRole = array_key_exists($rawRole, $roleMap) ? $rawRole : 'student';
        $dbRole = $roleMap[$selectedRole];

        // Validation
        if (empty($fullName) || empty($email) || empty($enrollmentId) || empty($password)) {
            $errorMessage = "All required fields must be completed.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $errorMessage = "Password must contain at least 6 characters.";
        } elseif ($password !== $confirmPassword) {
            $errorMessage = "Password credentials do not match. Please verify.";
        } else {
            try {
                // Check if email or username (enrollmentId) already exists
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1");
                $checkStmt->execute([
                    'email'    => $email,
                    'username' => $enrollmentId
                ]);

                if ($checkStmt->fetch()) {
                    $errorMessage = "An account with this email or ID already exists. Please sign in.";
                } else {
                    // Atomic user creation
                    $pdo->beginTransaction();

                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $insertUser = $pdo->prepare("
                        INSERT INTO users (username, email, password, role, status) 
                        VALUES (:username, :email, :password, :role, 'active')
                    ");
                    $insertUser->execute([
                        'username' => $enrollmentId,
                        'email'    => $email,
                        'password' => $hashedPassword,
                        'role'     => $dbRole
                    ]);

                    $userId = $pdo->lastInsertId();

                    // Split name into first and last
                    $nameParts = explode(' ', $fullName, 2);
                    $firstName = $nameParts[0];
                    $lastName  = $nameParts[1] ?? '';

                    // Insert into corresponding profile table
                    if ($dbRole === 'student') {
                        $insertStudent = $pdo->prepare("
                            INSERT INTO students (user_id, roll_number, first_name, last_name, admission_date) 
                            VALUES (:uid, :roll, :fn, :ln, CURDATE())
                        ");
                        $insertStudent->execute([
                            'uid'  => $userId,
                            'roll' => $enrollmentId,
                            'fn'   => $firstName,
                            'ln'   => $lastName
                        ]);
                    } elseif ($dbRole === 'teacher') {
                        $insertTeacher = $pdo->prepare("
                            INSERT INTO teachers (user_id, first_name, last_name, hire_date) 
                            VALUES (:uid, :fn, :ln, CURDATE())
                        ");
                        $insertTeacher->execute([
                            'uid' => $userId,
                            'fn'  => $firstName,
                            'ln'  => $lastName
                        ]);
                    }

                    $pdo->commit();

                    setFlash('success', 'Account registered successfully! You may now sign in.');
                    redirect(BASE_URL . 'auth/login.php');
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorMessage = "Registration could not be completed. Please verify database connectivity.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo APP_NAME; ?> - Create Account</title>

    <!-- Google Fonts & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>

    <!-- Tailwind CDN & Custom Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/tailwind-config.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/auth.css">
</head>
<body class="auth-wallpaper-bg font-body-md text-on-surface">

    <!-- Header Navigation -->
    <header class="fixed top-0 w-full z-50 bg-slate-900/80 backdrop-blur-xl border-b border-white/10 shadow-lg text-white">
        <div class="h-16 w-full px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto flex items-center justify-between">
            <a href="<?php echo BASE_URL; ?>" class="flex items-center gap-3">
                <img alt="Roots Logo" class="h-9 w-9 rounded-full object-cover shadow-sm ring-2 ring-amber-400/40" src="<?php echo BASE_URL; ?>assets/images/logo.svg"/>
                <span class="font-title-md text-title-md text-white font-extrabold tracking-tight"><?php echo APP_NAME; ?></span>
            </a>

            <nav class="flex items-center gap-2">
                <a class="px-3.5 py-1.5 text-sm font-semibold text-slate-300 hover:text-white hover:bg-white/10 rounded-lg transition-colors" href="<?php echo BASE_URL; ?>auth/login.php">Login</a>
                <a aria-current="page" class="px-3.5 py-1.5 transition-colors bg-white/20 text-white font-semibold rounded-lg text-sm" href="<?php echo BASE_URL; ?>auth/register.php">Register</a>
            </nav>
        </div>
    </header>

    <!-- Main Registration Container -->
    <main class="w-full pt-16 min-h-screen flex items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
        <div class="w-full max-w-5xl my-auto">
            <!-- Main Dual-Chamber Canvas Card -->
            <div class="relative z-10 w-full auth-glass-card rounded-2xl sm:rounded-3xl p-4 sm:p-6 lg:p-8 flex flex-col lg:flex-row gap-6 lg:gap-8 items-stretch overflow-hidden">

                <!-- Left Showcase Column: Royal Pod Container -->
                <div class="showcase-pod order-2 lg:order-1 relative flex-1 lg:max-w-[440px] bg-gradient-to-br from-primary-container via-primary to-indigo-800 rounded-xl sm:rounded-2xl p-6 sm:p-8 text-on-primary flex flex-col justify-between overflow-hidden shadow-md">
                    <div class="absolute -top-16 -right-16 w-56 h-56 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="absolute bottom-10 -left-10 w-48 h-48 bg-black/20 rounded-full blur-xl pointer-events-none"></div>

                    <!-- Pod Top Section -->
                    <div class="relative z-10">
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight">
                            Join Roots Community
                        </h1>
                        <p class="mt-3 text-sm sm:text-base text-white/85 leading-relaxed">
                            Create your academic account to view attendance, exam marks, and connect with faculty.
                        </p>
                    </div>

                    <!-- Illustration -->
                    <div class="relative z-10 my-6 flex flex-col items-center justify-center">
                        <div class="relative w-full max-w-[280px] sm:max-w-[320px] aspect-square flex items-center justify-center">
                            <img alt="Student studying illustration" class="w-full h-full object-contain filter drop-shadow-lg select-none pointer-events-none hover:scale-105 transition-transform duration-300" src="<?php echo BASE_URL; ?>assets/images/student-hero.svg"/>
                        </div>
                    </div>

                    <div class="relative z-10 text-center">
                        <p class="text-xs text-white/75 font-medium">Roots School System &bull; Education for the Future</p>
                    </div>
                </div>

                <!-- Right Column: Registration Workspace -->
                <div class="workspace-chamber order-1 lg:order-2 flex-1 flex flex-col justify-center px-2 sm:px-4 lg:px-6 py-2">

                    <!-- Header Branding & Title -->
                    <div class="flex flex-col items-center text-center mb-6">
                        <div class="flex items-center gap-2 mb-2">
                            <img alt="Roots Logo" class="w-10 h-10 rounded-full object-cover ring-2 ring-primary/30 shadow-sm" src="<?php echo BASE_URL; ?>assets/images/logo.svg"/>
                            <span class="text-xl font-bold tracking-tight text-on-surface"><?php echo APP_NAME; ?></span>
                        </div>
                        <h2 class="text-2xl font-extrabold text-on-surface tracking-tight">
                            Create Account
                        </h2>
                        <p class="text-sm text-on-surface-variant mt-1">
                            Register as a student, parent, or faculty member
                        </p>
                    </div>

                    <!-- Alerts -->
                    <?php if (!empty($dbWarning)): ?>
                        <div class="mb-4 p-3 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">warning</span>
                            <span><?php echo htmlspecialchars($dbWarning); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errorMessage)): ?>
                        <div class="mb-4 p-3 bg-red-50 text-red-800 border border-red-200 rounded-lg text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">error</span>
                            <span><?php echo htmlspecialchars($errorMessage); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Account Type Selector -->
                    <div class="mb-5">
                        <div class="grid grid-cols-3 gap-1 bg-surface-container-low p-1 rounded-xl" id="roleSelector">
                            <button class="role-btn <?php echo ($selectedRole === 'student') ? 'bg-surface-container-lowest text-primary shadow-sm font-semibold' : 'text-on-surface-variant'; ?> flex items-center justify-center gap-1.5 py-2 px-1 rounded-lg text-xs sm:text-sm transition-all" data-role="student" onclick="selectRole('student')" type="button">
                                <span class="material-symbols-outlined text-[18px]">backpack</span>
                                <span>Student</span>
                            </button>
                            <button class="role-btn <?php echo ($selectedRole === 'parent') ? 'bg-surface-container-lowest text-primary shadow-sm font-semibold' : 'text-on-surface-variant'; ?> flex items-center justify-center gap-1.5 py-2 px-1 rounded-lg text-xs sm:text-sm transition-all" data-role="parent" onclick="selectRole('parent')" type="button">
                                <span class="material-symbols-outlined text-[18px]">supervisor_account</span>
                                <span>Parent</span>
                            </button>
                            <button class="role-btn <?php echo ($selectedRole === 'faculty') ? 'bg-surface-container-lowest text-primary shadow-sm font-semibold' : 'text-on-surface-variant'; ?> flex items-center justify-center gap-1.5 py-2 px-1 rounded-lg text-xs sm:text-sm transition-all" data-role="faculty" onclick="selectRole('faculty')" type="button">
                                <span class="material-symbols-outlined text-[18px]">co_present</span>
                                <span>Faculty</span>
                            </button>
                        </div>
                    </div>

                    <!-- Registration Form -->
                    <form class="space-y-4" method="POST" action="<?php echo BASE_URL; ?>auth/register.php" onsubmit="return validateRegistrationForm();">
                        <input type="hidden" name="role" id="selectedRoleInput" value="<?php echo htmlspecialchars($selectedRole); ?>">

                        <!-- Full Name -->
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-semibold text-on-surface" for="fullName">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <span class="absolute left-3.5 material-symbols-outlined text-outline text-[20px] pointer-events-none">badge</span>
                                <input name="fullName" class="w-full h-11 pl-11 pr-4 bg-surface-container-low focus:bg-surface-container-lowest rounded-lg text-sm text-on-surface placeholder:text-outline outline-none transition-all focus:shadow-[0_0_0_2px_#324bdc]" id="fullName" placeholder="e.g. Eleanor Vance" required type="text" value="<?php echo htmlspecialchars($_POST['fullName'] ?? ''); ?>"/>
                            </div>
                        </div>

                        <!-- Email & ID Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Email -->
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-semibold text-on-surface" for="schoolEmail">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 material-symbols-outlined text-outline text-[20px] pointer-events-none">alternate_email</span>
                                    <input name="schoolEmail" class="w-full h-11 pl-11 pr-4 bg-surface-container-low focus:bg-surface-container-lowest rounded-lg text-sm text-on-surface placeholder:text-outline outline-none transition-all focus:shadow-[0_0_0_2px_#324bdc]" id="schoolEmail" placeholder="student@roots.edu" required type="email" value="<?php echo htmlspecialchars($_POST['schoolEmail'] ?? ''); ?>"/>
                                </div>
                            </div>

                            <!-- Roll Number / ID -->
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-semibold text-on-surface" for="enrollmentId" id="enrollmentLabel">
                                    Student ID / Roll No <span class="text-red-500">*</span>
                                </label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 material-symbols-outlined text-outline text-[20px] pointer-events-none">pin</span>
                                    <input name="enrollmentId" class="w-full h-11 pl-11 pr-4 bg-surface-container-low focus:bg-surface-container-lowest rounded-lg text-sm text-on-surface placeholder:text-outline outline-none transition-all focus:shadow-[0_0_0_2px_#324bdc] uppercase" id="enrollmentId" placeholder="ID-88492" required type="text" value="<?php echo htmlspecialchars($_POST['enrollmentId'] ?? ''); ?>"/>
                                </div>
                            </div>
                        </div>

                        <!-- Password Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Password -->
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-semibold text-on-surface" for="passwordInput">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 material-symbols-outlined text-outline text-[20px] pointer-events-none">lock</span>
                                    <input name="password" class="w-full h-11 pl-11 pr-11 bg-surface-container-low focus:bg-surface-container-lowest rounded-lg text-sm text-on-surface placeholder:text-outline outline-none transition-all focus:shadow-[0_0_0_2px_#324bdc]" id="passwordInput" placeholder="Min 6 characters" required type="password"/>
                                    <button class="absolute right-3 p-1 text-outline hover:text-on-surface transition-colors" onclick="toggleVisibility('passwordInput', 'toggleEye1')" type="button">
                                        <span class="material-symbols-outlined text-[20px]" id="toggleEye1">visibility</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-semibold text-on-surface" for="confirmPasswordInput">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 material-symbols-outlined text-outline text-[20px] pointer-events-none">verified_user</span>
                                    <input name="confirmPassword" class="w-full h-11 pl-11 pr-11 bg-surface-container-low focus:bg-surface-container-lowest rounded-lg text-sm text-on-surface placeholder:text-outline outline-none transition-all focus:shadow-[0_0_0_2px_#324bdc]" id="confirmPasswordInput" placeholder="Confirm password" required type="password"/>
                                    <button class="absolute right-3 p-1 text-outline hover:text-on-surface transition-colors" onclick="toggleVisibility('confirmPasswordInput', 'toggleEye2')" type="button">
                                        <span class="material-symbols-outlined text-[20px]" id="toggleEye2">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Terms & Policy Checkbox -->
                        <div class="pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-on-surface-variant">
                                <input class="w-4 h-4 rounded text-primary focus:ring-0 accent-primary cursor-pointer" required type="checkbox"/>
                                <span>I agree to the School Terms and Privacy Policy.</span>
                            </label>
                        </div>

                        <!-- Primary Submission CTA Button -->
                        <button class="w-full h-11 bg-primary hover:bg-primary/90 text-on-primary font-semibold rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 tracking-wide text-sm mt-2" type="submit">
                            <span>Create Account</span>
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </form>

                    <!-- Bottom Navigation to Sign In -->
                    <div class="mt-6 text-center text-sm text-on-surface-variant">
                        Already have an account? 
                        <a class="text-primary font-bold hover:underline ml-1" href="<?php echo BASE_URL; ?>auth/login.php">
                            Sign In
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full bg-slate-950/80 backdrop-blur-xl border-t border-white/10 py-4 text-slate-400 text-xs text-center">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
            <div class="flex items-center gap-4">
                <a class="hover:text-white transition-colors" href="#">Privacy Policy</a>
                <a class="hover:text-white transition-colors" href="#">Terms of Service</a>
            </div>
        </div>
    </footer>

    <!-- Separated Registration JavaScript Logic -->
    <script src="<?php echo BASE_URL; ?>assets/js/register.js"></script>
</body>
</html>
