<?php
/**
 * Force regenerate QR code for testing
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/qr_code_generator.php';

$registration_id = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : 14;

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get registration data
    $stmt = $pdo->prepare("
        SELECT r.*, e.title as event_title, e.event_date, e.event_time, e.venue
        FROM registration r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.id = ?
    ");
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        throw new Exception('Registration not found');
    }
    
    echo "<h2>Force Regenerating QR Code for Registration ID: " . $registration_id . "</h2>";
    echo "<p><strong>Student:</strong> " . htmlspecialchars($registration['student_name']) . "</p>";
    echo "<p><strong>Event:</strong> " . htmlspecialchars($registration['event_title']) . "</p>";
    
    // Clear existing QR code first
    $clear_stmt = $pdo->prepare("UPDATE registration SET qr_code = NULL WHERE id = ?");
    $clear_stmt->execute([$registration_id]);
    echo "<p>✅ Cleared existing QR code</p>";
    
    // Generate new QR code
    echo "<h3>Generating New QR Code...</h3>";
    $qr_result = QRCodeGenerator::generateRegistrationQR($registration);
    
    if ($qr_result['success']) {
        // Update database
        $update_stmt = $pdo->prepare("UPDATE registration SET qr_code = ? WHERE id = ?");
        $update_stmt->execute([$qr_result['qr_code_data'], $registration_id]);
        
        echo "<p style='color: green;'>✅ QR Code generated successfully!</p>";
        echo "<p><strong>QR Code length:</strong> " . strlen($qr_result['qr_code_data']) . " characters</p>";
        
        // Validate the QR code
        $decoded = base64_decode($qr_result['qr_code_data']);
        echo "<p><strong>Decoded size:</strong> " . strlen($decoded) . " bytes</p>";
        
        $is_png = (substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n");
        echo "<p><strong>Valid PNG:</strong> " . ($is_png ? "YES" : "NO") . "</p>";
        
        if ($is_png && strlen($decoded) > 1000) {
            echo "<p style='color: green;'>✅ QR Code looks good!</p>";
            
            // Display QR code
            echo "<h3>QR Code Preview:</h3>";
            echo "<img src='data:image/png;base64," . $qr_result['qr_code_data'] . "' alt='QR Code' style='border: 1px solid #ddd; padding: 10px; max-width: 400px;'>";
            
            // Test download
            echo "<h3>Test Download:</h3>";
            echo "<p><a href='test_qr_download_direct.php?registration_id=" . $registration_id . "' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;' target='_blank'>Download QR Code</a></p>";
            
        } else {
            echo "<p style='color: red;'>❌ QR Code still has issues</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Failed to generate QR code: " . $qr_result['error'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
