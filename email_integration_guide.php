<?php
/**
 * Email Integration Guide and Examples
 * 
 * This file shows how to integrate the EmailService class into your existing
 * user registration and admin panel functionality.
 */

require_once 'includes/EmailService.php';

/**
 * Example 1: Integration with User Registration
 * 
 * Add this code to your user registration handler
 */
function handleUserRegistration($userData) {
    try {
        // Your existing registration logic here
        // ... save user to database ...
        
        // Initialize email service
        $emailService = new EmailService();
        
        // Send admin notification about new registration
        $registrationData = [
            'name' => $userData['name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'] ?? 'Not provided',
            'event_name' => $userData['event_name'] ?? 'General Registration',
            'registration_date' => date('Y-m-d H:i:s'),
            'registration_id' => $userData['id'] ?? 'PENDING'
        ];
        
        $adminNotificationSent = $emailService->sendNewRegistrationNotification($registrationData);
        
        if ($adminNotificationSent) {
            echo "Registration successful! Admin has been notified.";
        } else {
            echo "Registration successful! (Note: Admin notification failed - check logs)";
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        echo "Registration failed. Please try again.";
        return false;
    }
}

/**
 * Example 2: Integration with Admin Approval System
 * 
 * Add this code to your admin panel approval handler
 */
function approveUserRegistration($userId) {
    try {
        // Your existing approval logic here
        // ... update user status in database ...
        
        // Get user data from database
        $userData = getUserById($userId); // Your existing function
        
        if (!$userData) {
            throw new Exception("User not found");
        }
        
        // Initialize email service
        $emailService = new EmailService();
        
        // Prepare approval data
        $approvalData = [
            'approval_date' => date('Y-m-d H:i:s'),
            'approved_by' => $_SESSION['admin_name'] ?? 'Admin',
            'approval_id' => 'APR-' . time()
        ];
        
        // Send approval confirmation to user
        $confirmationSent = $emailService->sendApprovalConfirmation($userData, $approvalData);
        
        if ($confirmationSent) {
            echo "User approved successfully! Confirmation email sent.";
        } else {
            echo "User approved successfully! (Note: Confirmation email failed - check logs)";
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Approval error: " . $e->getMessage());
        echo "Approval failed. Please try again.";
        return false;
    }
}

/**
 * Example 3: Custom Email Notifications
 */
function sendCustomNotification($userEmail, $userName, $eventType, $eventData) {
    try {
        $emailService = new EmailService();
        
        // Define different email templates based on event type
        switch ($eventType) {
            case 'payment_received':
                $templateData = [
                    'title' => 'Payment Confirmation',
                    'heading' => '💳 Payment Received Successfully',
                    'content' => "
                        <div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                            <h3 style='color: #155724; margin-top: 0;'>Payment Confirmed</h3>
                            <p style='color: #155724;'>Dear {$userName},</p>
                            <p style='color: #155724;'>We have successfully received your payment of <strong>\${$eventData['amount']}</strong> for {$eventData['event_name']}.</p>
                            <p style='color: #155724; margin-bottom: 0;'>Transaction ID: <strong>{$eventData['transaction_id']}</strong></p>
                        </div>
                    "
                ];
                break;
                
            case 'event_reminder':
                $templateData = [
                    'title' => 'Event Reminder',
                    'heading' => '📅 Upcoming Event Reminder',
                    'content' => "
                        <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                            <h3 style='color: #856404; margin-top: 0;'>Don't Forget!</h3>
                            <p style='color: #856404;'>Dear {$userName},</p>
                            <p style='color: #856404;'>This is a friendly reminder about your upcoming event:</p>
                            <p style='color: #856404;'><strong>{$eventData['event_name']}</strong></p>
                            <p style='color: #856404;'>Date: <strong>{$eventData['event_date']}</strong></p>
                            <p style='color: #856404; margin-bottom: 0;'>Location: <strong>{$eventData['location']}</strong></p>
                        </div>
                    "
                ];
                break;
                
            default:
                $templateData = [
                    'title' => 'Notification',
                    'heading' => '📢 System Notification',
                    'content' => "<p>Dear {$userName},</p><p>{$eventData['message']}</p>"
                ];
        }
        
        $subject = $templateData['title'] . ' - ' . ($eventData['event_name'] ?? 'Event Management System');
        
        return $emailService->sendCustomEmail($userEmail, $userName, $subject, $templateData, strtoupper($eventType));
        
    } catch (Exception $e) {
        error_log("Custom notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function - Get user by ID (implement based on your database structure)
 */
function getUserById($userId) {
    // This is a placeholder - implement based on your database structure
    // Example:
    /*
    global $pdo; // Your database connection
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
    */
    
    // For demonstration purposes, return sample data
    return [
        'id' => $userId,
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'phone' => '123-456-7890',
        'event_name' => 'Sample Event',
        'registration_date' => date('Y-m-d H:i:s')
    ];
}

// Example usage (uncomment to test):
/*
// Test the email service status
$emailService = new EmailService();
$status = $emailService->getStatus();
print_r($status);

// Send a test email
$result = $emailService->testConfiguration();
echo $result ? "Test email sent!" : "Test email failed!";

// Example user registration
$newUser = [
    'name' => 'Jane Smith',
    'email' => 'jane.smith@example.com',
    'phone' => '987-654-3210',
    'event_name' => 'Tech Conference 2024'
];
handleUserRegistration($newUser);
*/

?>
