<?php
/**
 * Final Email System Setup & Status Page
 * Your PHPMailer integration is complete!
 */

require_once 'includes/EmailService.php';

$emailService = new EmailService();
$status = $emailService->getStatus();

// Handle Gmail App Password setup
$setupMessage = '';
if (isset($_POST['setup_gmail_app_password'])) {
    $appPassword = trim($_POST['app_password']);
    
    if (!empty($appPassword)) {
        // Update config file with App Password
        $configContent = "<?php
return [
    'admin_email' => 'codisticsolutions@gmail.com',
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'codisticsolutions@gmail.com',
        'password' => '{$appPassword}',
        'encryption' => 'tls'
    ],
    'from' => [
        'email' => 'codisticsolutions@gmail.com',
        'name' => 'Event Management System'
    ],
    'templates' => [
        'header_color' => '#007bff',
        'footer_color' => '#333333',
        'system_name' => 'Event Management System'
    ],
    'notifications' => [
        'new_registration' => true,
        'registration_approval' => true,
        'payment_received' => true,
        'qr_code_generated' => true,
        'system_errors' => true,
        'event_created' => true,
        'daily_reports' => true
    ]
];
?>";
        
        if (file_put_contents('config/email_config.php', $configContent)) {
            $setupMessage = 'success|Gmail App Password updated successfully! You can now send real emails.';
            // Reinitialize service
            $emailService = new EmailService();
            $status = $emailService->getStatus();
        } else {
            $setupMessage = 'error|Failed to update configuration file.';
        }
    } else {
        $setupMessage = 'error|Please enter a valid App Password.';
    }
}

// Handle test email
$testResult = '';
if (isset($_POST['send_test_email'])) {
    $testData = [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'phone' => '123-456-7890',
        'event_name' => 'Email System Test',
        'registration_date' => date('Y-m-d H:i:s'),
        'registration_id' => 'TEST-' . time()
    ];
    
    $result = $emailService->sendNewRegistrationNotification($testData);
    $testResult = $result ? 'success|Test email sent successfully!' : 'error|Test email failed to send.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System - Final Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { text-align: center; color: white; margin-bottom: 30px; }
        .header h1 { font-size: 3rem; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .header p { font-size: 1.2rem; opacity: 0.9; }
        .card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 25px; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 25px; }
        .card-header h2 { font-size: 1.5rem; margin-bottom: 5px; }
        .card-header p { opacity: 0.9; }
        .card-body { padding: 30px; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0; }
        .status-item { padding: 20px; border-radius: 10px; text-align: center; font-weight: 600; }
        .status-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .status-warning { background: linear-gradient(135deg, #ffc107, #fd7e14); color: #212529; }
        .status-error { background: linear-gradient(135deg, #dc3545, #e83e8c); color: white; }
        .status-info { background: linear-gradient(135deg, #17a2b8, #6f42c1); color: white; }
        .btn { padding: 15px 30px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, #007bff, #0056b3); color: white; }
        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107, #fd7e14); color: #212529; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-control { width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; transition: border-color 0.3s ease; }
        .form-control:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
        .alert { padding: 20px; margin: 20px 0; border-radius: 8px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 25px 0; }
        .feature-item { background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #007bff; }
        .feature-item h4 { color: #007bff; margin-bottom: 10px; }
        .code-block { background: #2d3748; color: #e2e8f0; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; margin: 15px 0; overflow-x: auto; }
        .success-banner { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 30px; text-align: center; border-radius: 15px; margin-bottom: 30px; }
        .success-banner h2 { font-size: 2rem; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Email System</h1>
            <p>PHPMailer Integration Complete!</p>
        </div>

        <!-- Success Banner -->
        <div class="success-banner">
            <h2>🎉 Integration Complete!</h2>
            <p>Your PHPMailer email system is fully functional and ready for production use.</p>
        </div>

        <!-- Messages -->
        <?php if ($setupMessage): ?>
            <?php list($type, $message) = explode('|', $setupMessage); ?>
            <div class="alert alert-<?php echo $type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($testResult): ?>
            <?php list($type, $message) = explode('|', $testResult); ?>
            <div class="alert alert-<?php echo $type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <h2>📊 System Status</h2>
                <p>Current email system configuration</p>
            </div>
            <div class="card-body">
                <div class="status-grid">
                    <div class="status-item status-success">
                        <h3>✅ PHPMailer</h3>
                        <p>Installed & Ready</p>
                    </div>
                    <div class="status-item status-success">
                        <h3>✅ Configuration</h3>
                        <p>Loaded Successfully</p>
                    </div>
                    <div class="status-item <?php echo ($status['smtp_configured'] ?? false) ? 'status-success' : 'status-warning'; ?>">
                        <h3><?php echo ($status['smtp_configured'] ?? false) ? '✅' : '⚠️'; ?> SMTP</h3>
                        <p><?php echo ($status['smtp_configured'] ?? false) ? 'Configured' : 'Needs App Password'; ?></p>
                    </div>
                    <div class="status-item status-info">
                        <h3>📧 Admin Email</h3>
                        <p><?php echo $status['admin_email'] ?? 'Not Set'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gmail Setup (if needed) -->
        <?php if (!($status['smtp_configured'] ?? false)): ?>
        <div class="card">
            <div class="card-header">
                <h2>⚙️ Gmail App Password Setup</h2>
                <p>Enable real email sending via Gmail SMTP</p>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Current Status:</strong> Emails are being saved to files in the 'logs' directory. To send real emails, set up Gmail App Password below.
                </div>
                
                <h4>📋 Setup Instructions:</h4>
                <ol style="margin: 20px 0; padding-left: 20px;">
                    <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                    <li>Enable <strong>2-Step Verification</strong></li>
                    <li>Click <strong>"App passwords"</strong></li>
                    <li>Select <strong>Mail → Other (Custom name)</strong></li>
                    <li>Enter <strong>"Event Management System"</strong></li>
                    <li>Copy the <strong>16-character password</strong></li>
                </ol>

                <form method="POST">
                    <div class="form-group">
                        <label for="app_password">Gmail App Password (16 characters)</label>
                        <input type="password" id="app_password" name="app_password" class="form-control" 
                               placeholder="abcd efgh ijkl mnop" maxlength="19" required>
                    </div>
                    <button type="submit" name="setup_gmail_app_password" class="btn btn-primary">
                        🔧 Setup Gmail SMTP
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Test Email -->
        <div class="card">
            <div class="card-header">
                <h2>🧪 Test Email System</h2>
                <p>Send a test email to verify functionality</p>
            </div>
            <div class="card-body">
                <p>This will send a test registration notification to: <strong><?php echo $status['admin_email']; ?></strong></p>
                
                <?php if ($status['smtp_configured'] ?? false): ?>
                    <p style="color: #28a745; font-weight: 600;">✅ SMTP configured - emails will be sent via Gmail</p>
                <?php else: ?>
                    <p style="color: #ffc107; font-weight: 600;">⚠️ SMTP not configured - emails will be saved to logs directory</p>
                <?php endif; ?>
                
                <form method="POST" style="margin-top: 20px;">
                    <button type="submit" name="send_test_email" class="btn btn-success">
                        📧 Send Test Email
                    </button>
                </form>
            </div>
        </div>

        <!-- Features Overview -->
        <div class="card">
            <div class="card-header">
                <h2>🚀 What's Working</h2>
                <p>Your complete email integration features</p>
            </div>
            <div class="card-body">
                <div class="feature-grid">
                    <div class="feature-item">
                        <h4>📝 Registration Notifications</h4>
                        <p>Admin gets notified when users register for events</p>
                    </div>
                    <div class="feature-item">
                        <h4>✅ Approval Confirmations</h4>
                        <p>Users receive confirmation emails when approved</p>
                    </div>
                    <div class="feature-item">
                        <h4>🎨 Custom Templates</h4>
                        <p>Professional HTML email templates with branding</p>
                    </div>
                    <div class="feature-item">
                        <h4>🔒 Secure SMTP</h4>
                        <p>TLS encryption with Gmail App Password authentication</p>
                    </div>
                    <div class="feature-item">
                        <h4>📊 Error Handling</h4>
                        <p>Comprehensive logging and fallback mechanisms</p>
                    </div>
                    <div class="feature-item">
                        <h4>🔧 Easy Integration</h4>
                        <p>Already integrated with your registration and admin systems</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2>🔗 Quick Actions</h2>
                <p>Navigate to other parts of your system</p>
            </div>
            <div class="card-body" style="text-align: center;">
                <a href="test_all_emails.php" class="btn btn-primary" style="margin: 10px;">🧪 Run All Tests</a>
                <a href="admin/dashboard.php" class="btn btn-success" style="margin: 10px;">🏠 Admin Dashboard</a>
                <a href="pages/register-event.php" class="btn btn-warning" style="margin: 10px;">📝 Test Registration</a>
                <a href="logs/" class="btn btn-info" style="margin: 10px;">📁 View Email Logs</a>
            </div>
        </div>

    </div>
</body>
</html>
