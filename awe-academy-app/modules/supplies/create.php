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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $unit = filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (!$name || !$unit || !is_numeric($quantity)) {
            $error = 'Please fill in all required fields';
        } elseif ($quantity < 0) {
            $error = 'Quantity cannot be negative';
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Insert new supply
                $stmt = $pdo->prepare("
                    INSERT INTO supplies (name, description, quantity_available, unit)
                    VALUES (:name, :description, :quantity, :unit)
                ");

                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit' => $unit
                ]);

                $supplyId = $pdo->lastInsertId();

                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, details)
                    VALUES (:user_id, 'supply_created', :details)
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'details' => "Created supply: $name with initial quantity of $quantity"
                ]);

                // Commit transaction
                $pdo->commit();

                $success = 'Supply has been added successfully';
                // Redirect to supply list after short delay
                header("refresh:2;url=index.php");

            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Error creating supply: " . $e->getMessage());
                $error = 'An error occurred while adding the supply';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Add New Supply</h2>
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
                    <!-- Supply Name -->
                    <div class="col-md-6">
                        <label for="name" class="form-label">Supply Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a supply name.
                        </div>
                    </div>

                    <!-- Unit -->
                    <div class="col-md-6">
                        <label for="unit" class="form-label">Unit *</label>
                        <select class="form-select" id="unit" name="unit" required>
                            <option value="">Select Unit</option>
                            <option value="pieces">Pieces</option>
                            <option value="sets">Sets</option>
                            <option value="boxes">Boxes</option>
                            <option value="packs">Packs</option>
                            <option value="reams">Reams</option>
                            <option value="books">Books</option>
                            <option value="pairs">Pairs</option>
                        </select>
                        <div class="invalid-feedback">
                            Please select a unit.
                        </div>
                    </div>

                    <!-- Initial Quantity -->
                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Initial Quantity *</label>
                        <input type="number" 
                               class="form-control" 
                               id="quantity" 
                               name="quantity" 
                               min="0" 
                               value="0" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a valid quantity.
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

                    <div class="col-12">
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Supply
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
