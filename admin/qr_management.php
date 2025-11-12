<?php
/**
 * QR Code Management for Admin Panel
 */

session_start();
require_once '../config/database.php';
require_once '../includes/QRCodeGenerator.php';

// Check admin authentication (adjust this based on your auth system)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $qrGenerator = new QRCodeGenerator($pdo);
    
    // Handle QR code generation
    if (isset($_GET['generate_qr'])) {
        $registrationId = (int)$_GET['generate_qr'];
        $result = $qrGenerator->generateQRCode($registrationId);
        
        if ($result['success']) {
            $message = 'QR Code generated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error generating QR Code: ' . $result['error'];
            $messageType = 'error';
        }
    }
    
    // Handle QR code deletion
    if (isset($_GET['delete_qr'])) {
        $registrationId = (int)$_GET['delete_qr'];
        if ($qrGenerator->deleteQRCode($registrationId)) {
            $message = 'QR Code deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deleting QR Code.';
            $messageType = 'error';
        }
    }
    
    // Get all registrations with user and event details
    $stmt = $pdo->prepare("
        SELECT 
            r.registration_id,
            r.event_id,
            r.student_id,
            r.registered_on,
            r.status,
            r.uid,
            r.qr_path,
            r.attendance_status,
            r.attendance_time,
            e.title as event_title,
            e.event_date,
            e.venue,
            u.email,
            ud.full_name,
            ud.phone
        FROM registrations r
        JOIN events e ON r.event_id = e.event_id
        JOIN users u ON r.student_id = u.user_id
        LEFT JOIN userdetails ud ON u.user_id = ud.user_id
        ORDER BY r.registered_on DESC
    ");
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'Database error: ' . $e->getMessage();
    $messageType = 'error';
    $registrations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Management - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #667eea;
            color: white;
            padding: 20px;
        }

        .table-header h2 {
            font-size: 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-waitlist {
            background: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .attendance-present {
            background: #d1ecf1;
            color: #0c5460;
        }

        .attendance-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 2px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .qr-preview {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .uid-display {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>QR Code Management</h1>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="registrations.php">Registrations</a>
                <a href="events.php">Events</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($registrations); ?></div>
                <div class="stat-label">Total Registrations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($registrations, fn($r) => $r['status'] === 'confirmed')); ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($registrations, fn($r) => !empty($r['qr_path']))); ?></div>
                <div class="stat-label">QR Codes Generated</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($registrations, fn($r) => $r['attendance_status'] === 'present')); ?></div>
                <div class="stat-label">Present</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>Registration Management</h2>
            </div>
            
            <?php if (empty($registrations)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    No registrations found.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Participant</th>
                            <th>Event</th>
                            <th>Status</th>
                            <th>UID</th>
                            <th>QR Code</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($reg['full_name'] ?: 'N/A'); ?></strong><br>
                                    <small><?php echo htmlspecialchars($reg['email']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($reg['event_title']); ?></strong><br>
                                    <small><?php echo date('M j, Y', strtotime($reg['event_date'])); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $reg['status']; ?>">
                                        <?php echo ucfirst($reg['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reg['uid']): ?>
                                        <span class="uid-display"><?php echo htmlspecialchars($reg['uid']); ?></span>
                                    <?php else: ?>
                                        <small style="color: #666;">Not generated</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($reg['qr_path'] && file_exists($reg['qr_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($reg['qr_path']); ?>" alt="QR Code" class="qr-preview">
                                    <?php else: ?>
                                        <small style="color: #666;">No QR code</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge attendance-<?php echo $reg['attendance_status']; ?>">
                                        <?php echo ucfirst($reg['attendance_status']); ?>
                                    </span>
                                    <?php if ($reg['attendance_time']): ?>
                                        <br><small><?php echo date('M j, g:i A', strtotime($reg['attendance_time'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($reg['status'] === 'confirmed'): ?>
                                        <?php if (empty($reg['qr_path'])): ?>
                                            <a href="?generate_qr=<?php echo $reg['registration_id']; ?>" class="btn btn-primary">Generate QR</a>
                                        <?php else: ?>
                                            <a href="../download_qr_code.php?uid=<?php echo $reg['uid']; ?>" class="btn btn-success">Download QR</a>
                                            <a href="?delete_qr=<?php echo $reg['registration_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete QR code?')">Delete QR</a>
                                        <?php endif; ?>
                                        <button class="btn btn-info" onclick="copyCanvaPrompt('<?php echo $reg['registration_id']; ?>')">Copy Canva Prompt</button>
                                    <?php else: ?>
                                        <small style="color: #666;">Not confirmed</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyCanvaPrompt(registrationId) {
            fetch(`get_canva_prompt.php?registration_id=${registrationId}`)
                .then(response => response.text())
                .then(prompt => {
                    navigator.clipboard.writeText(prompt).then(() => {
                        alert('Canva prompt copied to clipboard!');
                    }).catch(() => {
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = prompt;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        alert('Canva prompt copied to clipboard!');
                    });
                })
                .catch(error => {
                    alert('Error getting Canva prompt: ' + error);
                });
        }
    </script>
</body>
</html>
