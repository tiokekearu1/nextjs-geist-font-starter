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
$fee = null;
$studentFees = [];

// Get fee ID from URL
$feeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$feeId) {
    header("Location: index.php");
    exit;
}

try {
    // Fetch fee details
    $stmt = $pdo->prepare("
        SELECT * FROM fees WHERE id = ?
    ");
    $stmt->execute([$feeId]);
    $fee = $stmt->fetch();

    if (!$fee) {
        header("Location: index.php");
        exit;
    }

    // Fetch student fees and payment details
    $stmt = $pdo->prepare("
        SELECT 
            sf.*,
            s.student_number,
            s.first_name,
            s.last_name,
            s.class_year,
            COUNT(p.id) as payment_count,
            MAX(p.payment_date) as last_payment_date
        FROM student_fees sf
        JOIN students s ON sf.student_id = s.id
        LEFT JOIN payments p ON p.student_fee_id = sf.id
        WHERE sf.fee_id = ?
        GROUP BY sf.id, s.student_number, s.first_name, s.last_name, s.class_year
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$feeId]);
    $studentFees = $stmt->fetchAll();

    // Calculate summary statistics
    $totalStudents = count($studentFees);
    $totalCollected = array_sum(array_column($studentFees, 'amount_paid'));
    $totalExpected = $fee['amount'] * $totalStudents;
    $collectionRate = $totalExpected > 0 ? ($totalCollected / $totalExpected) * 100 : 0;

    $paymentStatus = array_count_values(array_column($studentFees, 'payment_status'));
    $fullyPaid = $paymentStatus['paid'] ?? 0;
    $partiallyPaid = $paymentStatus['partial'] ?? 0;
    $unpaid = $paymentStatus['unpaid'] ?? 0;

} catch (PDOException $e) {
    error_log("Error fetching fee details: " . $e->getMessage());
    $error = 'An error occurred while fetching fee information';
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Fee Details</h2>
                <div>
                    <a href="edit.php?id=<?php echo $feeId; ?>" class="btn btn-primary">
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
        <!-- Fee Information -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Fee Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Fee Name:</th>
                            <td><?php echo htmlspecialchars($fee['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Amount:</th>
                            <td>$<?php echo number_format($fee['amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Academic Year:</th>
                            <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                        </tr>
                        <tr>
                            <th>Due Date:</th>
                            <td><?php echo date('F j, Y', strtotime($fee['due_date'])); ?></td>
                        </tr>
                        <?php if ($fee['description']): ?>
                            <tr>
                                <th>Description:</th>
                                <td><?php echo nl2br(htmlspecialchars($fee['description'])); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Collection Summary -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Collection Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Expected</h6>
                                    <h4 class="card-title">$<?php echo number_format($totalExpected, 2); ?></h4>
                                    <p class="card-text">From <?php echo $totalStudents; ?> students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Collected</h6>
                                    <h4 class="card-title">$<?php echo number_format($totalCollected, 2); ?></h4>
                                    <p class="card-text"><?php echo number_format($collectionRate, 1); ?>% collection rate</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6>Payment Status Distribution</h6>
                        <div class="progress" style="height: 25px;">
                            <?php
                            $paidPercent = ($fullyPaid / $totalStudents) * 100;
                            $partialPercent = ($partiallyPaid / $totalStudents) * 100;
                            $unpaidPercent = ($unpaid / $totalStudents) * 100;
                            ?>
                            <div class="progress-bar bg-success" 
                                 style="width: <?php echo $paidPercent; ?>%" 
                                 title="Fully Paid: <?php echo $fullyPaid; ?>">
                                <?php echo $fullyPaid; ?> Paid
                            </div>
                            <div class="progress-bar bg-warning" 
                                 style="width: <?php echo $partialPercent; ?>%" 
                                 title="Partially Paid: <?php echo $partiallyPaid; ?>">
                                <?php echo $partiallyPaid; ?> Partial
                            </div>
                            <div class="progress-bar bg-danger" 
                                 style="width: <?php echo $unpaidPercent; ?>%" 
                                 title="Unpaid: <?php echo $unpaid; ?>">
                                <?php echo $unpaid; ?> Unpaid
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Payment List -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Student Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Class Year</th>
                                    <th>Amount Paid</th>
                                    <th>Status</th>
                                    <th>Last Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentFees as $sf): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sf['student_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($sf['first_name'] . ' ' . $sf['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($sf['class_year']); ?></td>
                                        <td>
                                            $<?php echo number_format($sf['amount_paid'], 2); ?> / 
                                            $<?php echo number_format($fee['amount'], 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($sf['payment_status']) {
                                                    'paid' => 'success',
                                                    'partial' => 'warning',
                                                    default => 'danger'
                                                };
                                            ?>">
                                                <?php echo ucfirst($sf['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            echo $sf['last_payment_date'] 
                                                ? date('M j, Y', strtotime($sf['last_payment_date']))
                                                : '-';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="../payments/create.php?student_fee_id=<?php echo $sf['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Record Payment">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </a>
                                                <a href="../payments/history.php?student_fee_id=<?php echo $sf['id']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary"
                                                   title="Payment History">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
