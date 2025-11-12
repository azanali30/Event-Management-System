<?php
/**
 * Generate QR Code endpoint for users
 * Generates QR code for approved registrations that don't have one yet
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/QRCodeGenerator.php';

// Start session to check user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

try {
    $registration_id = (int)($_GET['registration_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if ($registration_id <= 0) {
        throw new Exception('Invalid registration ID');
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get registration data - ensure it belongs to the current user and is confirmed
    $stmt = $pdo->prepare("
        SELECT r.*, e.title as event_title, e.event_date, e.event_time, e.venue
        FROM registrations r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.registration_id = ? AND r.student_id = ? AND r.status = 'confirmed'
    ");
    $stmt->execute([$registration_id, $user_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registration not found, not confirmed, or access denied');
    }

    // Check if QR code already exists
    if (!empty($registration['qr_path'])) {
        echo json_encode([
            'success' => true,
            'message' => 'QR code already exists',
            'qr_path' => $registration['qr_path']
        ]);
        exit;
    }

    // Generate QR code
    $generator = new QRCodeGenerator($pdo);
    $qr_result = $generator->generateQRCode($registration_id);

    if (!$qr_result['success']) {
        throw new Exception('Failed to generate QR code: ' . $qr_result['error']);
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'QR code generated successfully',
        'uid' => $qr_result['uid'],
        'qr_path' => $qr_result['qr_path'],
        'attendance_url' => $qr_result['attendance_url']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
