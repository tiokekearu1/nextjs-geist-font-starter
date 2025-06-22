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

// Fetch supply data
try {
    $stmt = $pdo->prepare("SELECT * FROM supplies WHERE id = ?");
    $stmt->execute([$supplyId]);
    $supply = $stmt->fetch();

    if (!$supply) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching supply: " . $e->getMessage());
    $error = 'An error occurred while fetching supply data';
}

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

                // Update supply record
                $stmt = $pdo->prepare("
                    UPDATE supplies SET 
                        name = :name,
                        description = :description,
                        quantity_available = :quantity,
                        unit = :unit
                    WHERE id = :id
                ");

                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'id' => $supplyId
                ]);

                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, details)
                    VALUES (:user_id, 'supply_updated', :details)
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'details' => "Updated supply ID: $supplyId"
                ]);

                // Commit transaction
                $pdo->commit();

                $success = 'Supply has been updated successfully';
                
                // Refresh supply data
                $stmt = $pdo->prepare("SELECT * FROM supplies WHERE id = ?");
                $stmt->execute([$supplyId]);
                $supply = $stmt->fetch();

            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Error updating supply: " . $e->getMessage());
                $error = 'An error occurred while updating the supply';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Edit Supply</h2>
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
                               value="<?php echo htmlspecialchars($supply['name']); ?>"
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
                            <?php
                            $units = ['pieces', 'sets', 'boxes', 'packs', 'reams', 'books', 'pairs'];
                            foreach ($units as $unitOption) {
                                $selected = ($supply['unit'] === $unitOption) ? 'selected' : '';
                                echo "<option value=\"$unitOption\" $selected>" . 
                                     ucfirst($unitOption) . 
                                     "</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a unit.
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Current Quantity *</label>
                        <input type="number" 
                               class="form-control" 
                               id="quantity" 
                               name="quantity" 
                               min="0" 
                               value="<?php echo htmlspecialchars($supply['quantity_available']); ?>"
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
                                  rows="3"><?php echo htmlspecialchars($supply['description']); ?></textarea>
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
