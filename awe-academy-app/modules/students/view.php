<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'student_officer'])) {
    header("Location: /index.php");
    exit;
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

$error = '';
$student = null;
$fees = [];
$supplies = [];

// Get student ID from URL
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$studentId) {
    header("Location: index.php");
    exit;
}

try {
    // Fetch student data
    $stmt = $pdo->prepare("
        SELECT * FROM students 
        WHERE id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        header("Location: index.php");
        exit;
    }

    // Fetch student fees
    $stmt = $pdo->prepare("
        SELECT sf.*, f.name as fee_name, f.amount as total_amount
        FROM student_fees sf
        JOIN fees f ON sf.fee_id = f.id
        WHERE sf.student_id = ?
        ORDER BY f.due_date DESC
    ");
    $stmt->execute([$studentId]);
    $fees = $stmt->fetchAll();

    // Fetch supply distributions
    $stmt = $pdo->prepare("
        SELECT sd.*, s.name as supply_name, u.full_name as distributor_name
        FROM supply_distributions sd
        JOIN supplies s ON sd.supply_id = s.id
        JOIN users u ON sd.distributed_by = u.id
        WHERE sd.student_id = ?
        ORDER BY sd.distribution_date DESC
    ");
    $stmt->execute([$studentId]);
    $supplies = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching student details: " . $e->getMessage());
    $error = 'An error occurred while fetching student information';
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Student Details</h2>
                <div>
                    <a href="edit.php?id=<?php echo $studentId; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Student Information -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Personal Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="35%">Student Number:</th>
                            <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Name:</th>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth:</th>
                            <td><?php echo date('F j, Y', strtotime($student['date_of_birth'])); ?></td>
                        </tr>
                        <tr>
                            <th>Gender:</th>
                            <td>
                                <?php 
                                echo match($student['gender']) {
                                    'M' => 'Male',
                                    'F' => 'Female',
                                    'O' => 'Other',
                                    default => 'Not Specified'
                                };
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Class Year:</th>
                            <td><?php echo htmlspecialchars($student['class_year']); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
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
                        </tr>
                        <tr>
                            <th>Address:</th>
                            <td><?php echo nl2br(htmlspecialchars($student['address'])); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td><?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($student['email'] ?: 'Not provided'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Fees Information -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Fees & Payments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($fees)): ?>
                        <p class="text-muted">No fee records found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fees as $fee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                            <td><?php echo number_format($fee['total_amount'], 2); ?></td>
                                            <td><?php echo number_format($fee['amount_paid'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($fee['payment_status']) {
                                                        'paid' => 'success',
                                                        'partial' => 'warning',
                                                        default => 'danger'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($fee['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Supply Distribution History -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Supply Distribution History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($supplies)): ?>
                        <p class="text-muted">No supply distribution records found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Supply Item</th>
                                        <th>Quantity</th>
                                        <th>Distributed By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supplies as $supply): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($supply['distribution_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($supply['supply_name']); ?></td>
                                            <td><?php echo htmlspecialchars($supply['quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($supply['distributor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($supply['notes'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
