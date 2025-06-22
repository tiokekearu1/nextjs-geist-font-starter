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

// Get fee ID
$feeId = filter_input(INPUT_POST, 'fee_id', FILTER_VALIDATE_INT);

if (!$feeId) {
    $_SESSION['error'] = 'Invalid fee ID';
    header("Location: index.php");
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if fee exists and get its info for logging
    $stmt = $pdo->prepare("SELECT name FROM fees WHERE id = ?");
    $stmt->execute([$feeId]);
    $fee = $stmt->fetch();

    if (!$fee) {
        throw new Exception('Fee not found');
    }

    // Delete all associated student fees first (due to foreign key constraints)
    $stmt = $pdo->prepare("DELETE FROM student_fees WHERE fee_id = ?");
    $stmt->execute([$feeId]);

    // Delete all associated payments
    $stmt = $pdo->prepare("
        DELETE FROM payments 
        WHERE student_fee_id IN (
            SELECT id FROM student_fees WHERE fee_id = ?
        )
    ");
    $stmt->execute([$feeId]);

    // Delete the fee record
    $stmt = $pdo->prepare("DELETE FROM fees WHERE id = ?");
    $stmt->execute([$feeId]);

    // Log the deletion
    $stmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, action, details) 
        VALUES (:user_id, 'fee_deleted', :details)
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'details' => "Deleted fee: " . $fee['name']
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = 'Fee and all associated records have been deleted successfully';

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error deleting fee: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while deleting the fee';
}

// Redirect back to fee list
header("Location: index.php");
exit;
