<?php
/**
 * EmailService Class
 * 
 * A comprehensive email service for handling all email notifications
 * Features:
 * - PHPMailer integration with secure SMTP (TLS, port 587)
 * - Admin notifications for new registrations
 * - User approval confirmation emails
 * - HTML email templates with dynamic content
 * - Error handling and logging
 * - Reusable service for different email types
 */

// Include Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    
    private $config;
    private $mailer;
    private $logger;
    
    /**
     * Constructor - Initialize email service with configuration
     */
    public function __construct() {
        $this->loadConfiguration();
        $this->initializeMailer();
        $this->initializeLogger();
    }
    
    /**
     * Load email configuration
     */
    private function loadConfiguration() {
        $config_path = __DIR__ . '/../config/email_config.php';
        if (file_exists($config_path)) {
            $this->config = include $config_path;
        } else {
            throw new Exception('Email configuration file not found');
        }
    }
    
    /**
     * Initialize PHPMailer with secure SMTP settings
     */
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings - Secure SMTP with TLS
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp']['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp']['username'];
            $this->mailer->Password = $this->config['smtp']['password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
            $this->mailer->Port = $this->config['smtp']['port']; // Port 587
            $this->mailer->SMTPDebug = 0; // Disable debug output for production
            
            // Default sender
            $this->mailer->setFrom(
                $this->config['from']['email'], 
                $this->config['from']['name']
            );
            
        } catch (Exception $e) {
            $this->logError('SMTP Configuration Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Initialize logging system
     */
    private function initializeLogger() {
        $this->logger = [
            'log_file' => __DIR__ . '/../logs/email_service.log',
            'enabled' => true
        ];
        
        // Ensure logs directory exists
        $logs_dir = dirname($this->logger['log_file']);
        if (!is_dir($logs_dir)) {
            mkdir($logs_dir, 0755, true);
        }
    }
    
    /**
     * Send notification to admin when new user registers
     * 
     * @param array $userData User registration data
     * @return bool Success status
     */
    public function sendNewRegistrationNotification($userData) {
        try {
            if (!$this->config['notifications']['new_registration']) {
                return true; // Notification disabled
            }
            
            $subject = 'New User Registration - ' . $userData['name'];
            $htmlBody = $this->generateNewRegistrationTemplate($userData);
            $textBody = $this->generateTextVersion($htmlBody);
            
            return $this->sendEmail(
                $this->config['admin_email'],
                $this->config['from']['name'] . ' Admin',
                $subject,
                $htmlBody,
                $textBody,
                'NEW_REGISTRATION'
            );
            
        } catch (Exception $e) {
            $this->logError('New Registration Notification Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send approval confirmation email to user
     * 
     * @param array $userData User data
     * @param array $approvalData Approval details
     * @return bool Success status
     */
    public function sendApprovalConfirmation($userData, $approvalData = []) {
        try {
            if (!$this->config['notifications']['registration_approval']) {
                return true; // Notification disabled
            }
            
            $subject = 'Registration Approved - Welcome to ' . $this->config['templates']['system_name'];
            $htmlBody = $this->generateApprovalTemplate($userData, $approvalData);
            $textBody = $this->generateTextVersion($htmlBody);
            
            return $this->sendEmail(
                $userData['email'],
                $userData['name'],
                $subject,
                $htmlBody,
                $textBody,
                'APPROVAL_CONFIRMATION'
            );
            
        } catch (Exception $e) {
            $this->logError('Approval Confirmation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generic email sending method
     *
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string $textBody Plain text email body
     * @param string $type Email type for logging
     * @return bool Success status
     */
    private function sendEmail($toEmail, $toName, $subject, $htmlBody, $textBody, $type = 'GENERAL') {
        // First try SMTP, then fallback to file saving for testing
        try {
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Recipients
            $this->mailer->addAddress($toEmail, $toName);

            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $textBody;

            // Send email
            $result = $this->mailer->send();

            if ($result) {
                $this->logSuccess($type, $toEmail, $subject);
                return true;
            } else {
                $this->logError($type . ' - SMTP Send failed for: ' . $toEmail . ' - Trying fallback');
                // Try fallback method
                return $this->saveEmailToFile($toEmail, $toName, $subject, $htmlBody, $type);
            }

        } catch (Exception $e) {
            $this->logError($type . ' - SMTP Exception: ' . $e->getMessage() . ' for: ' . $toEmail . ' - Trying fallback');
            // Try fallback method
            return $this->saveEmailToFile($toEmail, $toName, $subject, $htmlBody, $type);
        }
    }

    /**
     * Fallback method - save email to file for testing
     */
    private function saveEmailToFile($toEmail, $toName, $subject, $htmlBody, $type) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = __DIR__ . "/../logs/email_{$type}_{$timestamp}.html";

            $emailContent = "
<!DOCTYPE html>
<html>
<head>
    <title>Email: {$subject}</title>
    <style>body { font-family: Arial, sans-serif; margin: 20px; }</style>
</head>
<body>
    <div style='background: #f0f0f0; padding: 20px; border-radius: 5px; margin-bottom: 20px;'>
        <h2>📧 Email Details</h2>
        <p><strong>To:</strong> {$toName} &lt;{$toEmail}&gt;</p>
        <p><strong>Subject:</strong> {$subject}</p>
        <p><strong>Type:</strong> {$type}</p>
        <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
    </div>
    <div style='border: 1px solid #ddd; padding: 20px;'>
        {$htmlBody}
    </div>
</body>
</html>";

            if (file_put_contents($filename, $emailContent)) {
                $this->logSuccess($type . '_FILE', $toEmail, $subject . ' (Saved to file: ' . basename($filename) . ')');
                return true;
            } else {
                $this->logError($type . ' - Failed to save email to file for: ' . $toEmail);
                return false;
            }

        } catch (Exception $e) {
            $this->logError($type . ' - File save exception: ' . $e->getMessage() . ' for: ' . $toEmail);
            return false;
        }
    }
    
    /**
     * Generate HTML template for new registration notification
     */
    private function generateNewRegistrationTemplate($userData) {
        $registrationDate = $userData['registration_date'] ?? date('Y-m-d H:i:s');
        $eventName = $userData['event_name'] ?? 'Unknown Event';
        
        return $this->getEmailTemplate([
            'title' => 'New User Registration',
            'heading' => '🎉 New Registration Alert',
            'content' => "
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #007bff; margin-top: 0;'>Registration Details</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'><strong>Name:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'>{$userData['name']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'><strong>Email:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'>{$userData['email']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'><strong>Phone:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'>{$userData['phone']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'><strong>Event:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'>{$eventName}</td></tr>
                        <tr><td style='padding: 8px 0;'><strong>Registration Date:</strong></td><td style='padding: 8px 0;'>{$registrationDate}</td></tr>
                    </table>
                </div>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #1976d2;'><strong>Action Required:</strong> Please review and approve this registration in the admin panel.</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/event/admin/dashboard.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Admin Panel</a>
                </div>
            "
        ]);
    }
    
    /**
     * Generate HTML template for approval confirmation
     */
    private function generateApprovalTemplate($userData, $approvalData) {
        $approvalDate = $approvalData['approval_date'] ?? date('Y-m-d H:i:s');
        $eventName = $userData['event_name'] ?? 'Event';
        
        return $this->getEmailTemplate([
            'title' => 'Registration Approved',
            'heading' => '🎉 Congratulations! Your Registration is Approved',
            'content' => "
                <div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
                    <h3 style='color: #155724; margin-top: 0;'>Welcome {$userData['name']}!</h3>
                    <p style='color: #155724; margin-bottom: 0;'>Your registration for <strong>{$eventName}</strong> has been approved and confirmed.</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #007bff; margin-top: 0;'>Registration Summary</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'><strong>Name:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'>{$userData['name']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'><strong>Email:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'>{$userData['email']}</td></tr>
                        <tr><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'><strong>Event:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #dee2e6;'>{$eventName}</td></tr>
                        <tr><td style='padding: 8px 0;'><strong>Approved On:</strong></td><td style='padding: 8px 0;'>{$approvalDate}</td></tr>
                    </table>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='color: #856404; margin-top: 0;'>Next Steps:</h4>
                    <ul style='color: #856404; margin-bottom: 0;'>
                        <li>You will receive further instructions via email</li>
                        <li>Keep this email for your records</li>
                        <li>Contact us if you have any questions</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <p style='color: #666;'>Thank you for registering with us!</p>
                </div>
            "
        ]);
    }

    /**
     * Base email template with consistent styling
     */
    private function getEmailTemplate($data) {
        $headerColor = $this->config['templates']['header_color'];
        $systemName = $this->config['templates']['system_name'];

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$data['title']}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 0; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>

                <!-- Header -->
                <div style='background: {$headerColor}; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>{$systemName}</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>{$data['heading']}</p>
                </div>

                <!-- Content -->
                <div style='padding: 30px;'>
                    {$data['content']}
                </div>

                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; border-top: 1px solid #dee2e6;'>
                    <p style='margin: 0; color: #666; font-size: 14px;'>
                        This is an automated message from {$systemName}<br>
                        <small>Sent on " . date('Y-m-d H:i:s') . "</small>
                    </p>
                </div>

            </div>
        </body>
        </html>";
    }

    /**
     * Generate plain text version of HTML email
     */
    private function generateTextVersion($htmlContent) {
        // Simple HTML to text conversion
        $text = strip_tags($htmlContent);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Log successful email sending
     */
    private function logSuccess($type, $email, $subject) {
        $this->writeLog('SUCCESS', $type, $email, $subject);
    }

    /**
     * Log email errors
     */
    private function logError($message) {
        $this->writeLog('ERROR', 'SYSTEM', '', $message);
    }

    /**
     * Write to log file
     */
    private function writeLog($status, $type, $email, $message) {
        if (!$this->logger['enabled']) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$status} - {$type} - {$email} - {$message}" . PHP_EOL;

        file_put_contents($this->logger['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send custom email with template
     */
    public function sendCustomEmail($toEmail, $toName, $subject, $templateData, $type = 'CUSTOM') {
        try {
            $htmlBody = $this->getEmailTemplate($templateData);
            $textBody = $this->generateTextVersion($htmlBody);

            return $this->sendEmail($toEmail, $toName, $subject, $htmlBody, $textBody, $type);

        } catch (Exception $e) {
            $this->logError('Custom Email Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function testConfiguration() {
        try {
            $testData = [
                'name' => 'Test User',
                'email' => $this->config['admin_email'],
                'phone' => '123-456-7890',
                'event_name' => 'Email System Test',
                'registration_date' => date('Y-m-d H:i:s')
            ];

            return $this->sendNewRegistrationNotification($testData);

        } catch (Exception $e) {
            $this->logError('Configuration Test Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email service status
     */
    public function getStatus() {
        return [
            'phpmailer_available' => class_exists('PHPMailer\PHPMailer\PHPMailer'),
            'config_loaded' => !empty($this->config),
            'smtp_configured' => !empty($this->config['smtp']['password']) &&
                               $this->config['smtp']['password'] !== 'your-gmail-app-password',
            'admin_email' => $this->config['admin_email'] ?? 'Not set',
            'logs_writable' => is_writable(dirname($this->logger['log_file'])),
            'last_test' => file_exists($this->logger['log_file']) ?
                          date('Y-m-d H:i:s', filemtime($this->logger['log_file'])) : 'Never'
        ];
    }
}
