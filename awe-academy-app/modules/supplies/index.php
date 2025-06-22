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

// Get any messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Handle search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    // Prepare the query
    $query = "
        SELECT 
            s.*,
            COUNT(DISTINCT sd.id) as total_distributions,
            SUM(sd.quantity) as total_distributed
        FROM supplies s
        LEFT JOIN supply_distributions sd ON s.id = sd.supply_id
        WHERE 1=1
    ";
    $params = [];

    if ($search) {
        $query .= " AND (s.name LIKE :search OR s.description LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($status === 'out_of_stock') {
        $query .= " AND s.quantity_available = 0";
    } elseif ($status === 'low_stock') {
        $query .= " AND s.quantity_available > 0 AND s.quantity_available <= 10";
    } elseif ($status === 'in_stock') {
        $query .= " AND s.quantity_available > 10";
    }

    $query .= " GROUP BY s.id ORDER BY s.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $supplies = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching supplies: " . $e->getMessage());
    $error = "An error occurred while fetching the supplies list.";
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Supply Management</h2>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Supply
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
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               placeholder="Search supplies..." 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Stock Status</option>
                        <option value="in_stock" <?php echo $status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low_stock" <?php echo $status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out_of_stock" <?php echo $status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Supplies Overview Cards -->
    <div class="row g-4 mb-4">
        <?php
        $totalSupplies = count($supplies);
        $outOfStock = count(array_filter($supplies, fn($s) => $s['quantity_available'] == 0));
        $lowStock = count(array_filter($supplies, fn($s) => $s['quantity_available'] > 0 && $s['quantity_available'] <= 10));
        $totalItems = array_sum(array_column($supplies, 'quantity_available'));
        ?>
        
        <!-- Total Supplies -->
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Supplies</h6>
                    <h3 class="card-title mb-0"><?php echo $totalSupplies; ?></h3>
                    <small>Different items in inventory</small>
                </div>
            </div>
        </div>

        <!-- Total Items -->
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Items</h6>
                    <h3 class="card-title mb-0"><?php echo number_format($totalItems); ?></h3>
                    <small>Items available in stock</small>
                </div>
            </div>
        </div>

        <!-- Low Stock -->
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Low Stock</h6>
                    <h3 class="card-title mb-0"><?php echo $lowStock; ?></h3>
                    <small>Items need restock</small>
                </div>
            </div>
        </div>

        <!-- Out of Stock -->
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Out of Stock</h6>
                    <h3 class="card-title mb-0"><?php echo $outOfStock; ?></h3>
                    <small>Items completely depleted</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Supplies Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Supply Name</th>
                            <th>Description</th>
                            <th>Available Quantity</th>
                            <th>Unit</th>
                            <th>Distribution History</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($supplies)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    No supplies found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($supplies as $supply): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supply['name']); ?></td>
                                    <td>
                                        <?php 
                                        $description = $supply['description'] ?: 'No description available';
                                        echo strlen($description) > 50 ? 
                                             htmlspecialchars(substr($description, 0, 50) . '...') : 
                                             htmlspecialchars($description);
                                        ?>
                                    </td>
                                    <td><?php echo number_format($supply['quantity_available']); ?></td>
                                    <td><?php echo htmlspecialchars($supply['unit']); ?></td>
                                    <td>
                                        <?php if ($supply['total_distributions']): ?>
                                            <?php echo number_format($supply['total_distributed']); ?> items
                                            distributed in
                                            <?php echo $supply['total_distributions']; ?> transactions
                                        <?php else: ?>
                                            No distributions yet
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = match(true) {
                                            $supply['quantity_available'] == 0 => 'danger',
                                            $supply['quantity_available'] <= 10 => 'warning',
                                            default => 'success'
                                        };
                                        $statusText = match(true) {
                                            $supply['quantity_available'] == 0 => 'Out of Stock',
                                            $supply['quantity_available'] <= 10 => 'Low Stock',
                                            default => 'In Stock'
                                        };
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="distribute.php?id=<?php echo $supply['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Distribute Supply">
                                                <i class="fas fa-share-alt"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $supply['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete"
                                                        onclick="confirmDelete(<?php echo $supply['id']; ?>)">
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
                Are you sure you want to delete this supply? This will also remove all distribution records.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="delete.php" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="supply_id" id="deleteSupplyId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(supplyId) {
    document.getElementById('deleteSupplyId').value = supplyId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
