<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

try {
    // Get total number of active students
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM students 
        WHERE status = 'active'
    ");
    $activeStudents = $stmt->fetch()['count'];

    // Get total unpaid fees
    $stmt = $pdo->query("
        SELECT 
            SUM(f.amount - sf.amount_paid) as total_unpaid
        FROM student_fees sf
        JOIN fees f ON sf.fee_id = f.id
        WHERE sf.payment_status != 'paid'
    ");
    $totalUnpaidFees = $stmt->fetch()['total_unpaid'] ?? 0;

    // Get recent payments
    $stmt = $pdo->query("
        SELECT 
            p.*,
            s.student_number,
            s.first_name,
            s.last_name,
            f.name as fee_name
        FROM payments p
        JOIN student_fees sf ON p.student_fee_id = sf.id
        JOIN students s ON sf.student_id = s.id
        JOIN fees f ON sf.fee_id = f.id
        ORDER BY p.payment_date DESC, p.created_at DESC
        LIMIT 5
    ");
    $recentPayments = $stmt->fetchAll();

    // Get low stock supplies
    $stmt = $pdo->query("
        SELECT *
        FROM supplies
        WHERE quantity_available <= 10
        ORDER BY quantity_available ASC
        LIMIT 5
    ");
    $lowStockSupplies = $stmt->fetchAll();

    // Get recent supply distributions
    $stmt = $pdo->query("
        SELECT 
            sd.*,
            s.student_number,
            s.first_name,
            s.last_name,
            sup.name as supply_name,
            u.full_name as distributor_name
        FROM supply_distributions sd
        JOIN students s ON sd.student_id = s.id
        JOIN supplies sup ON sd.supply_id = sup.id
        JOIN users u ON sd.distributed_by = u.id
        ORDER BY sd.distribution_date DESC, sd.created_at DESC
        LIMIT 5
    ");
    $recentDistributions = $stmt->fetchAll();

    // Get system activity logs
    $stmt = $pdo->query("
        SELECT 
            l.*,
            u.full_name
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $activityLogs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Dashboard</h2>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <!-- Active Students -->
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Active Students</h6>
                    <h3 class="card-title mb-0"><?php echo number_format($activeStudents); ?></h3>
                    <a href="/modules/students/index.php" class="text-white text-decoration-none">
                        <small>View all students <i class="fas fa-arrow-right ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <!-- Unpaid Fees -->
        <div class="col-md-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Unpaid Fees</h6>
                    <h3 class="card-title mb-0">$<?php echo number_format($totalUnpaidFees, 2); ?></h3>
                    <a href="/modules/fees/index.php" class="text-white text-decoration-none">
                        <small>View all fees <i class="fas fa-arrow-right ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <!-- Low Stock Supplies -->
        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Low Stock Items</h6>
                    <h3 class="card-title mb-0"><?php echo count($lowStockSupplies); ?></h3>
                    <a href="/modules/supplies/index.php" class="text-dark text-decoration-none">
                        <small>View all supplies <i class="fas fa-arrow-right ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-3">Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="/modules/students/create.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-user-plus me-2"></i>New Student
                        </a>
                        <a href="/modules/fees/create.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-dollar-sign me-2"></i>Add Fee
                        </a>
                        <a href="/modules/supplies/create.php" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-box me-2"></i>Add Supply
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Payments -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Payments</h5>
                    <a href="/modules/fees/index.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPayments)): ?>
                        <p class="text-muted text-center py-3">No recent payments</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Fee</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($payment['student_number']); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['fee_name']); ?></td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Supply Distributions -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Supply Distributions</h5>
                    <a href="/modules/supplies/index.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentDistributions)): ?>
                        <p class="text-muted text-center py-3">No recent distributions</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Supply</th>
                                        <th>Quantity</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentDistributions as $dist): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($dist['first_name'] . ' ' . $dist['last_name']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($dist['student_number']); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($dist['supply_name']); ?></td>
                                            <td><?php echo number_format($dist['quantity']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($dist['distribution_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStockSupplies)): ?>
            <div class="col-md-6 mb-4">
                <div class="card border-warning h-100">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">Low Stock Alert</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Supply</th>
                                        <th>Available</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockSupplies as $supply): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($supply['name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $supply['quantity_available'] == 0 ? 'danger' : 'warning';
                                                ?>">
                                                    <?php 
                                                    echo number_format($supply['quantity_available']) . ' ' . 
                                                         htmlspecialchars($supply['unit']); 
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="/modules/supplies/edit.php?id=<?php echo $supply['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    Update Stock
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activityLogs)): ?>
                        <p class="text-muted text-center py-3">No recent activity</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-content">
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                        </small>
                                        <p class="mb-0">
                                            <?php echo htmlspecialchars($log['details']); ?>
                                            <br>
                                            <small class="text-muted">
                                                by <?php echo htmlspecialchars($log['full_name']); ?>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline-item {
    position: relative;
    padding-left: 20px;
    padding-bottom: 20px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item:after {
    content: '';
    position: absolute;
    left: -4px;
    top: 8px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #007bff;
}

.timeline-content {
    padding-left: 15px;
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
