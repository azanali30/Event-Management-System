<?php
/**
 * OTP Service for Forgot Password Functionality
 * Handles both Email and Mobile OTP generation and verification
 */

require_once 'config/database.php';
require_once 'includes/EmailService.php';

class OTPService {
    private $db;
    private $emailService;
    
    // OTP Configuration
    private $otpLength = 6;
    private $otpExpiryMinutes = 15;
    private $maxAttempts = 5;
    private $maxDailyOTPs = 10;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->emailService = new EmailService();
    }
    
    /**
     * Generate and send OTP via email
     * 
     * @param string $email User email address
     * @return array Result with success status and message
     */
    public function sendEmailOTP($email) {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Check if user exists
            $user = $this->getUserByEmail($email);
            if (!$user) {
                return ['success' => false, 'message' => 'No account found with this email address'];
            }
            
            // Check daily limit
            if (!$this->checkDailyLimit($user['user_id'], 'email')) {
                return ['success' => false, 'message' => 'Daily OTP limit exceeded. Please try again tomorrow.'];
            }
            
            // Generate OTP
            $otpCode = $this->generateOTP();
            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->otpExpiryMinutes} minutes"));
            
            // Store OTP in database
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_otps 
                (user_id, email, otp_code, otp_type, token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, 'email', ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            if (!$stmt->execute([$user['user_id'], $email, $otpCode, $token, $expiresAt, $ipAddress, $userAgent])) {
                return ['success' => false, 'message' => 'Failed to generate OTP. Please try again.'];
            }
            
            // Send OTP via email
            $emailSent = $this->sendOTPEmail($email, $user['name'], $otpCode);
            
            if ($emailSent) {
                // Log the activity
                $this->logActivity($user['user_id'], $email, null, 'otp_sent', 'email', [
                    'token' => $token,
                    'expires_at' => $expiresAt
                ]);
                
                return [
                    'success' => true, 
                    'message' => 'OTP sent to your email address',
                    'token' => $token,
                    'expires_in' => $this->otpExpiryMinutes
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to send OTP email. Please try again.'];
            }
            
        } catch (Exception $e) {
            error_log("Email OTP Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again later.'];
        }
    }
    
    /**
     * Generate and send OTP via mobile SMS
     * 
     * @param string $mobile Mobile number
     * @return array Result with success status and message
     */
    public function sendMobileOTP($mobile) {
        try {
            // Validate and format mobile number
            $mobile = $this->formatMobileNumber($mobile);
            if (!$mobile) {
                return ['success' => false, 'message' => 'Invalid mobile number format'];
            }
            
            // Check if user exists with this mobile
            $user = $this->getUserByMobile($mobile);
            if (!$user) {
                return ['success' => false, 'message' => 'No account found with this mobile number'];
            }
            
            // Check daily limit
            if (!$this->checkDailyLimit($user['user_id'], 'mobile')) {
                return ['success' => false, 'message' => 'Daily OTP limit exceeded. Please try again tomorrow.'];
            }
            
            // Generate OTP
            $otpCode = $this->generateOTP();
            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->otpExpiryMinutes} minutes"));
            
            // Store OTP in database
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_otps 
                (user_id, email, mobile, otp_code, otp_type, token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, 'mobile', ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            if (!$stmt->execute([$user['user_id'], $user['email'], $mobile, $otpCode, $token, $expiresAt, $ipAddress, $userAgent])) {
                return ['success' => false, 'message' => 'Failed to generate OTP. Please try again.'];
            }
            
            // Send OTP via SMS
            $smsSent = $this->sendOTPSMS($mobile, $otpCode);
            
            if ($smsSent['success']) {
                // Log the activity
                $this->logActivity($user['user_id'], $user['email'], $mobile, 'otp_sent', 'mobile', [
                    'token' => $token,
                    'expires_at' => $expiresAt,
                    'sms_response' => $smsSent
                ]);
                
                return [
                    'success' => true, 
                    'message' => 'OTP sent to your mobile number',
                    'token' => $token,
                    'expires_in' => $this->otpExpiryMinutes,
                    'masked_mobile' => $this->maskMobileNumber($mobile)
                ];
            } else {
                return ['success' => false, 'message' => $smsSent['message'] ?? 'Failed to send SMS. Please try again.'];
            }
            
        } catch (Exception $e) {
            error_log("Mobile OTP Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again later.'];
        }
    }
    
    /**
     * Verify OTP code
     * 
     * @param string $token OTP token
     * @param string $otpCode OTP code entered by user
     * @return array Result with success status and message
     */
    public function verifyOTP($token, $otpCode) {
        try {
            // Get OTP record
            $stmt = $this->db->prepare("
                SELECT * FROM password_reset_otps 
                WHERE token = ? AND is_verified = 0 AND is_used = 0 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$token]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$otpRecord) {
                return ['success' => false, 'message' => 'Invalid or expired OTP token'];
            }
            
            // Check if OTP is expired
            if (strtotime($otpRecord['expires_at']) < time()) {
                return ['success' => false, 'message' => 'OTP has expired. Please request a new one.'];
            }
            
            // Check max attempts
            if ($otpRecord['attempts'] >= $this->maxAttempts) {
                return ['success' => false, 'message' => 'Maximum verification attempts exceeded. Please request a new OTP.'];
            }
            
            // Increment attempts
            $stmt = $this->db->prepare("UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$otpRecord['id']]);
            
            // Verify OTP code
            if ($otpRecord['otp_code'] !== $otpCode) {
                $remainingAttempts = $this->maxAttempts - ($otpRecord['attempts'] + 1);
                
                // Log failed attempt
                $this->logActivity($otpRecord['user_id'], $otpRecord['email'], $otpRecord['mobile'], 'otp_failed', $otpRecord['otp_type'], [
                    'attempts' => $otpRecord['attempts'] + 1,
                    'remaining_attempts' => $remainingAttempts
                ]);
                
                if ($remainingAttempts > 0) {
                    return ['success' => false, 'message' => "Invalid OTP. $remainingAttempts attempts remaining."];
                } else {
                    return ['success' => false, 'message' => 'Maximum attempts exceeded. Please request a new OTP.'];
                }
            }
            
            // OTP is valid - mark as verified
            $stmt = $this->db->prepare("
                UPDATE password_reset_otps 
                SET is_verified = 1, verified_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$otpRecord['id']]);
            
            // Log successful verification
            $this->logActivity($otpRecord['user_id'], $otpRecord['email'], $otpRecord['mobile'], 'otp_verified', $otpRecord['otp_type'], [
                'token' => $token
            ]);
            
            return [
                'success' => true, 
                'message' => 'OTP verified successfully',
                'user_id' => $otpRecord['user_id'],
                'email' => $otpRecord['email'],
                'token' => $token
            ];
            
        } catch (Exception $e) {
            error_log("OTP Verification Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again later.'];
        }
    }
    
    /**
     * Reset user password after OTP verification
     * 
     * @param string $token Verified OTP token
     * @param string $newPassword New password
     * @return array Result with success status and message
     */
    public function resetPassword($token, $newPassword) {
        try {
            // Validate password
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
            }
            
            // Get verified OTP record
            $stmt = $this->db->prepare("
                SELECT * FROM password_reset_otps 
                WHERE token = ? AND is_verified = 1 AND is_used = 0 
                ORDER BY verified_at DESC LIMIT 1
            ");
            $stmt->execute([$token]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$otpRecord) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }
            
            // Check if token is still valid (30 minutes after verification)
            if (strtotime($otpRecord['verified_at']) < strtotime('-30 minutes')) {
                return ['success' => false, 'message' => 'Reset token has expired. Please start the process again.'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            if (!$stmt->execute([$hashedPassword, $otpRecord['user_id']])) {
                return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
            }
            
            // Mark OTP as used
            $stmt = $this->db->prepare("UPDATE password_reset_otps SET is_used = 1, used_at = NOW() WHERE id = ?");
            $stmt->execute([$otpRecord['id']]);
            
            // Log password reset
            $this->logActivity($otpRecord['user_id'], $otpRecord['email'], $otpRecord['mobile'], 'password_reset', $otpRecord['otp_type'], [
                'token' => $token
            ]);
            
            return ['success' => true, 'message' => 'Password reset successfully. You can now login with your new password.'];
            
        } catch (Exception $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error. Please try again later.'];
        }
    }
    
    /**
     * Generate random OTP code
     */
    private function generateOTP() {
        return str_pad(random_int(0, pow(10, $this->otpLength) - 1), $this->otpLength, '0', STR_PAD_LEFT);
    }

    /**
     * Generate secure token
     */
    private function generateToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get user by email
     */
    private function getUserByEmail($email) {
        $stmt = $this->db->prepare("SELECT user_id, email, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by mobile number
     */
    private function getUserByMobile($mobile) {
        $stmt = $this->db->prepare("
            SELECT u.user_id, u.email, u.name, ud.mobile
            FROM users u
            JOIN userdetails ud ON u.user_id = ud.user_id
            WHERE ud.mobile = ?
        ");
        $stmt->execute([$mobile]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check daily OTP limit
     */
    private function checkDailyLimit($userId, $otpType) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM password_reset_otps
            WHERE user_id = ? AND otp_type = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$userId, $otpType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] < $this->maxDailyOTPs;
    }

    /**
     * Send OTP via email
     */
    private function sendOTPEmail($email, $name, $otpCode) {
        $subject = 'Password Reset OTP - Event Management System';
        $htmlBody = $this->generateOTPEmailTemplate($name, $otpCode);

        return $this->emailService->sendCustomEmail($email, $name, $subject, $htmlBody);
    }

    /**
     * Generate OTP email template
     */
    private function generateOTPEmailTemplate($name, $otpCode) {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center;'>
                <h1 style='color: #333; margin-bottom: 20px;'>🔐 Password Reset OTP</h1>
                <p style='font-size: 16px; color: #666; margin-bottom: 30px;'>
                    Hello <strong>$name</strong>,<br>
                    You requested to reset your password. Use the OTP below to continue:
                </p>

                <div style='background: #007bff; color: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h2 style='margin: 0; font-size: 32px; letter-spacing: 5px;'>$otpCode</h2>
                </div>

                <p style='color: #666; font-size: 14px;'>
                    ⏰ This OTP will expire in {$this->otpExpiryMinutes} minutes<br>
                    🔒 For security, do not share this code with anyone
                </p>

                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
                    <p style='color: #999; font-size: 12px;'>
                        If you didn't request this password reset, please ignore this email.
                    </p>
                </div>
            </div>
        </div>";
    }

    /**
     * Send OTP via SMS
     */
    private function sendOTPSMS($mobile, $otpCode) {
        // For now, return a mock success response
        // In production, integrate with SMS gateway like Twilio
        $message = "Your Event Management System password reset OTP is: $otpCode. Valid for {$this->otpExpiryMinutes} minutes. Do not share.";

        // Mock SMS sending - replace with actual SMS gateway integration
        error_log("SMS OTP to $mobile: $otpCode");

        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'message_id' => 'mock_' . time(),
            'cost' => 0.05
        ];
    }

    /**
     * Format mobile number
     */
    private function formatMobileNumber($mobile) {
        // Remove all non-numeric characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);

        // Basic validation - should be 10-15 digits
        if (strlen($mobile) >= 10 && strlen($mobile) <= 15) {
            return $mobile;
        }

        return false;
    }

    /**
     * Mask mobile number for display
     */
    private function maskMobileNumber($mobile) {
        if (strlen($mobile) > 4) {
            return substr($mobile, 0, 2) . str_repeat('*', strlen($mobile) - 4) . substr($mobile, -2);
        }
        return $mobile;
    }

    /**
     * Log activity
     */
    private function logActivity($userId, $email, $mobile, $action, $otpType, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_logs
                (user_id, email, mobile, action, otp_type, ip_address, user_agent, details)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $detailsJson = json_encode($details);

            $stmt->execute([$userId, $email, $mobile, $action, $otpType, $ipAddress, $userAgent, $detailsJson]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
