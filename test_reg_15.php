<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Checking registration 15:\n";
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE registration_id = 15');
    $stmt->execute();
    $reg = $stmt->fetch();
    
    if ($reg) {
        echo "Registration 15 found:\n";
        echo "- ID: " . $reg['registration_id'] . "\n";
        echo "- Student ID: " . $reg['student_id'] . "\n";
        echo "- Event ID: " . $reg['event_id'] . "\n";
        echo "- Status: " . $reg['status'] . "\n";
        echo "- UID: " . $reg['uid'] . "\n";
        echo "- QR Path: " . $reg['qr_path'] . "\n";
        
        // Test the download QR functionality
        require_once 'includes/qr_code_generator.php';
        
        echo "\nTesting QR code generation...\n";
        $result = QRCodeGenerator::downloadQRCode(15, $pdo);
        
        if ($result['success']) {
            echo "QR code generation successful!\n";
            echo "Filename: " . $result['filename'] . "\n";
            echo "MIME type: " . $result['mime_type'] . "\n";
            echo "Data length: " . strlen(base64_decode($result['qr_data'])) . " bytes\n";
        } else {
            echo "QR code generation failed: " . $result['error'] . "\n";
        }
    } else {
        echo "Registration 15 not found!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
