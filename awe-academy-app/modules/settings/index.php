<?php
require_once __DIR__ . '/../../includes/auth.php';

// Only admin can access settings
if ($_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

$error = '';
$success = '';

// Get current settings
try {
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, name");
    $settings = $stmt->fetchAll(PDO::FETCH_GROUP);
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $error = 'An error occurred while fetching settings';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Update settings
            $stmt = $pdo->prepare("
                UPDATE settings 
                SET value = :value, updated_at = NOW()
                WHERE id = :id
            ");

            foreach ($_POST['settings'] as $id => $value) {
                $stmt->execute([
                    'value' => $value,
                    'id' => $id
                ]);
            }

            // Log the action
            $stmt = $pdo->prepare("
                INSERT INTO system_logs (user_id, action, details)
                VALUES (:user_id, 'settings_updated', :details)
            ");
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'details' => "Updated system settings"
            ]);

            // Commit transaction
            $pdo->commit();

            $success = 'Settings have been updated successfully';

            // Refresh settings
            $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, name");
            $settings = $stmt->fetchAll(PDO::FETCH_GROUP);

        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error updating settings: " . $e->getMessage());
            $error = 'An error occurred while updating settings';
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h2>System Settings</h2>
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

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="row">
            <!-- School Settings -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">School Settings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($settings['school'])): ?>
                            <?php foreach ($settings['school'] as $setting): ?>
                                <div class="mb-3">
                                    <label for="setting_<?php echo $setting['id']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['display_name']); ?>
                                    </label>
                                    <?php if ($setting['type'] === 'text'): ?>
                                        <input type="text" 
                                               class="form-control" 
                                               id="setting_<?php echo $setting['id']; ?>"
                                               name="settings[<?php echo $setting['id']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php elseif ($setting['type'] === 'textarea'): ?>
                                        <textarea class="form-control" 
                                                  id="setting_<?php echo $setting['id']; ?>"
                                                  name="settings[<?php echo $setting['id']; ?>]"
                                                  rows="3"><?php echo htmlspecialchars($setting['value']); ?></textarea>
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <div class="form-text">
                                            <?php echo htmlspecialchars($setting['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">System Settings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($settings['system'])): ?>
                            <?php foreach ($settings['system'] as $setting): ?>
                                <div class="mb-3">
                                    <label for="setting_<?php echo $setting['id']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['display_name']); ?>
                                    </label>
                                    <?php if ($setting['type'] === 'text'): ?>
                                        <input type="text" 
                                               class="form-control" 
                                               id="setting_<?php echo $setting['id']; ?>"
                                               name="settings[<?php echo $setting['id']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php elseif ($setting['type'] === 'number'): ?>
                                        <input type="number" 
                                               class="form-control" 
                                               id="setting_<?php echo $setting['id']; ?>"
                                               name="settings[<?php echo $setting['id']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php elseif ($setting['type'] === 'boolean'): ?>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" 
                                                   class="form-check-input" 
                                                   id="setting_<?php echo $setting['id']; ?>"
                                                   name="settings[<?php echo $setting['id']; ?>]"
                                                   value="1"
                                                   <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <div class="form-text">
                                            <?php echo htmlspecialchars($setting['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Email Settings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($settings['email'])): ?>
                            <?php foreach ($settings['email'] as $setting): ?>
                                <div class="mb-3">
                                    <label for="setting_<?php echo $setting['id']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['display_name']); ?>
                                    </label>
                                    <?php if ($setting['type'] === 'text'): ?>
                                        <input type="text" 
                                               class="form-control" 
                                               id="setting_<?php echo $setting['id']; ?>"
                                               name="settings[<?php echo $setting['id']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php elseif ($setting['type'] === 'password'): ?>
                                        <input type="password" 
                                               class="form-control" 
                                               id="setting_<?php echo $setting['id']; ?>"
                                               name="settings[<?php echo $setting['id']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php elseif ($setting['type'] === 'number'): ?>
                                        <input type="number" 
                                               class="form-control" 
                                               id="setting_<?php echo $setting['id']; ?>"
                                               name="settings[<?php echo $setting['id']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <div class="form-text">
                                            <?php echo htmlspecialchars($setting['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($settings['notifications'])): ?>
                            <?php foreach ($settings['notifications'] as $setting): ?>
                                <div class="mb-3">
                                    <label for="setting_<?php echo $setting['id']; ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['display_name']); ?>
                                    </label>
                                    <?php if ($setting['type'] === 'boolean'): ?>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" 
                                                   class="form-check-input" 
                                                   id="setting_<?php echo $setting['id']; ?>"
                                                   name="settings[<?php echo $setting['id']; ?>]"
                                                   value="1"
                                                   <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <div class="form-text">
                                            <?php echo htmlspecialchars($setting['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <hr class="my-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
                <button type="reset" class="btn btn-outline-secondary">
                    <i class="fas fa-undo me-2"></i>Reset Changes
                </button>
            </div>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
