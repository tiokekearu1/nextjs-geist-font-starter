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
$fee = null;

// Get fee ID from URL
$feeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$feeId) {
    header("Location: index.php");
    exit;
}

// Fetch fee data
try {
    $stmt = $pdo->prepare("SELECT * FROM fees WHERE id = ?");
    $stmt->execute([$feeId]);
    $fee = $stmt->fetch();

    if (!$fee) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching fee: " . $e->getMessage());
    $error = 'An error occurred while fetching fee data';
}

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

        // Validate required fields
        if (!$name || !$amount || !$academicYear || !$dueDate) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Update fee record
                $stmt = $pdo->prepare("
                    UPDATE fees SET 
                        name = :name,
                        amount = :amount,
                        description = :description,
                        academic_year = :academic_year,
                        due_date = :due_date
                    WHERE id = :id
                ");

                $stmt->execute([
                    'name' => $name,
                    'amount' => $amount,
                    'description' => $description,
                    'academic_year' => $academicYear,
                    'due_date' => $dueDate,
                    'id' => $feeId
                ]);

                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, details)
                    VALUES (:user_id, 'fee_updated', :details)
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'details' => "Updated fee ID: $feeId"
                ]);

                // Commit transaction
                $pdo->commit();

                $success = 'Fee has been updated successfully';
                
                // Refresh fee data
                $stmt = $pdo->prepare("SELECT * FROM fees WHERE id = ?");
                $stmt->execute([$feeId]);
                $fee = $stmt->fetch();

            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Error updating fee: " . $e->getMessage());
                $error = 'An error occurred while updating the fee';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Edit Fee</h2>
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
                               value="<?php echo htmlspecialchars($fee['name']); ?>"
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
                                   value="<?php echo htmlspecialchars($fee['amount']); ?>"
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
                            <?php 
                            $currentYear = date('Y');
                            for ($i = 0; $i < 4; $i++) {
                                $year = $currentYear + $i;
                                $selected = ($fee['academic_year'] == $year) ? 'selected' : '';
                                echo "<option value=\"$year\" $selected>$year</option>";
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
                               value="<?php echo htmlspecialchars($fee['due_date']); ?>"
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
                                  rows="3"><?php echo htmlspecialchars($fee['description']); ?></textarea>
                    </div>

                    <div class="col-12">
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
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
