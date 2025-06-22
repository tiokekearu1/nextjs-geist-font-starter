<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'finance_officer'])) {
    header("Location: /index.php");
    exit;
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

$error = '';
$success = '';

// Get any messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Handle search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$academicYear = isset($_GET['academic_year']) ? $_GET['academic_year'] : date('Y');
$paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';

try {
    // Fetch all academic years for filter
    $stmt = $pdo->query("SELECT DISTINCT academic_year FROM fees ORDER BY academic_year DESC");
    $academicYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Prepare the main query
    $query = "
        SELECT 
            f.id,
            f.name,
            f.amount,
            f.academic_year,
            f.due_date,
            COUNT(DISTINCT sf.student_id) as total_students,
            SUM(sf.amount_paid) as total_collected,
            COUNT(CASE WHEN sf.payment_status = 'paid' THEN 1 END) as fully_paid,
            COUNT(CASE WHEN sf.payment_status = 'partial' THEN 1 END) as partially_paid,
            COUNT(CASE WHEN sf.payment_status = 'unpaid' THEN 1 END) as unpaid
        FROM fees f
        LEFT JOIN student_fees sf ON f.id = sf.fee_id
        WHERE 1=1
    ";

    $params = [];

    if ($search) {
        $query .= " AND f.name LIKE :search";
        $params[':search'] = "%$search%";
    }

    if ($academicYear) {
        $query .= " AND f.academic_year = :academic_year";
        $params[':academic_year'] = $academicYear;
    }

    $query .= " GROUP BY f.id, f.name, f.amount, f.academic_year, f.due_date ORDER BY f.due_date DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $fees = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching fees: " . $e->getMessage());
    $error = "An error occurred while fetching the fees list.";
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Fees Management</h2>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Fee
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               placeholder="Search fees..." 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="academic_year" class="form-select" onchange="this.form.submit()">
                        <option value="">All Academic Years</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?php echo $year; ?>" 
                                    <?php echo $academicYear == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="payment_status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Payment Statuses</option>
                        <option value="paid" <?php echo $paymentStatus === 'paid' ? 'selected' : ''; ?>>Fully Paid</option>
                        <option value="partial" <?php echo $paymentStatus === 'partial' ? 'selected' : ''; ?>>Partially Paid</option>
                        <option value="unpaid" <?php echo $paymentStatus === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Fees Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fee Name</th>
                            <th>Amount</th>
                            <th>Academic Year</th>
                            <th>Due Date</th>
                            <th>Total Students</th>
                            <th>Collection Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fees)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    No fees found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fee['name']); ?></td>
                                    <td><?php echo number_format($fee['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></td>
                                    <td><?php echo $fee['total_students']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1" style="height: 10px;">
                                                <?php
                                                $total = $fee['total_students'] ?: 1;
                                                $paidPercent = ($fee['fully_paid'] / $total) * 100;
                                                $partialPercent = ($fee['partially_paid'] / $total) * 100;
                                                ?>
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?php echo $paidPercent; ?>%" 
                                                     title="Fully Paid: <?php echo $fee['fully_paid']; ?>">
                                                </div>
                                                <div class="progress-bar bg-warning" 
                                                     style="width: <?php echo $partialPercent; ?>%" 
                                                     title="Partially Paid: <?php echo $fee['partially_paid']; ?>">
                                                </div>
                                            </div>
                                            <span class="ms-2">
                                                <?php 
                                                echo number_format(($fee['total_collected'] / ($fee['amount'] * $total)) * 100, 1);
                                                ?>%
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $fee['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $fee['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete"
                                                        onclick="confirmDelete(<?php echo $fee['id']; ?>)">
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
                Are you sure you want to delete this fee? This will also remove all associated payment records.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="delete.php" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="fee_id" id="deleteFeeId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(feeId) {
    document.getElementById('deleteFeeId').value = feeId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
