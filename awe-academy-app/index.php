<?php
require_once __DIR__ . '/includes/auth.php';

// Redirect to login if not authenticated
if (!isAuthenticated()) {
    header("Location: /modules/users/login.php");
    exit;
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Welcome to AWE Academy Management System</h2>
                    <p class="card-text">Select a module from the navigation menu above to get started.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'student_officer'): ?>
        <!-- Students Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-users me-2"></i>
                        Students
                    </h5>
                    <p class="card-text">Manage student records, enrollments, and academic information.</p>
                    <a href="/modules/students/index.php" class="btn btn-primary">Access Students</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'finance_officer'): ?>
        <!-- Fees Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        Fees Management
                    </h5>
                    <p class="card-text">Handle student fees, payments, and generate receipts.</p>
                    <a href="/modules/fees/index.php" class="btn btn-primary">Access Fees</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'supply_officer'): ?>
        <!-- Supplies Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-box me-2"></i>
                        Supplies
                    </h5>
                    <p class="card-text">Manage and track school supplies distribution.</p>
                    <a href="/modules/supplies/index.php" class="btn btn-primary">Access Supplies</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <!-- Reports Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-chart-bar me-2"></i>
                        Reports
                    </h5>
                    <p class="card-text">View analytics, generate reports, and export data.</p>
                    <a href="/modules/reports/index.php" class="btn btn-primary">Access Reports</a>
                </div>
            </div>
        </div>

        <!-- Backup Card -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-database me-2"></i>
                        Backup & Restore
                    </h5>
                    <p class="card-text">Manage database backups and system restoration.</p>
                    <a href="/backup_restore/backup.php" class="btn btn-primary">Access Backup</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>
