<?php
/**
 * Class & Section Management Module
 * Roots School System
 */

$pageTitle = "Classes & Sections";
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subAction = $_POST['sub_action'] ?? '';

    // 1. ADD CLASS
    if ($subAction === 'add_class') {
        $className = trim($_POST['class_name'] ?? '');
        $level     = (int)($_POST['numeric_level'] ?? 1);

        if (!empty($className)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO classes (class_name, numeric_level) VALUES (?, ?)");
                $stmt->execute([$className, $level]);
                setFlash('success', "Class '{$className}' created successfully.");
                redirect(BASE_URL . 'admin/classes.php');
            } catch (Exception $e) {
                setFlash('danger', "Error creating class: " . $e->getMessage());
            }
        }
    }

    // 2. ADD SECTION
    if ($subAction === 'add_section') {
        $classId     = (int)($_POST['class_id'] ?? 0);
        $sectionName = trim($_POST['section_name'] ?? '');
        $roomNo      = trim($_POST['room_no'] ?? '');
        $capacity    = (int)($_POST['capacity'] ?? 35);

        if ($classId > 0 && !empty($sectionName)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO sections (class_id, section_name, room_no, capacity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$classId, $sectionName, $roomNo, $capacity]);
                setFlash('success', "Section '{$sectionName}' added to class.");
                redirect(BASE_URL . 'admin/classes.php');
            } catch (Exception $e) {
                setFlash('danger', "Section could not be created: " . $e->getMessage());
            }
        }
    }

    // 3. DELETE CLASS
    if ($subAction === 'delete_class') {
        $classId = (int)($_POST['class_id'] ?? 0);
        try {
            // Check if students enrolled
            $cnt = $pdo->query("SELECT COUNT(*) FROM students WHERE class_id = {$classId}")->fetchColumn();
            if ($cnt > 0) {
                setFlash('danger', "Cannot delete class: {$cnt} active students are currently enrolled in it.");
            } else {
                $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$classId]);
                setFlash('success', "Class removed successfully.");
            }
            redirect(BASE_URL . 'admin/classes.php');
        } catch (Exception $e) {
            setFlash('danger', "Delete error: " . $e->getMessage());
        }
    }

    // 4. DELETE SECTION
    if ($subAction === 'delete_section') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        try {
            $cnt = $pdo->query("SELECT COUNT(*) FROM students WHERE section_id = {$sectionId}")->fetchColumn();
            if ($cnt > 0) {
                setFlash('danger', "Cannot delete section: {$cnt} students are assigned to this section.");
            } else {
                $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$sectionId]);
                setFlash('success', "Section removed successfully.");
            }
            redirect(BASE_URL . 'admin/classes.php');
        } catch (Exception $e) {
            setFlash('danger', "Delete error: " . $e->getMessage());
        }
    }
}

// Fetch classes with section and student counts
$classes = $pdo->query("
    SELECT c.*, 
           COUNT(DISTINCT s.id) AS student_count,
           COUNT(DISTINCT sec.id) AS section_count
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id AND s.status = 'active'
    LEFT JOIN sections sec ON c.id = sec.class_id
    GROUP BY c.id
    ORDER BY c.numeric_level ASC
")->fetchAll();

// Fetch all sections with class name
$sections = $pdo->query("
    SELECT sec.*, c.class_name,
           COUNT(s.id) AS enrolled_students
    FROM sections sec
    JOIN classes c ON sec.class_id = c.id
    LEFT JOIN students s ON sec.id = s.section_id AND s.status = 'active'
    GROUP BY sec.id
    ORDER BY c.numeric_level ASC, sec.section_name ASC
")->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-0">Classes &amp; Sections</h3>
        <p class="text-muted small mb-0">Configure classroom wings, sections (A, B, C), room numbers, and capacity limits.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Class
        </button>
        <button class="btn btn-outline-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="bi bi-grid-3x3-gap me-1"></i> Add Section
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Classes Overview Column -->
    <div class="col-12 col-lg-5">
        <div class="content-card h-100">
            <div class="content-card-header">
                <span class="fw-bold text-dark"><i class="bi bi-building text-primary me-2"></i>Academic Classes</span>
                <span class="badge bg-light text-muted border"><?php echo count($classes); ?> Classes</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Level</th>
                            <th>Sections</th>
                            <th>Enrolled</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($classes)): ?>
                            <?php foreach ($classes as $c): ?>
                                <tr>
                                    <td><strong class="text-navy"><?php echo htmlspecialchars($c['class_name']); ?></strong></td>
                                    <td><span class="badge bg-light text-secondary border">#<?php echo $c['numeric_level']; ?></span></td>
                                    <td><span class="badge bg-info-subtle text-info-emphasis"><?php echo $c['section_count']; ?> Sec</span></td>
                                    <td><span class="badge bg-success-subtle text-success"><?php echo $c['student_count']; ?> Students</span></td>
                                    <td class="text-end">
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this class?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="sub_action" value="delete_class">
                                            <input type="hidden" name="class_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Class" <?php echo ($c['student_count'] > 0) ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No classes configured yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sections Detail Column -->
    <div class="col-12 col-lg-7">
        <div class="content-card h-100">
            <div class="content-card-header">
                <span class="fw-bold text-dark"><i class="bi bi-door-open-fill text-warning me-2"></i>Class Sections &amp; Rooms</span>
                <span class="badge bg-light text-muted border"><?php echo count($sections); ?> Sections</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Room No</th>
                            <th>Capacity</th>
                            <th>Enrolled</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sections)): ?>
                            <?php foreach ($sections as $sec): 
                                $occupancyPct = ($sec['capacity'] > 0) ? round(($sec['enrolled_students'] / $sec['capacity']) * 100) : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($sec['class_name']); ?></strong></td>
                                    <td><span class="badge bg-primary px-2.5 py-1">Section <?php echo htmlspecialchars($sec['section_name']); ?></span></td>
                                    <td><span class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($sec['room_no'] ?? 'N/A'); ?></span></td>
                                    <td><?php echo $sec['capacity']; ?> seats</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="small fw-semibold"><?php echo $sec['enrolled_students']; ?> / <?php echo $sec['capacity']; ?></span>
                                            <div class="progress flex-fill" style="height: 6px; width: 60px;">
                                                <div class="progress-bar <?php echo ($occupancyPct >= 90) ? 'bg-danger' : 'bg-primary'; ?>" style="width: <?php echo min(100, $occupancyPct); ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this section?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="sub_action" value="delete_section">
                                            <input type="hidden" name="section_id" value="<?php echo $sec['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Section" <?php echo ($sec['enrolled_students'] > 0) ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No sections configured yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= ADD CLASS MODAL ================= -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add Class</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="add_class">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class Name <span class="text-danger">*</span></label>
                        <input type="text" name="class_name" class="form-control form-control-sm" required placeholder="e.g. Nursery, Grade 1">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Numeric Level <span class="text-danger">*</span></label>
                        <input type="number" name="numeric_level" class="form-control form-control-sm" required min="0" max="15" value="<?php echo count($classes) + 1; ?>">
                        <small class="text-muted text-xs">Used for ascending sequence (e.g. 1 for Grade 1)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Create Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= ADD SECTION MODAL ================= -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-grid-3x3-gap me-2"></i>Add Section to Class</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sub_action" value="add_section">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Target Class <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select form-select-sm" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Section Name <span class="text-danger">*</span></label>
                            <input type="text" name="section_name" class="form-control form-control-sm" required placeholder="e.g. A, B, C">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Room Number</label>
                            <input type="text" name="room_no" class="form-control form-control-sm" placeholder="e.g. Room 204">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Seating Capacity</label>
                            <input type="number" name="capacity" class="form-control form-control-sm" value="35" min="10" max="60">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

