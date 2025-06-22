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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $academicYear = filter_input(INPUT_POST, 'academic_year', FILTER_SANITIZE_STRING);
        $dueDate = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);
        $applyToAll = isset($_POST['apply_to_all']) ? true : false;

        // Validate required fields
        if (!$name || !$amount || !$academicYear || !$dueDate) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Insert new fee
                $stmt = $pdo->prepare("
                    INSERT INTO fees (name, amount, description, academic_year, due_date)
                    VALUES (:name, :amount, :description, :academic_year, :due_date)
                ");

                $stmt->execute([
                    'name' => $name,
                    'amount' => $amount,
                    'description' => $description,
                    'academic_year' => $academicYear,
                    'due_date' => $dueDate
                ]);

                $feeId = $pdo->lastInsertId();

                // If apply to all students is checked, create student_fees records
                if ($applyToAll) {
                    $stmt = $pdo->prepare("
                        INSERT INTO student_fees (student_id, fee_id, amount_paid, payment_status)
                        SELECT id, :fee_id, 0, 'unpaid'
                        FROM students
                        WHERE status = 'active'
                    ");
                    $stmt->execute(['fee_id' => $feeId]);
                }

                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, details)
                    VALUES (:user_id, 'fee_created', :details)
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'details' => "Created fee: $name"
                ]);

                // Commit transaction
                $pdo->commit();

                $success = 'Fee has been created successfully';
                // Redirect to fee list after short delay
                header("refresh:2;url=index.php");

            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Error creating fee: " . $e->getMessage());
                $error = 'An error occurred while creating the fee';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Create New Fee</h2>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
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

    <div class="card">
        <div class="card-body">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                <div class="row g-3">
                    <!-- Fee Name -->
                    <div class="col-md-6">
                        <label for="name" class="form-label">Fee Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a fee name.
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="col-md-6">
                        <label for="amount" class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   class="form-control" 
                                   id="amount" 
                                   name="amount" 
                                   step="0.01" 
                                   min="0" 
                                   required>
                            <div class="invalid-feedback">
                                Please provide a valid amount.
                            </div>
                        </div>
                    </div>

                    <!-- Academic Year -->
                    <div class="col-md-6">
                        <label for="academic_year" class="form-label">Academic Year *</label>
                        <select class="form-select" id="academic_year" name="academic_year" required>
                            <option value="">Select Academic Year</option>
                            <?php 
                            $currentYear = date('Y');
                            for ($i = 0; $i < 4; $i++) {
                                $year = $currentYear + $i;
                                echo "<option value=\"$year\">$year</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select an academic year.
                        </div>
                    </div>

                    <!-- Due Date -->
                    <div class="col-md-6">
                        <label for="due_date" class="form-label">Due Date *</label>
                        <input type="date" 
                               class="form-control" 
                               id="due_date" 
                               name="due_date" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a due date.
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"></textarea>
                    </div>

                    <!-- Apply to All Students -->
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="apply_to_all" 
                                   name="apply_to_all" 
                                   checked>
                            <label class="form-check-label" for="apply_to_all">
                                Apply this fee to all active students
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Fee
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

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
