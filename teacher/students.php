<?php
/**
 * Teacher View Assigned Students
 * Roots School System
 */

$pageTitle = "My Class Students";
require_once __DIR__ . '/../includes/header.php';
requireRole('teacher');

$pdo = getDB();

// Get Teacher Record
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUser['id']]);
$teacher = $tStmt->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

// Fetch Teacher's Assigned Classes and Sections
$classesStmt = $pdo->prepare("
    SELECT DISTINCT ta.class_id, ta.section_id, c.class_name, sec.section_name
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    JOIN sections sec ON ta.section_id = sec.id
    WHERE ta.teacher_id = ?
    ORDER BY c.id ASC, sec.section_name ASC
");
$classesStmt->execute([$teacherId]);
$teacherClasses = $classesStmt->fetchAll();

$filterClassId = (int)($_GET['class_id'] ?? ($teacherClasses[0]['class_id'] ?? 0));
$filterSectionId = (int)($_GET['section_id'] ?? ($teacherClasses[0]['section_id'] ?? 0));
$searchKeyword = trim($_GET['search'] ?? '');

$students = [];
if ($filterClassId > 0 && $filterSectionId > 0) {
    $where = "s.class_id = ? AND s.section_id = ? AND s.status = 'Active'";
    $params = [$filterClassId, $filterSectionId];

    if (!empty($searchKeyword)) {
        $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
        $like = "%{$searchKeyword}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $stStmt = $pdo->prepare("
        SELECT s.*, c.class_name, sec.section_name, p.father_name, p.phone as parent_phone
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN parents p ON s.parent_id = p.id
        WHERE {$where}
        ORDER BY s.roll_no ASC, s.first_name ASC
    ");
    $stStmt->execute($params);
    $students = $stStmt->fetchAll();
}
?>

<div class="row align-items-center mb-4 g-3">
    <div class="col-md-7">
        <h2 class="fw-bold mb-1">My Class Students</h2>
        <p class="text-muted small mb-0">View enrolled student profiles and guardian contact information for your classes.</p>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?php echo BASE_URL; ?>teacher/index.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Class & Search Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Assigned Class &amp; Section</label>
                <select name="class_and_sec" class="form-select form-select-sm" onchange="
                    var parts = this.value.split('|');
                    this.form.class_id.value = parts[0];
                    this.form.section_id.value = parts[1];
                    this.form.submit();
                ">
                    <?php foreach ($teacherClasses as $tc): 
                        $val = $tc['class_id'] . '|' . $tc['section_id'];
                        $isSel = ($filterClassId == $tc['class_id'] && $filterSectionId == $tc['section_id']);
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo $isSel ? 'selected' : ''; ?>>
                            Class <?php echo htmlspecialchars($tc['class_name'] . ' - Section ' . $tc['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="class_id" value="<?php echo $filterClassId; ?>">
                <input type="hidden" name="section_id" value="<?php echo $filterSectionId; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Search Student</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or Admission #" value="<?php echo htmlspecialchars($searchKeyword); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Students Roster Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i>Enrolled Students (<?php echo count($students); ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px;">Roll #</th>
                    <th>Student Info</th>
                    <th>Gender</th>
                    <th>Father / Guardian</th>
                    <th>Parent Phone</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                    <?php foreach ($students as $st): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="avatar bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                        <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                        <small class="text-muted">Adm #: <?php echo htmlspecialchars($st['admission_no']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <?php echo htmlspecialchars($st['gender']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-dark fw-medium"><?php echo htmlspecialchars($st['father_name'] ?: 'N/A'); ?></span>
                            </td>
                            <td>
                                <?php if ($st['parent_phone']): ?>
                                    <a href="tel:<?php echo htmlspecialchars($st['parent_phone']); ?>" class="text-decoration-none small text-primary">
                                        <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($st['parent_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success">Active</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No students found matching your selection.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

