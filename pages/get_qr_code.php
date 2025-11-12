<?php
/**
 * Get QR Code API endpoint for users
 * Returns QR code data for preview
 */

// Clean output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/qr_code_generator.php';

// Start session to check user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}



try {
    $registration_id = (int)($_GET['registration_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if ($registration_id <= 0) {
        throw new Exception('Invalid registration ID');
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get registration data - ensure it belongs to the current user
    $stmt = $pdo->prepare("
        SELECT r.*, e.title as event_title, e.event_date, e.event_time, e.venue
        FROM registration r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.id = ? AND r.student_id = ? AND r.status = 'approved'
    ");
    $stmt->execute([$registration_id, $user_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        throw new Exception('Registration not found, not approved, or access denied');
    }
    
    // Check if QR code exists
    if (empty($registration['qr_code'])) {
        // Generate QR code if it doesn't exist
        $qr_result = QRCodeGenerator::generateRegistrationQR($registration);
        
        if (!$qr_result['success']) {
            throw new Exception('Failed to generate QR code: ' . $qr_result['error']);
        }
        
        // Update database with QR code
        $update_stmt = $pdo->prepare("UPDATE registration SET qr_code = ? WHERE id = ?");
        $update_stmt->execute([$qr_result['qr_code_data'], $registration_id]);
        
        $qr_data = $qr_result['qr_code_data'];
    } else {
        $qr_data = $registration['qr_code'];
    }
    
    // Return QR code data
    echo json_encode([
        'success' => true,
        'qr_data_uri' => QRCodeGenerator::getQRCodeDataUri($qr_data),
        'student_name' => $registration['student_name'],
        'event_title' => $registration['event_title'],
        'event_date' => date('M j, Y', strtotime($registration['event_date'])),
        'event_time' => $registration['event_time'] ? date('g:i A', strtotime($registration['event_time'])) : 'TBD',
        'venue' => $registration['venue']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
