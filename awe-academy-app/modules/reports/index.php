<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'finance_officer'])) {
    header("Location: /index.php");
    exit;
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Get date range parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : '';

try {
    // Initialize report data arrays
    $feeCollections = [];
    $supplyDistributions = [];
    $studentStats = [];
    $paymentTrends = [];

    if ($reportType) {
        switch ($reportType) {
            case 'fee_collections':
                // Get fee collection summary
                $stmt = $pdo->prepare("
                    SELECT 
                        f.name as fee_name,
                        f.amount as total_amount,
                        COUNT(DISTINCT sf.student_id) as total_students,
                        SUM(sf.amount_paid) as total_collected,
                        COUNT(CASE WHEN sf.payment_status = 'paid' THEN 1 END) as fully_paid,
                        COUNT(CASE WHEN sf.payment_status = 'partial' THEN 1 END) as partially_paid,
                        COUNT(CASE WHEN sf.payment_status = 'unpaid' THEN 1 END) as unpaid
                    FROM fees f
                    LEFT JOIN student_fees sf ON f.id = sf.fee_id
                    WHERE f.due_date BETWEEN :start_date AND :end_date
                    GROUP BY f.id, f.name, f.amount
                    ORDER BY f.due_date DESC
                ");
                $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
                $feeCollections = $stmt->fetchAll();
                break;

            case 'supply_distributions':
                // Get supply distribution summary
                $stmt = $pdo->prepare("
                    SELECT 
                        s.name as supply_name,
                        s.unit,
                        COUNT(DISTINCT sd.student_id) as total_students,
                        SUM(sd.quantity) as total_distributed
                    FROM supplies s
                    LEFT JOIN supply_distributions sd ON s.id = sd.supply_id
                    WHERE sd.distribution_date BETWEEN :start_date AND :end_date
                    GROUP BY s.id, s.name, s.unit
                    ORDER BY total_distributed DESC
                ");
                $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
                $supplyDistributions = $stmt->fetchAll();
                break;

            case 'student_stats':
                // Get student statistics
                $stmt = $pdo->prepare("
                    SELECT 
                        s.class_year,
                        COUNT(*) as total_students,
                        COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_students,
                        COUNT(CASE WHEN s.status = 'inactive' THEN 1 END) as inactive_students,
                        COUNT(CASE WHEN s.status = 'graduated' THEN 1 END) as graduated_students
                    FROM students s
                    GROUP BY s.class_year
                    ORDER BY s.class_year DESC
                ");
                $stmt->execute();
                $studentStats = $stmt->fetchAll();
                break;

            case 'payment_trends':
                // Get payment trends by month
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        COUNT(DISTINCT p.student_fee_id) as total_payments,
                        SUM(p.amount) as total_amount
                    FROM payments p
                    WHERE p.payment_date BETWEEN :start_date AND :end_date
                    GROUP BY month
                    ORDER BY month DESC
                ");
                $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
                $paymentTrends = $stmt->fetchAll();
                break;
        }
    }

} catch (PDOException $e) {
    error_log("Error generating report: " . $e->getMessage());
    $error = "An error occurred while generating the report.";
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Reports</h2>
        </div>
    </div>

    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select name="report_type" id="report_type" class="form-select" required>
                        <option value="">Select Report Type</option>
                        <option value="fee_collections" <?php echo $reportType === 'fee_collections' ? 'selected' : ''; ?>>
                            Fee Collections
                        </option>
                        <option value="supply_distributions" <?php echo $reportType === 'supply_distributions' ? 'selected' : ''; ?>>
                            Supply Distributions
                        </option>
                        <option value="student_stats" <?php echo $reportType === 'student_stats' ? 'selected' : ''; ?>>
                            Student Statistics
                        </option>
                        <option value="payment_trends" <?php echo $reportType === 'payment_trends' ? 'selected' : ''; ?>>
                            Payment Trends
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" 
                           class="form-control" 
                           id="start_date" 
                           name="start_date"
                           value="<?php echo $startDate; ?>">
                </div>

                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" 
                           class="form-control" 
                           id="end_date" 
                           name="end_date"
                           value="<?php echo $endDate; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($reportType === 'fee_collections' && !empty($feeCollections)): ?>
        <!-- Fee Collections Report -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Fee Collections Report</h5>
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fee Name</th>
                                <th>Total Amount</th>
                                <th>Students</th>
                                <th>Collected</th>
                                <th>Collection Rate</th>
                                <th>Payment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeCollections as $fee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                    <td>$<?php echo number_format($fee['total_amount'], 2); ?></td>
                                    <td><?php echo number_format($fee['total_students']); ?></td>
                                    <td>$<?php echo number_format($fee['total_collected'], 2); ?></td>
                                    <td>
                                        <?php
                                        $expectedTotal = $fee['total_amount'] * $fee['total_students'];
                                        $collectionRate = $expectedTotal > 0 ? 
                                            ($fee['total_collected'] / $expectedTotal) * 100 : 0;
                                        echo number_format($collectionRate, 1) . '%';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <?php
                                            $paidPercent = ($fee['fully_paid'] / $fee['total_students']) * 100;
                                            $partialPercent = ($fee['partially_paid'] / $fee['total_students']) * 100;
                                            $unpaidPercent = ($fee['unpaid'] / $fee['total_students']) * 100;
                                            ?>
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $paidPercent; ?>%"
                                                 title="Fully Paid: <?php echo $fee['fully_paid']; ?>">
                                                <?php echo $fee['fully_paid']; ?>
                                            </div>
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?php echo $partialPercent; ?>%"
                                                 title="Partially Paid: <?php echo $fee['partially_paid']; ?>">
                                                <?php echo $fee['partially_paid']; ?>
                                            </div>
                                            <div class="progress-bar bg-danger" 
                                                 style="width: <?php echo $unpaidPercent; ?>%"
                                                 title="Unpaid: <?php echo $fee['unpaid']; ?>">
                                                <?php echo $fee['unpaid']; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($reportType === 'supply_distributions' && !empty($supplyDistributions)): ?>
        <!-- Supply Distributions Report -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Supply Distributions Report</h5>
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Supply Name</th>
                                <th>Total Distributed</th>
                                <th>Students Received</th>
                                <th>Average per Student</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supplyDistributions as $supply): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supply['supply_name']); ?></td>
                                    <td>
                                        <?php 
                                        echo number_format($supply['total_distributed']) . ' ' . 
                                             htmlspecialchars($supply['unit']); 
                                        ?>
                                    </td>
                                    <td><?php echo number_format($supply['total_students']); ?></td>
                                    <td>
                                        <?php 
                                        $avg = $supply['total_students'] > 0 ? 
                                            $supply['total_distributed'] / $supply['total_students'] : 0;
                                        echo number_format($avg, 1) . ' ' . htmlspecialchars($supply['unit']); 
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($reportType === 'student_stats' && !empty($studentStats)): ?>
        <!-- Student Statistics Report -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Student Statistics Report</h5>
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Class Year</th>
                                <th>Total Students</th>
                                <th>Active</th>
                                <th>Inactive</th>
                                <th>Graduated</th>
                                <th>Status Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentStats as $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['class_year']); ?></td>
                                    <td><?php echo number_format($stat['total_students']); ?></td>
                                    <td><?php echo number_format($stat['active_students']); ?></td>
                                    <td><?php echo number_format($stat['inactive_students']); ?></td>
                                    <td><?php echo number_format($stat['graduated_students']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <?php
                                            $activePercent = ($stat['active_students'] / $stat['total_students']) * 100;
                                            $inactivePercent = ($stat['inactive_students'] / $stat['total_students']) * 100;
                                            $graduatedPercent = ($stat['graduated_students'] / $stat['total_students']) * 100;
                                            ?>
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $activePercent; ?>%"
                                                 title="Active: <?php echo $stat['active_students']; ?>">
                                                <?php echo $stat['active_students']; ?>
                                            </div>
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?php echo $inactivePercent; ?>%"
                                                 title="Inactive: <?php echo $stat['inactive_students']; ?>">
                                                <?php echo $stat['inactive_students']; ?>
                                            </div>
                                            <div class="progress-bar bg-info" 
                                                 style="width: <?php echo $graduatedPercent; ?>%"
                                                 title="Graduated: <?php echo $stat['graduated_students']; ?>">
                                                <?php echo $stat['graduated_students']; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($reportType === 'payment_trends' && !empty($paymentTrends)): ?>
        <!-- Payment Trends Report -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Payment Trends Report</h5>
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Payments</th>
                                <th>Total Amount</th>
                                <th>Average Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentTrends as $trend): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                                    <td><?php echo number_format($trend['total_payments']); ?></td>
                                    <td>$<?php echo number_format($trend['total_amount'], 2); ?></td>
                                    <td>
                                        $<?php 
                                        $avg = $trend['total_payments'] > 0 ? 
                                            $trend['total_amount'] / $trend['total_payments'] : 0;
                                        echo number_format($avg, 2); 
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
    }
    .card-header {
        background: none !important;
        border: none !important;
    }
    .container {
        width: 100% !important;
        max-width: none !important;
    }
}
</style>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
