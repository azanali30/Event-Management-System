<?php
$page_title = "Register";
$page_description = "Create your College Event Management account";
$additional_css = ['auth.css'];
// Completely disable all JavaScript to prevent form submission issues
$additional_js = [];
$disable_main_js = true;

require_once '../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('pages/dashboard.php');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $name = sanitize($_POST['name'] ?? '');
    $full_name = sanitize($_POST['full_name'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $enrollment_no = sanitize($_POST['enrollment_no'] ?? '');
    $terms = isset($_POST['terms']);

    // Validation
    if (empty($email) || empty($password) || empty($name) || empty($full_name)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!$terms) {
        $error_message = 'You must agree to the Terms of Service and Privacy Policy.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Start transaction
            $conn->beginTransaction();

            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error_message = 'Email already exists.';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users table (essential data only)
                $stmt = $conn->prepare("
                    INSERT INTO users (email, name, password, role)
                    VALUES (?, ?, ?, 'participant')
                ");

                if ($stmt->execute([$email, $name, $hashed_password])) {
                    // Get the new user ID
                    $user_id = $conn->lastInsertId();

                    // Insert into userdetails table (detailed information)
                    $stmt = $conn->prepare("
                        INSERT INTO userdetails (user_id, full_name, mobile, department, enrollment_no)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    if ($stmt->execute([$user_id, $full_name, $mobile, $department, $enrollment_no])) {
                        // Commit transaction
                        $conn->commit();

                        // Store success message in session for login page
                        $_SESSION['registration_success'] = 'Account created successfully! Please log in with your credentials.';

                        // Redirect to login page
                        header("Location: " . SITE_URL . "/pages/login.php");
                        exit();
                    } else {
                        $conn->rollback();
                        $error_message = 'Error saving user details. Please try again.';
                    }
                } else {
                    $conn->rollback();
                    $error_message = 'Error creating account. Please try again.';
                }
            }
        } catch (PDOException $e) {
            if (isset($conn)) {
                $conn->rollback();
            }
            $error_message = 'Database error. Please try again.';
            error_log('Registration error: ' . $e->getMessage());
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollback();
            }
            $error_message = 'System error. Please try again.';
        }
    }
}

include '../includes/pages-header.php';
?>

<div class="auth-container">
    <div class="container">
        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="auth-logo">
                        <img src="../images/logo.png" alt="Logo" class="auth-logo-img">
                    </div>
                    <h1 class="auth-title">Create Account</h1>
                    <p class="auth-subtitle">Join our community and start exploring events</p>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i data-lucide="alert-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i data-lucide="check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="auth-form">
                    <div class="form-group">
                        <label for="name" class="form-label">Display Name *</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            placeholder="Enter your display name"
                            value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                            autocomplete="name"
                            required
                        >
                        <small class="form-text">This will be shown publicly on the website</small>
                    </div>

                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-control"
                            placeholder="Enter your full name"
                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter your email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="mobile" class="form-label">Mobile Number</label>
                                <input
                                    type="tel"
                                    id="mobile"
                                    name="mobile"
                                    class="form-control"
                                    placeholder="Enter your mobile number"
                                    value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>"
                                    autocomplete="tel"
                                >
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="enrollment_no" class="form-label">Enrollment Number</label>
                                <input
                                    type="text"
                                    id="enrollment_no"
                                    name="enrollment_no"
                                    class="form-control"
                                    placeholder="Enter your enrollment number"
                                    value="<?php echo isset($_POST['enrollment_no']) ? htmlspecialchars($_POST['enrollment_no']) : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="department" class="form-label">Department</label>
                        <select id="department" name="department" class="form-control">
                            <option value="">Select your department</option>
                            <option value="Computer Science" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                            <option value="Information Technology" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                            <option value="Electronics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                            <option value="Mechanical" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Mechanical') ? 'selected' : ''; ?>>Mechanical</option>
                            <option value="Civil" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
                            <option value="Electrical" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Electrical') ? 'selected' : ''; ?>>Electrical</option>
                            <option value="Other" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="password" class="form-label">Password *</label>
                                <div class="password-input">
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control"
                                        placeholder="Create a password"
                                        autocomplete="new-password"
                                        required
                                    >
                                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                        <i data-lucide="eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <div class="password-input">
                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        class="form-control"
                                        placeholder="Confirm your password"
                                        autocomplete="new-password"
                                        required
                                    >
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i data-lucide="eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="terms" name="terms" class="form-check-input" required>
                            <label for="terms" class="form-check-label">
                                I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-full" onclick="return true;">
                        <i data-lucide="user-plus"></i>
                        Create Account
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p class="auth-link">
                        Already have an account? 
                        <a href="login.php">Sign in here</a>
                    </p>
                </div>
            </div>
            
            <div class="auth-side">
                <div class="auth-side-content">
                    <h2>Welcome to Our Community</h2>
                    <p>Join thousands of students who are already using our platform to discover amazing events and opportunities.</p>
                    
                    <div class="auth-features">
                        <div class="auth-feature">
                            <i data-lucide="calendar"></i>
                            <span>Discover Events</span>
                        </div>
                        <div class="auth-feature">
                            <i data-lucide="users"></i>
                            <span>Connect with Peers</span>
                        </div>
                        <div class="auth-feature">
                            <i data-lucide="award"></i>
                            <span>Earn Certificates</span>
                        </div>
                        <div class="auth-feature">
                            <i data-lucide="bell"></i>
                            <span>Get Notifications</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const toggle = input.nextElementSibling;
    const icon = toggle.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }

    lucide.createIcons();
}

// Simple form submission handler - no validation interference
document.addEventListener('DOMContentLoaded', function() {});
</script>

<?php include '../includes/footer.php'; ?>
