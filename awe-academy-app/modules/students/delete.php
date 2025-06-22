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

// Get student ID
$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

if (!$studentId) {
    $_SESSION['error'] = 'Invalid student ID';
    header("Location: index.php");
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if student exists and get their info for logging
    $stmt = $pdo->prepare("SELECT student_number, first_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception('Student not found');
    }

    // Delete student record
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$studentId]);

    // Log the deletion
    $details = sprintf(
        "Deleted student: %s (%s %s)", 
        $student['student_number'],
        $student['first_name'],
        $student['last_name']
    );

    $stmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, action, details) 
        VALUES (:user_id, 'student_deleted', :details)
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'details' => $details
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = 'Student has been deleted successfully';

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error deleting student: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while deleting the student';
}

// Redirect back to student list
header("Location: index.php");
exit;
