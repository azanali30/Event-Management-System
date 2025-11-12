<?php
/**
 * QR Code Integration Example
 * Shows how to integrate the Canva-style QR generator into your existing system
 */

// Include your existing authentication and database files
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user's registrations
$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("
    SELECT 
        r.id as registration_id,
        r.student_id,
        r.student_name,
        r.student_email,
        r.status,
        e.title as event_name,
        e.event_date,
        e.event_time,
        e.venue,
        e.category
    FROM registration r
    INNER JOIN events e ON r.event_id = e.event_id
    WHERE r.student_id = ?
    ORDER BY r.created_at DESC
");

$stmt->execute([$_SESSION['user_id']]);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My QR Codes - Event Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .registration-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .event-info {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            align-items: start;
        }
        
        .event-details h3 {
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            color: #6c757d;
        }
        
        .detail-item i {
            width: 20px;
            margin-right: 10px;
        }
        
        .qr-section {
            text-align: center;
            min-width: 300px;
        }
        
        .qr-preview {
            width: 200px;
            height: 200px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            background: #f8f9fa;
        }
        
        .qr-preview img {
            max-width: 100%;
            max-height: 100%;
            border-radius: 8px;
        }
        
        .qr-controls {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .style-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .style-selector select {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        @media (max-width: 768px) {
            .event-info {
                grid-template-columns: 1fr;
            }
            
            .qr-section {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-qrcode"></i> My QR Codes</h1>
        <p>Generate and download QR codes for your event registrations</p>
    </div>
    
    <div class="container">
        <?php if (empty($registrations)): ?>
            <div class="registration-card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-calendar-times" style="font-size: 4rem; color: #dee2e6; margin-bottom: 1rem;"></i>
                    <h3>No Registrations Found</h3>
                    <p>You haven't registered for any events yet.</p>
                    <a href="events.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-calendar-plus"></i> Browse Events
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($registrations as $registration): ?>
                <div class="registration-card">
                    <div class="card-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h4><?php echo htmlspecialchars($registration['event_name']); ?></h4>
                            <span class="status-badge status-<?php echo $registration['status']; ?>">
                                <?php echo ucfirst($registration['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="event-info">
                            <div class="event-details">
                                <h3>Event Details</h3>
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('F j, Y', strtotime($registration['event_date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $registration['event_time'] ?: 'Time TBD'; ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($registration['venue']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo htmlspecialchars($registration['category'] ?: 'General'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-id-card"></i>
                                    <span>Registration #<?php echo $registration['registration_id']; ?></span>
                                </div>
                            </div>
                            
                            <div class="qr-section">
                                <h4>QR Code</h4>
                                <div class="qr-preview" id="qr-preview-<?php echo $registration['registration_id']; ?>">
                                    <p>Click Generate to create QR code</p>
                                </div>
                                
                                <div class="qr-controls">
                                    <div class="style-selector">
                                        <select id="style-<?php echo $registration['registration_id']; ?>">
                                            <option value="professional">Professional</option>
                                            <option value="modern">Modern</option>
                                            <option value="colorful">Colorful</option>
                                            <option value="minimal">Minimal</option>
                                        </select>
                                        
                                        <select id="color-<?php echo $registration['registration_id']; ?>">
                                            <option value="default">Default</option>
                                            <option value="blue">Blue</option>
                                            <option value="green">Green</option>
                                            <option value="red">Red</option>
                                            <option value="purple">Purple</option>
                                            <option value="orange">Orange</option>
                                        </select>
                                        
                                        <select id="size-<?php echo $registration['registration_id']; ?>">
                                            <option value="small">Small</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="large">Large</option>
                                            <option value="xl">XL</option>
                                        </select>
                                        
                                        <select id="frame-<?php echo $registration['registration_id']; ?>">
                                            <option value="none">No Frame</option>
                                            <option value="square">Square</option>
                                            <option value="rounded" selected>Rounded</option>
                                            <option value="circle">Circle</option>
                                        </select>
                                    </div>
                                    
                                    <button class="btn btn-primary" onclick="generateQR(<?php echo $registration['registration_id']; ?>)">
                                        <i class="fas fa-magic"></i> Generate QR
                                    </button>
                                    
                                    <div id="download-buttons-<?php echo $registration['registration_id']; ?>" style="display: none;">
                                        <a href="#" class="btn btn-success" id="download-link-<?php echo $registration['registration_id']; ?>">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <button class="btn btn-outline" onclick="shareQR(<?php echo $registration['registration_id']; ?>)">
                                            <i class="fas fa-share"></i> Share
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="canva_qr_generator.js"></script>
    <script>
        // Initialize QR generator
        const qrGenerator = new CanvaQRGenerator();
        
        // Registration data
        const registrations = <?php echo json_encode($registrations); ?>;
        
        async function generateQR(registrationId) {
            const registration = registrations.find(r => r.registration_id == registrationId);
            if (!registration) return;
            
            const preview = document.getElementById(`qr-preview-${registrationId}`);
            const downloadButtons = document.getElementById(`download-buttons-${registrationId}`);
            const downloadLink = document.getElementById(`download-link-${registrationId}`);
            
            // Get selected options
            const style = document.getElementById(`style-${registrationId}`).value;
            const color = document.getElementById(`color-${registrationId}`).value;
            const size = document.getElementById(`size-${registrationId}`).value;
            const frame = document.getElementById(`frame-${registrationId}`).value;
            
            // Show loading
            preview.innerHTML = '<i class="fas fa-spinner fa-spin"></i><br>Generating...';
            
            try {
                // Create QR data
                const qrData = QRUtils.createRegistrationData(registration);
                
                // Generate QR code
                const qrImageUrl = await qrGenerator.generateQR(qrData, {
                    style: style,
                    colorScheme: color,
                    size: size,
                    frameStyle: frame,
                    includeLogo: true
                });
                
                // Display QR code
                preview.innerHTML = `<img src="${qrImageUrl}" alt="QR Code">`;
                
                // Setup download link
                downloadLink.href = qrImageUrl;
                downloadLink.download = `QR_${registration.student_name}_${registration.event_name}_${registrationId}.png`;
                
                // Show download buttons
                downloadButtons.style.display = 'block';
                
            } catch (error) {
                console.error('QR generation failed:', error);
                preview.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: red;"></i><br>Generation failed';
            }
        }
        
        function shareQR(registrationId) {
            const qrImage = document.querySelector(`#qr-preview-${registrationId} img`);
            if (!qrImage) return;
            
            if (navigator.share) {
                // Use Web Share API if available
                qrImage.toBlob(blob => {
                    const file = new File([blob], `qr-code-${registrationId}.png`, { type: 'image/png' });
                    navigator.share({
                        title: 'Event QR Code',
                        text: 'My event registration QR code',
                        files: [file]
                    });
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(qrImage.src).then(() => {
                    alert('QR code link copied to clipboard!');
                });
            }
        }
    </script>
</body>
</html>
