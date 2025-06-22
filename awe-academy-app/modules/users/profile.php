<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/header.php';

$error = '';
$success = '';
$user = null;

try {
    // Fetch user details
    $stmt = $pdo->prepare("
        SELECT * FROM users WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: /index.php");
        exit;
    }

} catch (PDOException $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $error = 'An error occurred while fetching user information';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            // Validate and sanitize input
            $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

            // Validate required fields
            if (!$fullName || !$email) {
                $error = 'Please fill in all required fields';
            } else {
                try {
                    // Check if email is already taken by another user
                    $stmt = $pdo->prepare("
                        SELECT id FROM users 
                        WHERE email = ? AND id != ?
                    ");
                    $stmt->execute([$email, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        $error = 'Email address is already in use';
                    } else {
                        // Update user profile
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                full_name = :full_name,
                                email = :email,
                                phone = :phone
                            WHERE id = :id
                        ");

                        $stmt->execute([
                            'full_name' => $fullName,
                            'email' => $email,
                            'phone' => $phone,
                            'id' => $_SESSION['user_id']
                        ]);

                        $success = 'Profile has been updated successfully';
                        
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                    }
                } catch (PDOException $e) {
                    error_log("Error updating profile: " . $e->getMessage());
                    $error = 'An error occurred while updating your profile';
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!$currentPassword || !$newPassword || !$confirmPassword) {
                $error = 'Please fill in all password fields';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            } elseif (strlen($newPassword) < 8) {
                $error = 'New password must be at least 8 characters long';
            } else {
                try {
                    // Verify current password
                    if (!password_verify($currentPassword, $user['password'])) {
                        $error = 'Current password is incorrect';
                    } else {
                        // Update password
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                password = :password
                            WHERE id = :id
                        ");

                        $stmt->execute([
                            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                            'id' => $_SESSION['user_id']
                        ]);

                        $success = 'Password has been changed successfully';
                    }
                } catch (PDOException $e) {
                    error_log("Error changing password: " . $e->getMessage());
                    $error = 'An error occurred while changing your password';
                }
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h2>My Profile</h2>
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

    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>"
                                   disabled>
                            <div class="form-text">Username cannot be changed</div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="full_name" 
                                   name="full_name"
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Please provide your full name.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email"
                                   value="<?php echo htmlspecialchars($user['email']); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone"
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="role" 
                                   value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>"
                                   disabled>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="current_password" 
                                   name="current_password"
                                   required>
                            <div class="invalid-feedback">
                                Please enter your current password.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="new_password" 
                                   name="new_password"
                                   minlength="8"
                                   required>
                            <div class="form-text">
                                Password must be at least 8 characters long
                            </div>
                            <div class="invalid-feedback">
                                Please enter a new password (minimum 8 characters).
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password"
                                   required>
                            <div class="invalid-feedback">
                                Please confirm your new password.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Activity -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Account Activity</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->prepare("
                            SELECT * FROM system_logs
                            WHERE user_id = ?
                            ORDER BY created_at DESC
                            LIMIT 10
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $activities = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        error_log("Error fetching activity logs: " . $e->getMessage());
                        $activities = [];
                    }
                    ?>

                    <?php if (empty($activities)): ?>
                        <p class="text-muted text-center py-3">No recent activity</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td>
                                                <?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                            <td>
                                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
