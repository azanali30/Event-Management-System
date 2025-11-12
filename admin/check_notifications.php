<?php
/**
 * Admin Notification Checker
 * Checks for new admin panel activities and sends email notifications
 * This script can be run periodically or called via AJAX
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/EmailNotification.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $emailNotifier = new EmailNotification();
    
    $notifications = [];
    $email_sent = false;
    
    // Check for new pending registrations (last 5 minutes)
    $stmt = $pdo->prepare("
        SELECT r.*, e.title as event_title, e.event_date, e.event_time, e.venue
        FROM registration r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.status = 'pending' 
        AND r.registered_on >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY r.registered_on DESC
    ");
    $stmt->execute();
    $new_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($new_registrations)) {
        $notifications['new_registrations'] = count($new_registrations);
        
        // Send summary email for multiple registrations
        if (count($new_registrations) > 1) {
            $summary_data = [
                'Total New Registrations' => count($new_registrations),
                'Time Period' => 'Last 5 minutes',
                'Check Time' => date('Y-m-d H:i:s'),
                'Registrations' => []
            ];
            
            foreach ($new_registrations as $reg) {
                $summary_data['Registrations'][] = [
                    'ID' => $reg['id'],
                    'Student' => $reg['student_name'],
                    'Event' => $reg['event_title'],
                    'Time' => $reg['registered_on']
                ];
            }
            
            $subject = 'Multiple New Registrations - Admin Action Required';
            $message = count($new_registrations) . ' new registrations require your attention in the admin panel.';
            $emailNotifier->sendAdminNotification($subject, $message, $summary_data);
            $email_sent = true;
        }
    }
    
    // Check for waitlist pending registrations
    $stmt = $pdo->prepare("
        SELECT r.*, e.title as event_title, e.event_date
        FROM registration r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.status = 'waitlist_pending'
        AND r.registered_on >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY r.registered_on DESC
    ");
    $stmt->execute();
    $waitlist_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($waitlist_registrations)) {
        $notifications['waitlist_pending'] = count($waitlist_registrations);
    }
    
    // Check for recent QR code downloads (last 10 minutes)
    $log_file = '../logs/qr_downloads.log';
    if (file_exists($log_file)) {
        $recent_downloads = [];
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoff_time = time() - (10 * 60); // 10 minutes ago
        
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $log_time = strtotime($matches[1]);
                if ($log_time >= $cutoff_time) {
                    $recent_downloads[] = $line;
                }
            }
        }
        
        if (!empty($recent_downloads)) {
            $notifications['recent_qr_downloads'] = count($recent_downloads);
        }
    }
    
    // Check for system errors (last 30 minutes)
    $error_log = '../logs/system_errors.log';
    if (file_exists($error_log)) {
        $recent_errors = [];
        $lines = file($error_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoff_time = time() - (30 * 60); // 30 minutes ago
        
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $log_time = strtotime($matches[1]);
                if ($log_time >= $cutoff_time && strpos($line, 'ERROR') !== false) {
                    $recent_errors[] = $line;
                }
            }
        }
        
        if (!empty($recent_errors)) {
            $notifications['recent_errors'] = count($recent_errors);
            
            // Send error notification if there are critical errors
            $critical_errors = array_filter($recent_errors, function($error) {
                return strpos($error, 'CRITICAL') !== false || strpos($error, 'FATAL') !== false;
            });
            
            if (!empty($critical_errors)) {
                $error_data = [
                    'Critical Errors' => count($critical_errors),
                    'Total Errors' => count($recent_errors),
                    'Time Period' => 'Last 30 minutes',
                    'Check Time' => date('Y-m-d H:i:s'),
                    'Sample Errors' => array_slice($critical_errors, 0, 3)
                ];
                
                $subject = 'Critical System Errors Detected';
                $message = 'Critical errors have been detected in the event management system.';
                $emailNotifier->sendAdminNotification($subject, $message, $error_data);
                $email_sent = true;
            }
        }
    }
    
    // Get total counts for dashboard
    $total_pending = (int)$pdo->query("SELECT COUNT(*) FROM registration WHERE status = 'pending'")->fetchColumn();
    $total_waitlist = (int)$pdo->query("SELECT COUNT(*) FROM registration WHERE status = 'waitlist_pending'")->fetchColumn();
    $total_approved = (int)$pdo->query("SELECT COUNT(*) FROM registration WHERE status = 'approved'")->fetchColumn();
    
    // Send daily summary if it's a new day and no summary sent today
    $last_summary_file = '../logs/last_daily_summary.txt';
    $today = date('Y-m-d');
    $last_summary_date = file_exists($last_summary_file) ? trim(file_get_contents($last_summary_file)) : '';
    
    if ($last_summary_date !== $today && date('H') >= 9) { // Send after 9 AM
        $daily_data = [
            'Date' => $today,
            'Total Pending Registrations' => $total_pending,
            'Total Waitlist Pending' => $total_waitlist,
            'Total Approved Today' => $pdo->query("SELECT COUNT(*) FROM registration WHERE status = 'approved' AND DATE(approved_at) = CURDATE()")->fetchColumn(),
            'New Registrations Today' => $pdo->query("SELECT COUNT(*) FROM registration WHERE DATE(registered_on) = CURDATE()")->fetchColumn(),
            'QR Codes Generated Today' => $pdo->query("SELECT COUNT(*) FROM registration WHERE status = 'approved' AND DATE(approved_at) = CURDATE() AND qr_code IS NOT NULL")->fetchColumn(),
            'Active Events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn(),
            'System Status' => 'Operational'
        ];
        
        $emailNotifier->notifyDailyReport($daily_data);
        file_put_contents($last_summary_file, $today);
        $email_sent = true;
    }
    
    // Response
    $response = [
        'success' => true,
        'notifications' => $notifications,
        'totals' => [
            'pending' => $total_pending,
            'waitlist_pending' => $total_waitlist,
            'approved' => $total_approved
        ],
        'email_sent' => $email_sent,
        'check_time' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'check_time' => date('Y-m-d H:i:s')
    ]);
}
?>
