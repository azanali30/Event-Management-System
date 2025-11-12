<?php
/**
 * User QR Code Dashboard
 * Shows user's own registrations and QR codes
 */

session_start();

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get user's registrations
    $stmt = $pdo->prepare("
        SELECT 
            r.id as registration_id,
            r.user_id,
            r.event_id,
            r.status,
            r.registration_date,
            r.qr_code,
            r.payment_status,
            e.title as event_name,
            e.event_date,
            e.start_time,
            e.venue,
            e.category,
            e.description
        FROM registration r
        JOIN events e ON r.event_id = e.id
        WHERE r.user_id = ?
        ORDER BY e.event_date ASC
    ");
    
    $stmt->execute([$user_id]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user details
    $user_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My QR Codes - Event Registration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f5f5f5; }
        .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 30px; text-align: center; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .welcome { background: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .registration-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
        .registration-card { background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.3s; }
        .registration-card:hover { transform: translateY(-5px); }
        .card-header { padding: 20px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 1px solid #ddd; }
        .card-body { padding: 20px; }
        .card-footer { padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #ddd; }
        .event-title { font-size: 1.2em; font-weight: bold; color: #333; margin-bottom: 10px; }
        .event-date { color: #007bff; font-weight: bold; }
        .status { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .qr-section { text-align: center; margin: 15px 0; }
        .qr-available { color: #28a745; font-weight: bold; }
        .qr-unavailable { color: #dc3545; }
        .btn { padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; margin: 5px; display: inline-block; transition: all 0.3s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .empty-state { text-align: center; padding: 60px 20px; color: #666; }
        .info-row { margin: 8px 0; }
        .label { font-weight: bold; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎫 My Event QR Codes</h1>
        <p>Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</p>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>📱 Your Event Registrations</h2>
            <p>Here are all your event registrations and their QR codes. Present these QR codes at events for quick check-in.</p>
        </div>
        
        <?php if (empty($registrations)): ?>
            <div class="empty-state">
                <h3>No Registrations Found</h3>
                <p>You haven't registered for any events yet.</p>
                <a href="../events.php" class="btn btn-primary">Browse Events</a>
            </div>
        <?php else: ?>
            <div class="registration-grid">
                <?php foreach ($registrations as $reg): ?>
                    <div class="registration-card">
                        <div class="card-header">
                            <div class="event-title"><?php echo htmlspecialchars($reg['event_name']); ?></div>
                            <div class="event-date">
                                📅 <?php echo date('F j, Y', strtotime($reg['event_date'])); ?>
                                <?php if ($reg['start_time']): ?>
                                    at <?php echo date('g:i A', strtotime($reg['start_time'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="info-row">
                                <span class="label">Venue:</span>
                                <?php echo htmlspecialchars($reg['venue']); ?>
                            </div>
                            
                            <div class="info-row">
                                <span class="label">Category:</span>
                                <?php echo ucfirst($reg['category']); ?>
                            </div>
                            
                            <div class="info-row">
                                <span class="label">Status:</span>
                                <span class="status status-<?php echo strtolower($reg['status']); ?>">
                                    <?php echo strtoupper($reg['status']); ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="label">Registered:</span>
                                <?php echo date('M j, Y', strtotime($reg['registration_date'])); ?>
                            </div>
                            
                            <div class="qr-section">
                                <?php if ($reg['qr_code'] && $reg['status'] === 'confirmed'): ?>
                                    <div class="qr-available">
                                        ✅ QR Code Ready
                                    </div>
                                <?php elseif ($reg['status'] === 'confirmed'): ?>
                                    <div class="qr-unavailable">
                                        📱 QR Code Available
                                    </div>
                                <?php elseif ($reg['status'] === 'pending'): ?>
                                    <div class="qr-unavailable">
                                        ⏳ Awaiting Confirmation
                                    </div>
                                <?php else: ?>
                                    <div class="qr-unavailable">
                                        ❌ QR Code Not Available
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <?php if ($reg['status'] === 'confirmed'): ?>
                                <a href="../qr_preview.php?registration_id=<?php echo $reg['registration_id']; ?>" 
                                   class="btn btn-info" target="_blank">
                                    👁️ View QR Code
                                </a>
                                
                                <a href="../download_qr.php?registration_id=<?php echo $reg['registration_id']; ?>" 
                                   class="btn btn-success" target="_blank">
                                    💾 Download QR
                                </a>
                            <?php elseif ($reg['status'] === 'pending'): ?>
                                <span style="color: #856404; font-size: 14px;">
                                    ⏳ Waiting for admin approval
                                </span>
                            <?php else: ?>
                                <span style="color: #721c24; font-size: 14px;">
                                    ❌ Registration <?php echo $reg['status']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div style="text-align: center; margin-top: 40px; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h3>Quick Actions</h3>
            <a href="../events.php" class="btn btn-primary">🎪 Browse More Events</a>
            <a href="../profile.php" class="btn btn-info">👤 Edit Profile</a>
            <button onclick="downloadAllMyQR()" class="btn btn-success">💾 Download All My QR Codes</button>
        </div>
        
        <!-- Help Section -->
        <div style="margin-top: 30px; padding: 20px; background: #e7f3ff; border-radius: 10px; border-left: 4px solid #007bff;">
            <h4>📖 How to Use Your QR Codes</h4>
            <ul style="text-align: left; max-width: 600px; margin: 0 auto;">
                <li><strong>Download:</strong> Click "Download QR" to save the QR code image to your device</li>
                <li><strong>Print:</strong> You can print the QR code for physical events</li>
                <li><strong>Mobile:</strong> Save the QR code to your phone's photo gallery for easy access</li>
                <li><strong>Check-in:</strong> Present the QR code at the event entrance for quick check-in</li>
                <li><strong>Backup:</strong> Keep a screenshot or printed copy as backup</li>
            </ul>
        </div>
    </div>
    
    <script>
    function downloadAllMyQR() {
        const confirmedRegistrations = [];
        
        // Find all confirmed registrations
        <?php foreach ($registrations as $reg): ?>
            <?php if ($reg['status'] === 'confirmed'): ?>
                confirmedRegistrations.push(<?php echo $reg['registration_id']; ?>);
            <?php endif; ?>
        <?php endforeach; ?>
        
        if (confirmedRegistrations.length === 0) {
            alert('No confirmed registrations found with QR codes.');
            return;
        }
        
        if (confirm(`Download QR codes for ${confirmedRegistrations.length} confirmed registration(s)?`)) {
            confirmedRegistrations.forEach((id, index) => {
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = `../download_qr.php?registration_id=${id}`;
                    link.download = `qr_code_${id}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }, index * 1000); // Stagger downloads by 1 second
            });
        }
    }
    
    // Auto-refresh page every 5 minutes to check for status updates
    setTimeout(() => {
        location.reload();
    }, 300000);
    </script>
</body>
</html>
