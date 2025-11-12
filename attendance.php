<?php
/**
 * Attendance Marking Page
 * Handles QR code scanning and attendance marking
 */

require_once 'config/database.php';

$message = '';
$messageType = '';
$attendanceMarked = false;
$userInfo = null;

if (isset($_GET['uid'])) {
    try {
        $uid = $_GET['uid'];
        
        // Validate UID format
        if (!preg_match('/^[A-Z0-9]+$/', $uid)) {
            throw new Exception('Invalid QR code format');
        }
        
        $db = new Database();
        $pdo = $db->getConnection();
        
        // Get registration details
        $stmt = $pdo->prepare("
            SELECT 
                r.registration_id,
                r.event_id,
                r.student_id,
                r.uid,
                r.attendance_status,
                r.attendance_time,
                e.title as event_title,
                e.event_date,
                e.event_time,
                e.venue,
                u.email,
                ud.full_name,
                ud.phone
            FROM registrations r 
            JOIN events e ON r.event_id = e.event_id 
            JOIN users u ON r.student_id = u.user_id 
            LEFT JOIN userdetails ud ON u.user_id = ud.user_id 
            WHERE r.uid = ? AND r.status = 'confirmed'
        ");
        $stmt->execute([$uid]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$registration) {
            throw new Exception('Invalid or expired QR code');
        }
        
        $userInfo = $registration;
        
        // Check if attendance already marked
        if ($registration['attendance_status'] === 'present') {
            $message = 'Attendance already marked for ' . ($registration['full_name'] ?: $registration['email']) . 
                      ' at ' . date('M j, Y g:i A', strtotime($registration['attendance_time']));
            $messageType = 'info';
            $attendanceMarked = true;
        } else {
            // Mark attendance
            $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            $updateStmt = $pdo->prepare("
                UPDATE registrations 
                SET attendance_status = 'present', 
                    attendance_time = CURRENT_TIMESTAMP, 
                    attendance_ip = ? 
                WHERE registration_id = ?
            ");
            $updateStmt->execute([$clientIP, $registration['registration_id']]);
            
            // Also insert into attendance table if it exists
            try {
                $attendanceStmt = $pdo->prepare("
                    INSERT INTO attendance (event_id, student_id, attended, marked_on) 
                    VALUES (?, ?, 1, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE attended = 1, marked_on = CURRENT_TIMESTAMP
                ");
                $attendanceStmt->execute([$registration['event_id'], $registration['student_id']]);
            } catch (Exception $e) {
                // Attendance table might not exist or have different structure
                error_log("Attendance table insert failed: " . $e->getMessage());
            }
            
            $message = 'Attendance marked successfully for ' . ($registration['full_name'] ?: $registration['email']) . 
                      ' at ' . date('M j, Y g:i A');
            $messageType = 'success';
            $attendanceMarked = true;
            
            // Update user info with new attendance status
            $userInfo['attendance_status'] = 'present';
            $userInfo['attendance_time'] = date('Y-m-d H:i:s');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
} else {
    $message = 'Invalid or missing QR code';
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .icon.success {
            color: #28a745;
        }

        .icon.error {
            color: #dc3545;
        }

        .icon.info {
            color: #17a2b8;
        }

        .message {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .message.success {
            color: #155724;
        }

        .message.error {
            color: #721c24;
        }

        .message.info {
            color: #0c5460;
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            text-align: left;
        }

        .user-info h3 {
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }

        .info-value {
            color: #6c757d;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-present {
            background: #d4edda;
            color: #155724;
        }

        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            .icon {
                font-size: 3rem;
            }
            
            .message {
                font-size: 1.1rem;
            }
            
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($messageType === 'success'): ?>
            <div class="icon success">✅</div>
        <?php elseif ($messageType === 'info'): ?>
            <div class="icon info">ℹ️</div>
        <?php else: ?>
            <div class="icon error">❌</div>
        <?php endif; ?>

        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

        <?php if ($userInfo): ?>
            <div class="user-info">
                <h3>Participant Details</h3>
                
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($userInfo['full_name'] ?: 'N/A'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($userInfo['email']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Event:</span>
                    <span class="info-value"><?php echo htmlspecialchars($userInfo['event_title']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value"><?php echo date('M j, Y', strtotime($userInfo['event_date'])); ?></span>
                </div>
                
                <?php if ($userInfo['event_time']): ?>
                <div class="info-row">
                    <span class="info-label">Time:</span>
                    <span class="info-value"><?php echo date('g:i A', strtotime($userInfo['event_time'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($userInfo['venue']): ?>
                <div class="info-row">
                    <span class="info-label">Venue:</span>
                    <span class="info-value"><?php echo htmlspecialchars($userInfo['venue']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label">Attendance:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo $userInfo['attendance_status']; ?>">
                            <?php echo ucfirst($userInfo['attendance_status']); ?>
                        </span>
                    </span>
                </div>
                
                <?php if ($userInfo['attendance_time']): ?>
                <div class="info-row">
                    <span class="info-label">Marked At:</span>
                    <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($userInfo['attendance_time'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>Event Management System</p>
            <p>Attendance tracking powered by QR codes</p>
        </div>
    </div>
</body>
</html>
