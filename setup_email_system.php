<?php
/**
 * Email System Setup and Configuration
 * This script will help you set up email notifications properly
 */

// Include Composer autoloader
require_once 'vendor/autoload.php';

// Include required files
require_once 'config/database.php';
require_once 'includes/EmailNotification.php';

// Start session for admin access
session_start();

// Simple admin check (you can modify this)
$isAdmin = true; // Set to true for setup, change to proper admin check later

if (!$isAdmin) {
    die('Access denied. Admin access required.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Setup - Event Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; color: #007bff; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .code { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; margin: 10px 0; border-left: 4px solid #007bff; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0; }
        .status-card { padding: 15px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Email System Setup</h1>
            <p>Configure and test your email notification system</p>
        </div>

        <?php
        // Check system status
        $phpMailerExists = class_exists('PHPMailer\PHPMailer\PHPMailer');
        $configExists = file_exists('config/email_config.php');
        $logsDir = is_dir('logs') && is_writable('logs');
        
        // Load email config
        $emailConfig = [];
        if ($configExists) {
            $emailConfig = include 'config/email_config.php';
        }
        
        // Test email functionality
        $emailTest = false;
        $emailError = '';
        
        if (isset($_POST['test_email'])) {
            try {
                $emailNotification = new EmailNotification();
                $result = $emailNotification->sendAdminNotification(
                    'Email System Test',
                    'This is a test email from your Event Management System setup. If you receive this, your email system is working correctly!',
                    ['test_time' => date('Y-m-d H:i:s'), 'test_type' => 'Manual Setup Test']
                );
                
                if ($result) {
                    $emailTest = true;
                } else {
                    $emailError = 'Email sending failed - check logs for details';
                }
            } catch (Exception $e) {
                $emailError = 'Error: ' . $e->getMessage();
            }
        }
        
        // Gmail App Password setup
        if (isset($_POST['setup_gmail'])) {
            $gmail_password = $_POST['gmail_password'] ?? '';
            if (!empty($gmail_password)) {
                // Update config file
                $configContent = file_get_contents('config/email_config.php');
                $configContent = str_replace('your-gmail-app-password', $gmail_password, $configContent);
                file_put_contents('config/email_config.php', $configContent);
                echo '<div class="section success">Gmail App Password updated successfully!</div>';
            }
        }
        ?>

        <!-- System Status -->
        <div class="section">
            <h2>🔍 System Status</h2>
            <div class="status-grid">
                <div class="status-card <?php echo $phpMailerExists ? 'success' : 'error'; ?>">
                    <h3><?php echo $phpMailerExists ? '✅' : '❌'; ?> PHPMailer</h3>
                    <p><?php echo $phpMailerExists ? 'Installed' : 'Not Found'; ?></p>
                </div>
                
                <div class="status-card <?php echo $configExists ? 'success' : 'error'; ?>">
                    <h3><?php echo $configExists ? '✅' : '❌'; ?> Email Config</h3>
                    <p><?php echo $configExists ? 'Found' : 'Missing'; ?></p>
                </div>
                
                <div class="status-card <?php echo $logsDir ? 'success' : 'warning'; ?>">
                    <h3><?php echo $logsDir ? '✅' : '⚠️'; ?> Logs Directory</h3>
                    <p><?php echo $logsDir ? 'Ready' : 'Check Permissions'; ?></p>
                </div>
                
                <div class="status-card info">
                    <h3>📧 Admin Email</h3>
                    <p><?php echo $emailConfig['admin_email'] ?? 'Not Set'; ?></p>
                </div>
            </div>
        </div>

        <!-- Gmail Setup -->
        <div class="section">
            <h2>🔧 Gmail SMTP Setup</h2>
            <p>To send emails via Gmail, you need to set up an App Password:</p>
            
            <div class="info" style="margin: 15px 0;">
                <h3>Steps to get Gmail App Password:</h3>
                <ol>
                    <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                    <li>Enable 2-Step Verification if not already enabled</li>
                    <li>Click on "App passwords"</li>
                    <li>Select "Mail" and "Other (Custom name)"</li>
                    <li>Enter "Event Management System"</li>
                    <li>Copy the 16-character password</li>
                </ol>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="gmail_password">Gmail App Password (16 characters):</label>
                    <input type="password" id="gmail_password" name="gmail_password" placeholder="abcd efgh ijkl mnop" maxlength="19">
                </div>
                <button type="submit" name="setup_gmail" class="btn btn-primary">Update Gmail Settings</button>
            </form>
        </div>

        <!-- Email Test -->
        <div class="section">
            <h2>🧪 Test Email System</h2>
            
            <?php if ($emailTest): ?>
                <div class="success">
                    <h3>✅ Email Test Successful!</h3>
                    <p>Test email sent successfully to: <?php echo $emailConfig['admin_email'] ?? 'admin'; ?></p>
                    <p>Check your email inbox (and spam folder) for the test message.</p>
                </div>
            <?php elseif (!empty($emailError)): ?>
                <div class="error">
                    <h3>❌ Email Test Failed</h3>
                    <p><?php echo htmlspecialchars($emailError); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <button type="submit" name="test_email" class="btn btn-success">Send Test Email</button>
            </form>
            
            <div class="info" style="margin-top: 20px;">
                <h3>📝 Email Logs</h3>
                <p>Check the following locations for email logs:</p>
                <ul>
                    <li><code>logs/email_log.txt</code> - Email sending attempts</li>
                    <li><code>logs/emails_<?php echo date('Y-m-d'); ?>.txt</code> - Today's email content</li>
                </ul>
            </div>
        </div>

        <!-- Current Configuration -->
        <div class="section">
            <h2>⚙️ Current Configuration</h2>
            <?php if ($configExists): ?>
                <div class="code">
Admin Email: <?php echo $emailConfig['admin_email'] ?? 'Not set'; ?>
SMTP Host: <?php echo $emailConfig['smtp']['host'] ?? 'Not set'; ?>
SMTP Port: <?php echo $emailConfig['smtp']['port'] ?? 'Not set'; ?>
SMTP Username: <?php echo $emailConfig['smtp']['username'] ?? 'Not set'; ?>
SMTP Password: <?php echo !empty($emailConfig['smtp']['password']) && $emailConfig['smtp']['password'] !== 'your-gmail-app-password' ? 'Set (hidden)' : 'Not set'; ?>
From Email: <?php echo $emailConfig['from']['email'] ?? 'Not set'; ?>
From Name: <?php echo $emailConfig['from']['name'] ?? 'Not set'; ?>
                </div>
            <?php else: ?>
                <div class="error">Email configuration file not found!</div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="section">
            <h2>🚀 Quick Actions</h2>
            <a href="admin/dashboard.php" class="btn btn-primary">Go to Admin Dashboard</a>
            <a href="test_email_system.php" class="btn btn-warning">Advanced Email Testing</a>
            <a href="pages/register-event.php" class="btn btn-success">Test Registration</a>
        </div>

        <!-- Troubleshooting -->
        <div class="section">
            <h2>🔧 Troubleshooting</h2>
            <div class="warning">
                <h3>Common Issues:</h3>
                <ul>
                    <li><strong>SMTP Connection Failed:</strong> Check Gmail App Password and 2FA settings</li>
                    <li><strong>Emails not received:</strong> Check spam folder, verify admin email address</li>
                    <li><strong>Permission errors:</strong> Ensure logs directory is writable</li>
                    <li><strong>PHPMailer missing:</strong> Run <code>composer install</code> in project directory</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
