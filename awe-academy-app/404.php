<?php
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-75">
        <div class="col-md-6 text-center">
            <div class="error-page">
                <h1 class="display-1 fw-bold text-primary">404</h1>
                <h2 class="mb-4">Page Not Found</h2>
                <p class="text-muted mb-4">
                    The page you are looking for might have been removed, had its name changed,
                    or is temporarily unavailable.
                </p>
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>Go to Dashboard
                    </a>
                    <button onclick="history.back()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Go Back
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.min-vh-75 {
    min-height: 75vh;
}

.error-page {
    padding: 40px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.error-page h1 {
    font-size: 120px;
    margin-bottom: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

.error-page h2 {
    color: #333;
    font-weight: 500;
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
