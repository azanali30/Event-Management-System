<?php
require_once 'config/database.php';
require_once 'includes/QRCodeGenerator.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get a confirmed registration
    $stmt = $pdo->prepare("SELECT registration_id FROM registrations WHERE status = 'confirmed' LIMIT 1");
    $stmt->execute();
    $registration = $stmt->fetch();
    
    if (!$registration) {
        echo "No confirmed registrations found. Let me create a test one...\n";
        
        // Create a test registration
        $stmt = $pdo->prepare("INSERT INTO registrations (student_id, event_id, status, registration_date) VALUES (1, 1, 'confirmed', NOW())");
        $stmt->execute();
        $registration_id = $pdo->lastInsertId();
        echo "Created test registration with ID: $registration_id\n";
    } else {
        $registration_id = $registration['registration_id'];
        echo "Using existing registration ID: $registration_id\n";
    }
    
    // Generate QR code
    $generator = new QRCodeGenerator($pdo);
    $result = $generator->generateQRCode($registration_id);
    
    if ($result['success']) {
        echo "✅ QR Code generated successfully!\n";
        echo "UID: " . $result['uid'] . "\n";
        echo "QR Path: " . $result['qr_path'] . "\n";
        echo "Attendance URL: " . $result['attendance_url'] . "\n";
        echo "File exists: " . (file_exists($result['full_path']) ? 'YES' : 'NO') . "\n";
    } else {
        echo "❌ QR Code generation failed: " . $result['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
