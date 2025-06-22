<?php
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'student_officer'])) {
    header("Location: /index.php");
    exit;
}

// Include header
require_once __DIR__ . '/../../includes/header.php';

$error = '';
$success = '';
$student = null;

// Get student ID from URL
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$studentId) {
    header("Location: index.php");
    exit;
}

// Fetch student data
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    $error = 'An error occurred while fetching student data';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        // Validate and sanitize input
        $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $dateOfBirth = filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING);
        $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $classYear = filter_input(INPUT_POST, 'class_year', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (!$firstName || !$lastName || !$dateOfBirth || !$gender || !$address || !$classYear || !$status) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                // Update student record
                $stmt = $pdo->prepare("
                    UPDATE students SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        date_of_birth = :date_of_birth,
                        gender = :gender,
                        address = :address,
                        phone = :phone,
                        email = :email,
                        class_year = :class_year,
                        status = :status
                    WHERE id = :id
                ");

                $stmt->execute([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => $dateOfBirth,
                    'gender' => $gender,
                    'address' => $address,
                    'phone' => $phone,
                    'email' => $email,
                    'class_year' => $classYear,
                    'status' => $status,
                    'id' => $studentId
                ]);

                // Log the action
                $stmt = $pdo->prepare("
                    INSERT INTO system_logs (user_id, action, details) 
                    VALUES (:user_id, 'student_updated', :details)
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'details' => "Updated student ID: $studentId"
                ]);

                $success = 'Student information updated successfully';
                
                // Refresh student data
                $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                $stmt->execute([$studentId]);
                $student = $stmt->fetch();
            } catch (PDOException $e) {
                error_log("Error updating student: " . $e->getMessage());
                $error = 'An error occurred while updating student information';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Edit Student Information</h2>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>
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

    <div class="card">
        <div class="card-body">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                <div class="row g-3">
                    <!-- Student Number (Read-only) -->
                    <div class="col-md-6">
                        <label for="student_number" class="form-label">Student Number</label>
                        <input type="text" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($student['student_number']); ?>" 
                               readonly>
                    </div>

                    <!-- Class Year -->
                    <div class="col-md-6">
                        <label for="class_year" class="form-label">Class Year *</label>
                        <select class="form-select" id="class_year" name="class_year" required>
                            <?php 
                            $currentYear = date('Y');
                            for ($i = 0; $i < 4; $i++) {
                                $year = $currentYear + $i;
                                $selected = ($student['class_year'] == $year) ? 'selected' : '';
                                echo "<option value=\"$year\" $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- First Name -->
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="first_name" 
                               name="first_name" 
                               value="<?php echo htmlspecialchars($student['first_name']); ?>" 
                               required>
                    </div>

                    <!-- Last Name -->
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="last_name" 
                               name="last_name" 
                               value="<?php echo htmlspecialchars($student['last_name']); ?>" 
                               required>
                    </div>

                    <!-- Date of Birth -->
                    <div class="col-md-6">
                        <label for="date_of_birth" class="form-label">Date of Birth *</label>
                        <input type="date" 
                               class="form-control" 
                               id="date_of_birth" 
                               name="date_of_birth" 
                               value="<?php echo htmlspecialchars($student['date_of_birth']); ?>" 
                               required>
                    </div>

                    <!-- Gender -->
                    <div class="col-md-6">
                        <label for="gender" class="form-label">Gender *</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="M" <?php echo $student['gender'] === 'M' ? 'selected' : ''; ?>>Male</option>
                            <option value="F" <?php echo $student['gender'] === 'F' ? 'selected' : ''; ?>>Female</option>
                            <option value="O" <?php echo $student['gender'] === 'O' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="graduated" <?php echo $student['status'] === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                            <option value="withdrawn" <?php echo $student['status'] === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                        </select>
                    </div>

                    <!-- Address -->
                    <div class="col-12">
                        <label for="address" class="form-label">Address *</label>
                        <textarea class="form-control" 
                                  id="address" 
                                  name="address" 
                                  rows="3" 
                                  required><?php echo htmlspecialchars($student['address']); ?></textarea>
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" 
                               class="form-control" 
                               id="phone" 
                               name="phone" 
                               value="<?php echo htmlspecialchars($student['phone']); ?>">
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($student['email']); ?>">
                    </div>

                    <div class="col-12">
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>
