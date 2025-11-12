<?php
/**
 * Download QR Code File
 * Allows admin to download generated QR codes
 */

session_start();
require_once 'config/database.php';

// Check admin authentication (adjust based on your auth system)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Unauthorized access';
    exit;
}

if (!isset($_GET['uid'])) {
    http_response_code(400);
    echo 'UID parameter required';
    exit;
}

try {
    $uid = $_GET['uid'];
    
    // Validate UID format
    if (!preg_match('/^[A-Z0-9]+$/', $uid)) {
        throw new Exception('Invalid UID format');
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get registration details
    $stmt = $pdo->prepare("
        SELECT r.*, e.title as event_title, u.email, ud.full_name 
        FROM registrations r 
        JOIN events e ON r.event_id = e.event_id 
        JOIN users u ON r.student_id = u.user_id 
        LEFT JOIN userdetails ud ON u.user_id = ud.user_id 
        WHERE r.uid = ? AND r.status = 'confirmed'
    ");
    $stmt->execute([$uid]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        throw new Exception('Registration not found or not approved');
    }
    
    // Check if QR code file exists
    $qrPath = $registration['qr_path'];
    if (empty($qrPath)) {
        throw new Exception('QR code not generated yet');
    }
    
    $fullPath = __DIR__ . '/' . $qrPath;
    
    if (!file_exists($fullPath)) {
        throw new Exception('QR code file not found');
    }
    
    // Prepare download
    $fileName = 'QR_' . $uid . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $registration['event_title']) . '.png';
    
    // Set headers for download
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Output file
    readfile($fullPath);
    exit;
    
} catch (Exception $e) {
    http_response_code(404);
    echo 'Error: ' . $e->getMessage();
}
?>
