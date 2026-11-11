<?php
/**
 * Student Personal Profile View
 * Roots School System
 */

$pageTitle = "My Profile";
require_once __DIR__ . '/../includes/header.php';
requireRole('student');

$pdo = getDB();

$stStmt = $pdo->prepare("
    SELECT s.*, c.class_name, sec.section_name, 
           p.father_name, p.mother_name, p.phone as parent_phone, p.email as parent_email, p.occupation, p.address as parent_address,
           u.email as student_email, u.username
    FROM students s
    JOIN classes c ON s.class_id = c.id
    JOIN sections sec ON s.section_id = sec.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN parents p ON s.parent_id = p.id
    WHERE s.user_id = ?
");
$stStmt->execute([$currentUser['id']]);
$student = $stStmt->fetch();
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">My Student Profile</h2>
        <p class="text-muted small mb-0">Official institutional record and guardian details.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>student/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<?php if ($student): ?>
    <div class="row g-4">
        <!-- Profile Identity Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center p-4">
                <div class="card-body">
                    <div class="avatar-lg mx-auto mb-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 90px; height: 90px; font-size: 2.2rem;">
                        <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 fw-semibold mb-2">
                        Class <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?>
                    </span>
                    <div class="text-muted small">Roll #: <strong><?php echo htmlspecialchars($student['roll_no'] ?: 'N/A'); ?></strong></div>
                    <div class="text-muted small">Adm #: <strong><?php echo htmlspecialchars($student['admission_no']); ?></strong></div>

                    <hr class="my-3 text-secondary opacity-25">

                    <div class="text-start small">
                        <div class="mb-2"><span class="text-muted">Username:</span> <strong class="text-dark"><?php echo htmlspecialchars($student['username']); ?></strong></div>
                        <div class="mb-2"><span class="text-muted">Status:</span> <span class="badge bg-success-subtle text-success">Active Enrolled</span></div>
                        <div><span class="text-muted">Academic Year:</span> <strong class="text-dark"><?php echo APP_YEAR; ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Information Tabs -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Personal &amp; Contact Details</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">First Name</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['first_name']); ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Last Name</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['last_name']); ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Date of Birth</label>
                            <span class="fw-semibold text-dark"><?php echo $student['dob'] ? date('d F Y', strtotime($student['dob'])) : 'N/A'; ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Gender</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['gender'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Blood Group</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['blood_group'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Admission Date</label>
                            <span class="fw-semibold text-dark"><?php echo $student['admission_date'] ? date('d F Y', strtotime($student['admission_date'])) : 'N/A'; ?></span>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small d-block">Residential Address</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['address'] ?: 'Not recorded'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parent / Guardian Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>Parent / Guardian Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Father's Full Name</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['father_name'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Mother's Full Name</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['mother_name'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Guardian Contact Phone</label>
                            <span class="fw-semibold text-primary">
                                <?php if ($student['parent_phone']): ?>
                                    <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($student['parent_phone']); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Guardian Email</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['parent_email'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Occupation</label>
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['occupation'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

