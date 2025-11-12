<?php
/**
 * Download QR Code endpoint for admin
 * Downloads QR code as PNG file
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once '../includes/qr_code_generator.php';

// Check admin authentication
admin_require_login();

try {
    $registration_id = (int)($_GET['registration_id'] ?? 0);
    
    if ($registration_id <= 0) {
        throw new Exception('Invalid registration ID');
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Use QRCodeGenerator to handle download
    $download_result = QRCodeGenerator::downloadQRCode($registration_id, $pdo);
    
    if (!$download_result['success']) {
        throw new Exception($download_result['error']);
    }
    
    // Set headers for file download
    header('Content-Type: ' . $download_result['mime_type']);
    header('Content-Disposition: attachment; filename="' . $download_result['filename'] . '"');
    header('Content-Length: ' . strlen(base64_decode($download_result['qr_data'])));
    
    // Output the QR code image
    echo base64_decode($download_result['qr_data']);
    
} catch (Exception $e) {
    // Return error page
    http_response_code(400);
    echo '<html><body><h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
}
?>
