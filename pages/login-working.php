<?php
$page_title = "Login";
$page_description = "Sign in to your account to access events and features";
$additional_css = ['auth.css'];
require_once '../config/config.php';

// Handle form submission
$login_error = false;
if ($_POST) {
    // In a real application, you would validate credentials here
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        // Simulate login success - redirect to dashboard
        header('Location: ../index.php');
        exit;
    } else {
        $login_error = "Please fill in all fields.";
    }
}
include '../includes/pages-header.php';
?>
    <div class="auth-container">
        <div class="auth-card ken42-card animate-fade-in-up">
            <div class="auth-header">
                <div class="auth-logo">
                    <img src="../images/logo.png" alt="Logo" class="auth-logo-img">
                </div>
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">Sign in to your account to continue</p>
            </div>
            
            <?php if ($login_error): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle"></i>
                <span><?php echo $login_error; ?></span>
            </div>
            <?php endif; ?>
            
            <form class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <i data-lucide="mail" class="input-icon"></i>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <i data-lucide="lock" class="input-icon"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i data-lucide="eye" id="password-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" class="checkbox">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full btn-animated">
                    <i data-lucide="log-in"></i>
                    Sign In
                </button>
            </form>
            
            <div class="auth-divider">
                <span>or</span>
            </div>
            
            <div class="social-login">
                <button class="btn btn-social btn-google">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>
                
                <button class="btn btn-social btn-microsoft">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.4 24H0V12.6h11.4V24zM24 24H12.6V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zM24 11.4H12.6V0H24v11.4z"/>
                    </svg>
                    Continue with Microsoft
                </button>
            </div>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register-working.php" class="auth-link">Sign up here</a></p>
            </div>
        </div>
        
        <div class="auth-background">
            <div class="background-pattern"></div>
        </div>
    </div>
    
    <!-- Navigation Link -->
    <div class="back-to-site">
        <a href="../index.php" class="back-link">
            <i data-lucide="arrow-left"></i>
            Back to Website
        </a>
    </div>
    
    <!-- JavaScript -->
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/animations.js"></script>
    
    <!-- JavaScript -->
    <script src="../assets/js/auth.js"></script>
</body>
</html>
