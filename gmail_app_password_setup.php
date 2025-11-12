<?php
/**
 * Gmail App Password Setup
 * Quick setup for Gmail SMTP authentication
 */

$message = '';
$success = false;

if (isset($_POST['setup_app_password'])) {
    $appPassword = trim($_POST['app_password']);
    
    if (!empty($appPassword)) {
        // Update the email configuration
        $configContent = "<?php
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
        'password' => '{$appPassword}',       // Gmail App Password
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

        if (file_put_contents('config/email_config.php', $configContent)) {
            $message = 'Gmail App Password updated successfully! Now testing email...';
            $success = true;
            
            // Test email sending
            try {
                require_once 'includes/EmailService.php';
                $emailService = new EmailService();
                
                $testData = [
                    'name' => 'Test User - Gmail SMTP',
                    'email' => 'testuser@example.com',
                    'phone' => '123-456-7890',
                    'event_name' => 'Gmail SMTP Test Event',
                    'registration_date' => date('Y-m-d H:i:s'),
                    'registration_id' => 'GMAIL-TEST-' . time()
                ];
                
                $result = $emailService->sendNewRegistrationNotification($testData);
                
                if ($result) {
                    $message .= '<br><br>✅ <strong>SUCCESS!</strong> Test email sent to codisticsolutions@gmail.com via Gmail SMTP!<br>📧 Check your Gmail inbox now!';
                } else {
                    $message .= '<br><br>❌ Test email failed. Check logs for details.';
                }
                
            } catch (Exception $e) {
                $message .= '<br><br>❌ Error testing email: ' . $e->getMessage();
            }
            
        } else {
            $message = 'Failed to update configuration file.';
        }
    } else {
        $message = 'Please enter a valid App Password.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmail App Password Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; color: #007bff; }
        .steps { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .steps h3 { color: #1976d2; margin-top: 0; }
        .steps ol { margin: 15px 0; padding-left: 20px; }
        .steps li { margin: 8px 0; }
        .form-group { margin: 20px 0; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; }
        .btn { padding: 15px 30px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #0056b3; }
        .message { padding: 20px; margin: 20px 0; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .current-status { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Gmail App Password Setup</h1>
            <p>Enable real email sending for your Event Management System</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="current-status">
            <h3>⚠️ Current Issue:</h3>
            <p><strong>Your emails are not being sent</strong> because Gmail requires an "App Password" for applications to send emails via SMTP. Your regular Gmail password doesn't work for this.</p>
        </div>

        <div class="steps">
            <h3>📋 How to Get Gmail App Password:</h3>
            <ol>
                <li><strong>Go to:</strong> <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                <li><strong>Enable 2-Step Verification</strong> (if not already enabled)</li>
                <li><strong>Click "App passwords"</strong> (appears after 2-Step Verification is enabled)</li>
                <li><strong>Select:</strong> Mail → Other (Custom name)</li>
                <li><strong>Enter:</strong> "Event Management System"</li>
                <li><strong>Click "Generate"</strong></li>
                <li><strong>Copy the 16-character password</strong> (like: abcd efgh ijkl mnop)</li>
                <li><strong>Paste it below and click "Setup Gmail SMTP"</strong></li>
            </ol>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="app_password">Gmail App Password (16 characters):</label>
                <input type="text" id="app_password" name="app_password" class="form-control" 
                       placeholder="abcd efgh ijkl mnop" maxlength="19" required
                       style="font-family: monospace; letter-spacing: 2px;">
                <small style="color: #666; margin-top: 5px; display: block;">
                    Enter the 16-character App Password from Google (spaces are optional)
                </small>
            </div>
            <button type="submit" name="setup_app_password" class="btn">
                🔧 Setup Gmail SMTP & Test Email
            </button>
        </form>

        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h4>🎯 What will happen:</h4>
            <ol>
                <li>Your email configuration will be updated with the App Password</li>
                <li>A test email will be sent immediately</li>
                <li>You'll see the result on this page</li>
                <li>Check your Gmail inbox for the test email</li>
                <li>All future emails will be sent via Gmail SMTP</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="email_system_final.php" style="color: #007bff; text-decoration: none;">← Back to Email System</a>
        </div>
    </div>
</body>
</html>
