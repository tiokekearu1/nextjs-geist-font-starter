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
$studentFee = null;
$student = null;
$fee = null;

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

    // Calculate remaining amount
    $remainingAmount = $studentFee['total_amount'] - $studentFee['amount_paid'];

} catch (Exception $e) {
    error_log("Error fetching student fee details: " . $e->getMessage());
    $error = 'An error occurred while fetching payment information';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        // Validate and sanitize input
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $paymentDate = filter_input(INPUT_POST, 'payment_date', FILTER_SANITIZE_STRING);
        $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (!$amount || !$paymentDate || !$paymentMethod) {
            $error = 'Please fill in all required fields';
        } elseif ($amount <= 0) {
            $error = 'Payment amount must be greater than zero';
        } elseif ($amount > $remainingAmount) {
            $error = 'Payment amount cannot exceed the remaining balance';
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Generate receipt number
                $receiptNumber = date('Ymd') . '-' . sprintf('%04d', rand(0, 9999));

                // Record payment
                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        student_fee_id, amount, payment_date, payment_method,
                        receipt_number, notes, created_by
                    ) VALUES (
                        :student_fee_id, :amount, :payment_date, :payment_method,
                        :receipt_number, :notes, :created_by
                    )
                ");

                $stmt->execute([
                    'student_fee_id' => $studentFeeId,
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'receipt_number' => $receiptNumber,
                    'notes' => $notes,
                    'created_by' => $_SESSION['user_id']
                ]);

                // Update student_fees record
                $newAmountPaid = $studentFee['amount_paid'] + $amount;
                $newStatus = ($newAmountPaid >= $studentFee['total_amount']) ? 'paid' : 
                            ($newAmountPaid > 0 ? 'partial' : 'unpaid');

                $stmt = $pdo->prepare("
                    UPDATE student_fees 
                    SET amount_paid = :amount_paid,
                        payment_status = :payment_status
                    WHERE id = :id
                ");

                $stmt->execute([
                    'amount_paid' => $newAmountPaid,
                    'payment_status' => $newStatus,
                    'id' => $studentFeeId
                ]);

                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, details)
                    VALUES (:user_id, 'payment_recorded', :details)
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'details' => "Recorded payment of $" . number_format($amount, 2) . 
                                " for student " . $studentFee['student_number']
                ]);

                // Commit transaction
                $pdo->commit();

                $success = 'Payment has been recorded successfully';
                
                // Refresh student fee data
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
                $remainingAmount = $studentFee['total_amount'] - $studentFee['amount_paid'];

            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Error recording payment: " . $e->getMessage());
                $error = 'An error occurred while recording the payment';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Record Payment</h2>
                <a href="/modules/fees/view.php?id=<?php echo $studentFee['fee_id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Fee Details
                </a>
            </div>
        </div>
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

    <div class="row">
        <!-- Payment Information Card -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Information</h5>
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
                            <td>$<?php echo number_format($remainingAmount, 2); ?></td>
                        </tr>
                        <tr>
                            <th>Due Date:</th>
                            <td><?php echo date('F j, Y', strtotime($studentFee['due_date'])); ?></td>
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

        <!-- Payment Form -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Record New Payment</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                        <div class="row g-3">
                            <!-- Amount -->
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Payment Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="amount" 
                                           name="amount" 
                                           step="0.01" 
                                           min="0.01" 
                                           max="<?php echo $remainingAmount; ?>"
                                           required>
                                </div>
                                <div class="form-text">
                                    Maximum allowed: $<?php echo number_format($remainingAmount, 2); ?>
                                </div>
                            </div>

                            <!-- Payment Date -->
                            <div class="col-md-6">
                                <label for="payment_date" class="form-label">Payment Date *</label>
                                <input type="date" 
                                       class="form-control" 
                                       id="payment_date" 
                                       name="payment_date" 
                                       value="<?php echo date('Y-m-d'); ?>"
                                       required>
                            </div>

                            <!-- Payment Method -->
                            <div class="col-md-6">
                                <label for="payment_method" class="form-label">Payment Method *</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>

                            <!-- Notes -->
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" 
                                          id="notes" 
                                          name="notes" 
                                          rows="3"></textarea>
                            </div>

                            <div class="col-12">
                                <hr class="my-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Record Payment
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
