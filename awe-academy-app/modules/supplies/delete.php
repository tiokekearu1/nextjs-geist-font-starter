<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has admin permission
if ($_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid request';
    header("Location: index.php");
    exit;
}

// Get supply ID
$supplyId = filter_input(INPUT_POST, 'supply_id', FILTER_VALIDATE_INT);

if (!$supplyId) {
    $_SESSION['error'] = 'Invalid supply ID';
    header("Location: index.php");
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if supply exists and get its info for logging
    $stmt = $pdo->prepare("SELECT name FROM supplies WHERE id = ?");
    $stmt->execute([$supplyId]);
    $supply = $stmt->fetch();

    if (!$supply) {
        throw new Exception('Supply not found');
    }

    // Delete all distribution records first (due to foreign key constraints)
    $stmt = $pdo->prepare("DELETE FROM supply_distributions WHERE supply_id = ?");
    $stmt->execute([$supplyId]);

    // Delete the supply record
    $stmt = $pdo->prepare("DELETE FROM supplies WHERE id = ?");
    $stmt->execute([$supplyId]);

    // Log the deletion
    $stmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, action, details) 
        VALUES (:user_id, 'supply_deleted', :details)
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'details' => "Deleted supply: " . $supply['name']
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = 'Supply and all associated records have been deleted successfully';

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error deleting supply: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while deleting the supply';
}

// Redirect back to supply list
header("Location: index.php");
exit;
