<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'student_officer'])) {
    header("Location: /index.php");
    exit;
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Handle search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'active';

// Prepare the query
$query = "SELECT * FROM students WHERE 1=1";
if ($search) {
    $query .= " AND (student_number LIKE :search 
                OR first_name LIKE :search 
                OR last_name LIKE :search)";
}
if ($status) {
    $query .= " AND status = :status";
}
$query .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    if ($search) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam);
    }
    if ($status) {
        $stmt->bindParam(':status', $status);
    }
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
    $error = "An error occurred while fetching the student list.";
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Student Management</h2>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Student
        </a>
    </div>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               placeholder="Search by name or student number..." 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="graduated" <?php echo $status === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                        <option value="withdrawn" <?php echo $status === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Students Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    No students found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['class_year']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($student['status']) {
                                                'active' => 'success',
                                                'inactive' => 'warning',
                                                'graduated' => 'primary',
                                                'withdrawn' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $student['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $student['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete"
                                                        onclick="confirmDelete(<?php echo $student['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this student? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="delete.php" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="student_id" id="deleteStudentId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(studentId) {
    document.getElementById('deleteStudentId').value = studentId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
