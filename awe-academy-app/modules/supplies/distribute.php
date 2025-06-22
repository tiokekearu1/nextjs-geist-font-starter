<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'supply_officer'])) {
    header("Location: /index.php");
    exit;
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

$error = '';
$success = '';
$supply = null;

// Get supply ID from URL
$supplyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$supplyId) {
    header("Location: index.php");
    exit;
}

try {
    // Fetch supply details
    $stmt = $pdo->prepare("SELECT * FROM supplies WHERE id = ?");
    $stmt->execute([$supplyId]);
    $supply = $stmt->fetch();

    if (!$supply) {
        header("Location: index.php");
        exit;
    }

    // Fetch active students for dropdown
    $stmt = $pdo->prepare("
        SELECT id, student_number, first_name, last_name, class_year 
        FROM students 
        WHERE status = 'active' 
        ORDER BY last_name, first_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching supply details: " . $e->getMessage());
    $error = 'An error occurred while fetching supply information';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        // Validate and sanitize input
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $distributionDate = filter_input(INPUT_POST, 'distribution_date', FILTER_SANITIZE_STRING);
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (!$studentId || !$quantity || !$distributionDate) {
            $error = 'Please fill in all required fields';
        } elseif ($quantity <= 0) {
            $error = 'Quantity must be greater than zero';
        } elseif ($quantity > $supply['quantity_available']) {
            $error = 'Requested quantity exceeds available stock';
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Record distribution
                $stmt = $pdo->prepare("
                    INSERT INTO supply_distributions (
                        supply_id, student_id, quantity, distribution_date,
                        distributed_by, notes
                    ) VALUES (
                        :supply_id, :student_id, :quantity, :distribution_date,
                        :distributed_by, :notes
                    )
                ");

                $stmt->execute([
                    'supply_id' => $supplyId,
                    'student_id' => $studentId,
                    'quantity' => $quantity,
                    'distribution_date' => $distributionDate,
                    'distributed_by' => $_SESSION['user_id'],
                    'notes' => $notes
                ]);

                // Update supply quantity
                $stmt = $pdo->prepare("
                    UPDATE supplies 
                    SET quantity_available = quantity_available - :quantity
                    WHERE id = :id
                ");

                $stmt->execute([
                    'quantity' => $quantity,
                    'id' => $supplyId
                ]);

                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, details)
                    VALUES (:user_id, 'supply_distributed', :details)
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'details' => "Distributed {$quantity} {$supply['unit']} of {$supply['name']}"
                ]);

                // Commit transaction
                $pdo->commit();

                $success = 'Supply has been distributed successfully';
                
                // Refresh supply data
                $stmt = $pdo->prepare("SELECT * FROM supplies WHERE id = ?");
                $stmt->execute([$supplyId]);
                $supply = $stmt->fetch();

            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Error distributing supply: " . $e->getMessage());
                $error = 'An error occurred while distributing the supply';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Distribute Supply</h2>
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

    <div class="row">
        <!-- Supply Information -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Supply Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Name:</th>
                            <td><?php echo htmlspecialchars($supply['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Available:</th>
                            <td>
                                <?php echo number_format($supply['quantity_available']); ?>
                                <?php echo htmlspecialchars($supply['unit']); ?>
                            </td>
                        </tr>
                        <?php if ($supply['description']): ?>
                            <tr>
                                <th>Description:</th>
                                <td><?php echo nl2br(htmlspecialchars($supply['description'])); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Distribution Form -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Distribution Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                        <div class="row g-3">
                            <!-- Student -->
                            <div class="col-md-8">
                                <label for="student_id" class="form-label">Student *</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php 
                                            echo htmlspecialchars(
                                                $student['last_name'] . ', ' . 
                                                $student['first_name'] . ' (' . 
                                                $student['student_number'] . ')'
                                            ); 
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a student.
                                </div>
                            </div>

                            <!-- Distribution Date -->
                            <div class="col-md-4">
                                <label for="distribution_date" class="form-label">Distribution Date *</label>
                                <input type="date" 
                                       class="form-control" 
                                       id="distribution_date" 
                                       name="distribution_date" 
                                       value="<?php echo date('Y-m-d'); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Please provide a distribution date.
                                </div>
                            </div>

                            <!-- Quantity -->
                            <div class="col-md-6">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="quantity" 
                                       name="quantity" 
                                       min="1" 
                                       max="<?php echo $supply['quantity_available']; ?>"
                                       required>
                                <div class="form-text">
                                    Maximum available: <?php echo number_format($supply['quantity_available']); ?>
                                </div>
                                <div class="invalid-feedback">
                                    Please provide a valid quantity.
                                </div>
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
                                    <i class="fas fa-share-alt me-2"></i>Distribute Supply
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
