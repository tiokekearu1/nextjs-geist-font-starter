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
$studentFee = null;
$payments = [];

// Get student fee ID from URL
$studentFeeId = filter_input(INPUT_GET, 'student_fee_id', FILTER_VALIDATE_INT);

if (!$studentFeeId) {
    header("Location: /modules/fees/index.php");
    exit;
}

try {
    // Fetch student fee details with related information
    $stmt = $pdo->prepare("
        SELECT 
            sf.*,
            s.student_number,
            s.first_name,
            s.last_name,
            s.class_year,
            f.name as fee_name,
            f.amount as total_amount,
            f.due_date
        FROM student_fees sf
        JOIN students s ON sf.student_id = s.id
        JOIN fees f ON sf.fee_id = f.id
        WHERE sf.id = ?
    ");
    $stmt->execute([$studentFeeId]);
    $studentFee = $stmt->fetch();

    if (!$studentFee) {
        throw new Exception('Student fee record not found');
    }

    // Fetch payment history
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.full_name as recorded_by
        FROM payments p
        JOIN users u ON p.created_by = u.id
        WHERE p.student_fee_id = ?
        ORDER BY p.payment_date DESC, p.created_at DESC
    ");
    $stmt->execute([$studentFeeId]);
    $payments = $stmt->fetchAll();

    // Calculate remaining amount
    $remainingAmount = $studentFee['total_amount'] - $studentFee['amount_paid'];

} catch (Exception $e) {
    error_log("Error fetching payment history: " . $e->getMessage());
    $error = 'An error occurred while fetching payment information';
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Payment History</h2>
                <div>
                    <a href="create.php?student_fee_id=<?php echo $studentFeeId; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Record New Payment
                    </a>
                    <a href="/modules/fees/view.php?id=<?php echo $studentFee['fee_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Fee Details
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
        <!-- Payment Summary Card -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Student:</th>
                            <td>
                                <?php echo htmlspecialchars($studentFee['first_name'] . ' ' . $studentFee['last_name']); ?>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($studentFee['student_number']); ?>
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th>Fee:</th>
                            <td><?php echo htmlspecialchars($studentFee['fee_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Total Amount:</th>
                            <td>$<?php echo number_format($studentFee['total_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Amount Paid:</th>
                            <td>$<?php echo number_format($studentFee['amount_paid'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Remaining:</th>
                            <td>
                                <span class="<?php echo $remainingAmount > 0 ? 'text-danger' : 'text-success'; ?>">
                                    $<?php echo number_format($remainingAmount, 2); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Due Date:</th>
                            <td>
                                <?php 
                                $dueDate = strtotime($studentFee['due_date']);
                                $today = strtotime('today');
                                $isPastDue = $dueDate < $today && $remainingAmount > 0;
                                ?>
                                <span class="<?php echo $isPastDue ? 'text-danger' : ''; ?>">
                                    <?php echo date('F j, Y', $dueDate); ?>
                                    <?php if ($isPastDue): ?>
                                        <br><small class="text-danger">Past Due</small>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($studentFee['payment_status']) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        default => 'danger'
                                    };
                                ?>">
                                    <?php echo ucfirst($studentFee['payment_status']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Records</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <p class="text-muted text-center py-4">No payment records found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Receipt #</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Notes</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $payment['notes'] ? htmlspecialchars($payment['notes']) : '-'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['recorded_by']); ?></td>
                                            <td>
                                                <a href="receipt.php?id=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="View Receipt"
                                                   target="_blank">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
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
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
