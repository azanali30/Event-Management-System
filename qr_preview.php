<?php
/**
 * QR Code Preview and Download Page
 * Shows QR code preview with download button
 */

// Start session
session_start();

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Get registration ID
$registration_id = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);
if (!$registration_id) {
    die('Invalid registration ID');
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Fetch registration details
    $stmt = $pdo->prepare("
        SELECT 
            r.id as registration_id,
            r.user_id,
            r.event_id,
            r.status,
            r.registration_date,
            r.qr_code,
            u.first_name,
            u.last_name,
            u.email,
            e.title as event_name,
            e.event_date,
            e.start_time,
            e.venue,
            e.category
        FROM registration r
        JOIN users u ON r.user_id = u.id
        JOIN events e ON r.event_id = e.id
        WHERE r.id = ?
    ");
    
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        die('Registration not found');
    }
    
    // Authorization check
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['role'];
    $is_admin = ($current_user_role === 'admin');
    $is_owner = ($current_user_id == $registration['user_id']);
    
    if (!$is_admin && !$is_owner) {
        die('Access denied');
    }
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?php echo htmlspecialchars($registration['event_name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; }
        .event-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .qr-section { text-align: center; margin: 30px 0; }
        .qr-code { border: 2px solid #ddd; padding: 20px; border-radius: 10px; display: inline-block; background: white; }
        .download-btn { background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-size: 16px; display: inline-block; margin: 10px; }
        .download-btn:hover { background: #0056b3; }
        .success-btn { background: #28a745; }
        .success-btn:hover { background: #1e7e34; }
        .info-row { margin: 10px 0; }
        .label { font-weight: bold; color: #333; }
        .status { padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .back-btn { background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
        .back-btn:hover { background: #545b62; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎫 Event QR Code</h1>
            <p>Registration ID: <strong><?php echo $registration['registration_id']; ?></strong></p>
        </div>
        
        <div class="event-info">
            <h2><?php echo htmlspecialchars($registration['event_name']); ?></h2>
            
            <div class="info-row">
                <span class="label">Participant:</span>
                <?php echo htmlspecialchars($registration['first_name'] . ' ' . $registration['last_name']); ?>
            </div>
            
            <div class="info-row">
                <span class="label">Email:</span>
                <?php echo htmlspecialchars($registration['email']); ?>
            </div>
            
            <div class="info-row">
                <span class="label">Event Date:</span>
                <?php echo date('F j, Y', strtotime($registration['event_date'])); ?>
                <?php if ($registration['start_time']): ?>
                    at <?php echo date('g:i A', strtotime($registration['start_time'])); ?>
                <?php endif; ?>
            </div>
            
            <div class="info-row">
                <span class="label">Venue:</span>
                <?php echo htmlspecialchars($registration['venue']); ?>
            </div>
            
            <div class="info-row">
                <span class="label">Category:</span>
                <?php echo ucfirst($registration['category']); ?>
            </div>
            
            <div class="info-row">
                <span class="label">Status:</span>
                <span class="status status-<?php echo strtolower($registration['status']); ?>">
                    <?php echo strtoupper($registration['status']); ?>
                </span>
            </div>
            
            <div class="info-row">
                <span class="label">Registered:</span>
                <?php echo date('F j, Y g:i A', strtotime($registration['registration_date'])); ?>
            </div>
        </div>
        
        <div class="qr-section">
            <h3>Your QR Code</h3>
            <p>Present this QR code at the event for quick check-in</p>
            
            <div class="qr-code">
                <div id="qr-placeholder" style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border: 2px dashed #ddd;">
                    <p>Click "Generate QR Code" to create your QR code</p>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button onclick="generateQR()" class="download-btn">
                    📱 Generate QR Code
                </button>
                
                <a href="download_qr.php?registration_id=<?php echo $registration_id; ?>" 
                   class="download-btn success-btn" id="download-btn" style="display: none;">
                    💾 Download QR Code
                </a>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="javascript:history.back()" class="back-btn">← Go Back</a>
            
            <?php if ($is_admin): ?>
                <a href="admin/dashboard.php" class="back-btn" style="background: #dc3545;">
                    👑 Admin Dashboard
                </a>
            <?php else: ?>
                <a href="user/dashboard.php" class="back-btn" style="background: #007bff;">
                    👤 My Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function generateQR() {
        const placeholder = document.getElementById('qr-placeholder');
        const downloadBtn = document.getElementById('download-btn');
        
        // Show loading
        placeholder.innerHTML = '<p>Generating QR Code...</p>';
        
        // Create image element
        const img = new Image();
        img.onload = function() {
            placeholder.innerHTML = '';
            placeholder.appendChild(img);
            downloadBtn.style.display = 'inline-block';
        };
        
        img.onerror = function() {
            placeholder.innerHTML = '<p style="color: red;">Failed to generate QR code</p>';
        };
        
        // Set image source to trigger QR generation
        img.src = 'download_qr.php?registration_id=<?php echo $registration_id; ?>&preview=1';
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
    }
    
    // Auto-generate QR code if registration has confirmed status
    <?php if ($registration['status'] === 'confirmed'): ?>
    window.onload = function() {
        generateQR();
    };
    <?php endif; ?>
    </script>
</body>
</html>
