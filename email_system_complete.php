<?php
/**
 * Complete Email System Setup and Test Page
 * 
 * This page provides a comprehensive interface for setting up and testing
 * the PHPMailer-based email notification system.
 */

require_once 'includes/EmailService.php';

// Handle form submissions
$results = [];
$emailService = null;

try {
    $emailService = new EmailService();
    $status = $emailService->getStatus();
} catch (Exception $e) {
    $results['error'] = 'Failed to initialize EmailService: ' . $e->getMessage();
    $status = [];
}

// Handle Gmail App Password Update
if (isset($_POST['update_gmail_password']) && !empty($_POST['gmail_password'])) {
    $gmail_password = $_POST['gmail_password'];
    
    // Update the email configuration file
    $config_content = "<?php
/**
 * Email Configuration
 * Configure your email settings here
 */

return [
    // Admin notification email
    'admin_email' => 'codisticsolutions@gmail.com',
    
    // SMTP Settings (Gmail with App Password)
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'codisticsolutions@gmail.com',  // Your Gmail address
        'password' => '{$gmail_password}',       // Gmail App Password
        'encryption' => 'tls'
    ],
    
    // From email settings
    'from' => [
        'email' => 'codisticsolutions@gmail.com',    // Your Gmail address
        'name' => 'Event Management System'
    ],
    
    // Email templates
    'templates' => [
        'header_color' => '#007bff',
        'footer_color' => '#333333',
        'system_name' => 'Event Management System'
    ],
    
    // Notification settings
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

    if (file_put_contents('config/email_config.php', $config_content)) {
        $results['config_update'] = 'SUCCESS - Gmail App Password updated successfully!';
        // Reinitialize email service with new config
        try {
            $emailService = new EmailService();
            $status = $emailService->getStatus();
        } catch (Exception $e) {
            $results['config_update'] = 'Config updated but service initialization failed: ' . $e->getMessage();
        }
    } else {
        $results['config_update'] = 'FAILED - Could not update configuration file';
    }
}

// Handle test emails
if (isset($_POST['test_type']) && $emailService) {
    $test_type = $_POST['test_type'];
    
    switch ($test_type) {
        case 'registration':
            $testData = [
                'name' => 'Test Student',
                'email' => 'teststudent@example.com',
                'phone' => '123-456-7890',
                'event_name' => 'Email System Test Event',
                'registration_date' => date('Y-m-d H:i:s'),
                'registration_id' => 'TEST-' . time()
            ];
            $result = $emailService->sendNewRegistrationNotification($testData);
            $results['test_registration'] = $result ? 'SUCCESS' : 'FAILED';
            break;
            
        case 'approval':
            $userData = [
                'name' => 'Test Student',
                'email' => 'codisticsolutions@gmail.com', // Send to admin for testing
                'event_name' => 'Email System Test Event'
            ];
            $approvalData = [
                'approval_date' => date('Y-m-d H:i:s'),
                'approved_by' => 'Test Admin',
                'approval_id' => 'APR-' . time()
            ];
            $result = $emailService->sendApprovalConfirmation($userData, $approvalData);
            $results['test_approval'] = $result ? 'SUCCESS' : 'FAILED';
            break;
            
        case 'custom':
            $templateData = [
                'title' => 'System Test Email',
                'heading' => '🧪 Email System Test',
                'content' => "
                    <div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='color: #2e7d32; margin-top: 0;'>Email System Working!</h3>
                        <p style='color: #2e7d32;'>This is a test email to verify that your PHPMailer integration is working correctly.</p>
                        <p style='color: #2e7d32;'>Test sent at: " . date('Y-m-d H:i:s') . "</p>
                        <p style='color: #2e7d32; margin-bottom: 0;'>✅ PHPMailer is properly configured and sending emails!</p>
                    </div>
                "
            ];
            $result = $emailService->sendCustomEmail(
                'codisticsolutions@gmail.com',
                'System Administrator',
                'Email System Test - ' . date('H:i:s'),
                $templateData,
                'SYSTEM_TEST'
            );
            $results['test_custom'] = $result ? 'SUCCESS' : 'FAILED';
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Email System Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 40px; background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 40px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,123,255,0.3); }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .header p { font-size: 1.1rem; opacity: 0.9; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin: 30px 0; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.3s ease; }
        .card:hover { transform: translateY(-5px); }
        .card-header { padding: 20px; border-bottom: 1px solid #eee; }
        .card-header h3 { color: #007bff; margin-bottom: 5px; }
        .card-body { padding: 25px; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .status-item { padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; }
        .status-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s ease; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; transition: border-color 0.3s ease; }
        .form-control:focus { outline: none; border-color: #007bff; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 6px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .code-block { background: #f8f9fa; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; border-left: 4px solid #007bff; margin: 15px 0; }
        .feature-list { list-style: none; }
        .feature-list li { padding: 8px 0; }
        .feature-list li:before { content: '✅'; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Complete Email System</h1>
            <p>PHPMailer Integration with Secure SMTP & Professional Templates</p>
        </div>

        <!-- Results Display -->
        <?php if (!empty($results)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>📊 Operation Results</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($results as $operation => $result): ?>
                        <div class="alert <?php echo strpos($result, 'SUCCESS') !== false ? 'alert-success' : 'alert-error'; ?>">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $operation)); ?>:</strong> <?php echo $result; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- System Status -->
            <div class="card">
                <div class="card-header">
                    <h3>🔍 System Status</h3>
                    <p>Current email system configuration status</p>
                </div>
                <div class="card-body">
                    <div class="status-grid">
                        <div class="status-item <?php echo ($status['phpmailer_available'] ?? false) ? 'status-success' : 'status-error'; ?>">
                            <?php echo ($status['phpmailer_available'] ?? false) ? '✅ PHPMailer' : '❌ PHPMailer'; ?>
                        </div>
                        <div class="status-item <?php echo ($status['config_loaded'] ?? false) ? 'status-success' : 'status-error'; ?>">
                            <?php echo ($status['config_loaded'] ?? false) ? '✅ Config' : '❌ Config'; ?>
                        </div>
                        <div class="status-item <?php echo ($status['smtp_configured'] ?? false) ? 'status-success' : 'status-warning'; ?>">
                            <?php echo ($status['smtp_configured'] ?? false) ? '✅ SMTP' : '⚠️ SMTP'; ?>
                        </div>
                        <div class="status-item status-info">
                            📧 <?php echo $status['admin_email'] ?? 'Not Set'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gmail Setup -->
            <div class="card">
                <div class="card-header">
                    <h3>⚙️ Gmail SMTP Setup</h3>
                    <p>Configure Gmail App Password for email sending</p>
                </div>
                <div class="card-body">
                    <?php if (!($status['smtp_configured'] ?? false)): ?>
                        <div class="alert alert-error">
                            <strong>Setup Required!</strong> Gmail App Password not configured.
                        </div>
                        <form method="POST">
                            <div class="form-group">
                                <label for="gmail_password">Gmail App Password (16 characters)</label>
                                <input type="password" id="gmail_password" name="gmail_password" class="form-control" 
                                       placeholder="abcd efgh ijkl mnop" maxlength="19" required>
                                <small style="color: #666; margin-top: 5px; display: block;">
                                    Get this from <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a>
                                </small>
                            </div>
                            <button type="submit" name="update_gmail_password" class="btn btn-primary">
                                Update Gmail Settings
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <strong>✅ Gmail SMTP Configured!</strong> Ready to send emails.
                        </div>
                        <p><strong>SMTP Host:</strong> smtp.gmail.com</p>
                        <p><strong>Port:</strong> 587 (TLS)</p>
                        <p><strong>Username:</strong> codisticsolutions@gmail.com</p>
                        <p><strong>Status:</strong> ✅ Ready</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Email Tests -->
            <div class="card">
                <div class="card-header">
                    <h3>🧪 Email Tests</h3>
                    <p>Test different email notification types</p>
                </div>
                <div class="card-body">
                    <?php if ($status['smtp_configured'] ?? false): ?>
                        <div style="display: grid; gap: 15px;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="test_type" value="registration">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    📝 Test Registration Notification
                                </button>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    Sends admin notification for new user registration
                                </small>
                            </form>

                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="test_type" value="approval">
                                <button type="submit" class="btn btn-success" style="width: 100%;">
                                    ✅ Test Approval Confirmation
                                </button>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    Sends approval confirmation to user
                                </small>
                            </form>

                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="test_type" value="custom">
                                <button type="submit" class="btn btn-warning" style="width: 100%;">
                                    🎨 Test Custom Email
                                </button>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    Sends custom template email
                                </small>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-error">
                            <strong>Setup Required!</strong> Configure Gmail SMTP first to test emails.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Features -->
            <div class="card">
                <div class="card-header">
                    <h3>🚀 Email System Features</h3>
                    <p>What's included in this integration</p>
                </div>
                <div class="card-body">
                    <ul class="feature-list">
                        <li>PHPMailer with Composer autoloading</li>
                        <li>Secure SMTP with TLS encryption (port 587)</li>
                        <li>Professional HTML email templates</li>
                        <li>Admin notifications for new registrations</li>
                        <li>User approval confirmation emails</li>
                        <li>Custom email template system</li>
                        <li>Error handling and logging</li>
                        <li>Reusable EmailService class</li>
                        <li>Gmail App Password integration</li>
                        <li>Dynamic content with user data</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Integration Guide -->
        <div class="card">
            <div class="card-header">
                <h3>💻 Integration Guide</h3>
                <p>How to use the EmailService in your code</p>
            </div>
            <div class="card-body">
                <h4>1. New User Registration:</h4>
                <div class="code-block">
require_once 'includes/EmailService.php';
$emailService = new EmailService();

$userData = [
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'event_name' => $event['title'],
    'registration_date' => date('Y-m-d H:i:s')
];

$emailService->sendNewRegistrationNotification($userData);
                </div>

                <h4>2. Admin Approval:</h4>
                <div class="code-block">
$emailService = new EmailService();

$userData = [
    'name' => $user['name'],
    'email' => $user['email'],
    'event_name' => $event['title']
];

$approvalData = [
    'approval_date' => date('Y-m-d H:i:s'),
    'approved_by' => $_SESSION['admin_name'],
    'approval_id' => 'APR-' . time()
];

$emailService->sendApprovalConfirmation($userData, $approvalData);
                </div>

                <h4>3. Custom Emails:</h4>
                <div class="code-block">
$templateData = [
    'title' => 'Custom Email',
    'heading' => 'Your Custom Message',
    'content' => '&lt;p&gt;Your custom HTML content here&lt;/p&gt;'
];

$emailService->sendCustomEmail($email, $name, $subject, $templateData);
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3>🔗 Quick Actions</h3>
                <p>Navigate to other parts of the system</p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <a href="test_email_service.php" class="btn btn-primary">Detailed Testing</a>
                    <a href="email_integration_guide.php" class="btn btn-success">Integration Examples</a>
                    <a href="admin/dashboard.php" class="btn btn-warning">Admin Dashboard</a>
                    <a href="pages/register-event.php" class="btn btn-info">Test Registration</a>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
