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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        // Validate and sanitize input
        $studentNumber = filter_input(INPUT_POST, 'student_number', FILTER_SANITIZE_STRING);
        $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $dateOfBirth = filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING);
        $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $classYear = filter_input(INPUT_POST, 'class_year', FILTER_SANITIZE_STRING);

        // Validate required fields
        if (!$studentNumber || !$firstName || !$lastName || !$dateOfBirth || !$gender || !$address || !$classYear) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                // Check if student number already exists
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_number = ?");
                $stmt->execute([$studentNumber]);
                if ($stmt->fetch()) {
                    $error = 'Student number already exists';
                } else {
                    // Insert new student
                    $stmt = $pdo->prepare("
                        INSERT INTO students (
                            student_number, first_name, last_name, date_of_birth, 
                            gender, address, phone, email, class_year, status
                        ) VALUES (
                            :student_number, :first_name, :last_name, :date_of_birth,
                            :gender, :address, :phone, :email, :class_year, 'active'
                        )
                    ");

                    $stmt->execute([
                        'student_number' => $studentNumber,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'date_of_birth' => $dateOfBirth,
                        'gender' => $gender,
                        'address' => $address,
                        'phone' => $phone,
                        'email' => $email,
                        'class_year' => $classYear
                    ]);

                    // Log the action
                    $studentId = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("
                        INSERT INTO system_logs (user_id, action, details) 
                        VALUES (:user_id, 'student_created', :details)
                    ");
                    $stmt->execute([
                        'user_id' => $_SESSION['user_id'],
                        'details' => "Created student ID: $studentId"
                    ]);

                    $success = 'Student successfully registered';
                    // Redirect to student list after short delay
                    header("refresh:2;url=index.php");
                }
            } catch (PDOException $e) {
                error_log("Error creating student: " . $e->getMessage());
                $error = 'An error occurred while registering the student';
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Register New Student</h2>
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
                    <!-- Student Number -->
                    <div class="col-md-6">
                        <label for="student_number" class="form-label">Student Number *</label>
                        <input type="text" 
                               class="form-control" 
                               id="student_number" 
                               name="student_number" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a student number.
                        </div>
                    </div>

                    <!-- Class Year -->
                    <div class="col-md-6">
                        <label for="class_year" class="form-label">Class Year *</label>
                        <select class="form-select" id="class_year" name="class_year" required>
                            <option value="">Select Class Year</option>
                            <?php 
                            $currentYear = date('Y');
                            for ($i = 0; $i < 4; $i++) {
                                $year = $currentYear + $i;
                                echo "<option value=\"$year\">$year</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a class year.
                        </div>
                    </div>

                    <!-- First Name -->
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="first_name" 
                               name="first_name" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a first name.
                        </div>
                    </div>

                    <!-- Last Name -->
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" 
                               class="form-control" 
                               id="last_name" 
                               name="last_name" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a last name.
                        </div>
                    </div>

                    <!-- Date of Birth -->
                    <div class="col-md-6">
                        <label for="date_of_birth" class="form-label">Date of Birth *</label>
                        <input type="date" 
                               class="form-control" 
                               id="date_of_birth" 
                               name="date_of_birth" 
                               required>
                        <div class="invalid-feedback">
                            Please provide a date of birth.
                        </div>
                    </div>

                    <!-- Gender -->
                    <div class="col-md-6">
                        <label for="gender" class="form-label">Gender *</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="M">Male</option>
                            <option value="F">Female</option>
                            <option value="O">Other</option>
                        </select>
                        <div class="invalid-feedback">
                            Please select a gender.
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="col-12">
                        <label for="address" class="form-label">Address *</label>
                        <textarea class="form-control" 
                                  id="address" 
                                  name="address" 
                                  rows="3" 
                                  required></textarea>
                        <div class="invalid-feedback">
                            Please provide an address.
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" 
                               class="form-control" 
                               id="phone" 
                               name="phone">
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email">
                        <div class="invalid-feedback">
                            Please provide a valid email address.
                        </div>
                    </div>

                    <div class="col-12">
                        <hr class="my-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Register Student
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i>Reset Form
                        </button>
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
