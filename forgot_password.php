<?php
/**
 * Forgot Password Page
 * Allows users to reset their password using Email or Mobile OTP
 */

session_start();
require_once 'includes/OTPService.php';

$otpService = new OTPService();
$message = '';
$messageType = '';
$step = $_GET['step'] ?? 'request'; // request, verify, reset
$method = $_GET['method'] ?? 'email'; // email, mobile

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_email_otp':
                $email = trim($_POST['email'] ?? '');
                if ($email) {
                    $result = $otpService->sendEmailOTP($email);
                    if ($result['success']) {
                        $_SESSION['reset_token'] = $result['token'];
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_method'] = 'email';
                        header('Location: forgot_password.php?step=verify&method=email');
                        exit;
                    } else {
                        $message = $result['message'];
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'send_mobile_otp':
                $mobile = trim($_POST['mobile'] ?? '');
                if ($mobile) {
                    $result = $otpService->sendMobileOTP($mobile);
                    if ($result['success']) {
                        $_SESSION['reset_token'] = $result['token'];
                        $_SESSION['reset_mobile'] = $mobile;
                        $_SESSION['reset_method'] = 'mobile';
                        $_SESSION['masked_mobile'] = $result['masked_mobile'];
                        header('Location: forgot_password.php?step=verify&method=mobile');
                        exit;
                    } else {
                        $message = $result['message'];
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'verify_otp':
                $token = $_SESSION['reset_token'] ?? '';
                $otpCode = trim($_POST['otp_code'] ?? '');
                
                if ($token && $otpCode) {
                    $result = $otpService->verifyOTP($token, $otpCode);
                    if ($result['success']) {
                        $_SESSION['verified_token'] = $token;
                        $_SESSION['reset_user_id'] = $result['user_id'];
                        header('Location: forgot_password.php?step=reset');
                        exit;
                    } else {
                        $message = $result['message'];
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Please enter the OTP code';
                    $messageType = 'error';
                }
                break;
                
            case 'reset_password':
                $token = $_SESSION['verified_token'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (!$token) {
                    $message = 'Invalid session. Please start over.';
                    $messageType = 'error';
                } elseif (strlen($newPassword) < 6) {
                    $message = 'Password must be at least 6 characters long';
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = 'Passwords do not match';
                    $messageType = 'error';
                } else {
                    $result = $otpService->resetPassword($token, $newPassword);
                    if ($result['success']) {
                        // Clear session data
                        unset($_SESSION['reset_token'], $_SESSION['verified_token'], 
                              $_SESSION['reset_email'], $_SESSION['reset_mobile'], 
                              $_SESSION['reset_method'], $_SESSION['reset_user_id']);
                        
                        $message = $result['message'];
                        $messageType = 'success';
                        $step = 'complete';
                    } else {
                        $message = $result['message'];
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Validate session for verify and reset steps
if (($step === 'verify' && !isset($_SESSION['reset_token'])) || 
    ($step === 'reset' && !isset($_SESSION['verified_token']))) {
    header('Location: forgot_password.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .forgot-password-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h2 {
            margin: 0;
            font-weight: 300;
        }
        
        .header .step-indicator {
            margin-top: 15px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 40px;
        }
        
        .method-selector {
            display: flex;
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        
        .method-option {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #666;
        }
        
        .method-option.active {
            background: #667eea;
            color: white;
        }
        
        .method-option:hover {
            background: #f8f9fa;
            color: #333;
        }
        
        .method-option.active:hover {
            background: #5a6fd8;
            color: white;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 12px 15px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            transition: transform 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 25px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .otp-input {
            text-align: center;
            font-size: 24px;
            letter-spacing: 5px;
            font-weight: bold;
        }
        
        .success-icon {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .success-icon i {
            font-size: 64px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header">
            <h2><i class="fas fa-key"></i> Reset Password</h2>
            <div class="step-indicator">
                <?php
                switch ($step) {
                    case 'request':
                        echo 'Step 1: Choose Reset Method';
                        break;
                    case 'verify':
                        echo 'Step 2: Verify OTP Code';
                        break;
                    case 'reset':
                        echo 'Step 3: Set New Password';
                        break;
                    case 'complete':
                        echo 'Password Reset Complete';
                        break;
                }
                ?>
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
                    <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 'request'): ?>
                <!-- Step 1: Choose Reset Method -->
                <div class="method-selector">
                    <a href="?method=email" class="method-option <?php echo $method === 'email' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i><br>
                        <small>Email OTP</small>
                    </a>
                    <a href="?method=mobile" class="method-option <?php echo $method === 'mobile' ? 'active' : ''; ?>">
                        <i class="fas fa-mobile-alt"></i><br>
                        <small>Mobile OTP</small>
                    </a>
                </div>
                
                <?php if ($method === 'email'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_email_otp">
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter your registered email address" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send OTP to Email
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_mobile_otp">
                        <div class="form-group">
                            <label for="mobile" class="form-label">
                                <i class="fas fa-mobile-alt"></i> Mobile Number
                            </label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" 
                                   placeholder="Enter your registered mobile number" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sms"></i> Send OTP to Mobile
                        </button>
                    </form>
                <?php endif; ?>
                
            <?php elseif ($step === 'verify'): ?>
                <!-- Step 2: Verify OTP -->
                <div class="text-center mb-4">
                    <p class="text-muted">
                        We've sent a 6-digit OTP to your 
                        <?php echo $method === 'email' ? 'email address' : 'mobile number'; ?>
                        <?php if ($method === 'mobile' && isset($_SESSION['masked_mobile'])): ?>
                            <br><strong><?php echo $_SESSION['masked_mobile']; ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="form-group">
                        <label for="otp_code" class="form-label">
                            <i class="fas fa-shield-alt"></i> Enter OTP Code
                        </label>
                        <input type="text" class="form-control otp-input" id="otp_code" name="otp_code" 
                               placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Verify OTP
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="text-muted">
                        <i class="fas fa-arrow-left"></i> Back to reset method selection
                    </a>
                </div>
                
            <?php elseif ($step === 'reset'): ?>
                <!-- Step 3: Set New Password -->
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <div class="form-group">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               placeholder="Enter new password (min 6 characters)" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i> Confirm New Password
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm your new password" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                
            <?php elseif ($step === 'complete'): ?>
                <!-- Step 4: Complete -->
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="text-center">
                    <h4>Password Reset Successful!</h4>
                    <p class="text-muted">Your password has been reset successfully. You can now login with your new password.</p>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($step !== 'complete'): ?>
                <div class="back-link">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp_code');
            if (otpInput) {
                otpInput.addEventListener('input', function(e) {
                    // Only allow numbers
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
                
                // Auto-focus on page load
                otpInput.focus();
            }
            
            // Password confirmation validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                function validatePasswords() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
        });
    </script>
</body>
</html>
