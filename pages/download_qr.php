<?php
/**
 * Download QR Code endpoint for users
 * Downloads QR code as SVG file
 */

// Clean output buffer if it exists
if (ob_get_level()) {
    ob_end_clean();
}

require_once '../config/database.php';
require_once '../includes/qr_code_generator.php';

// Start session to check user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo '<html><body><h1>Error</h1><p>User not authenticated</p></body></html>';
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
    
    // Verify registration belongs to current user or user is admin
    $stmt = $pdo->prepare("
        SELECT
            r.registration_id,
            r.student_id,
            r.event_id,
            r.status,
            u.email as student_email,
            ud.full_name as student_name,
            e.title as event_title,
            e.event_date,
            e.event_time,
            e.venue,
            e.category
        FROM registrations r
        JOIN events e ON r.event_id = e.event_id
        JOIN users u ON r.student_id = u.user_id
        LEFT JOIN userdetails ud ON u.user_id = ud.user_id
        WHERE r.registration_id = ? AND (r.student_id = ? OR ? = 'admin') AND r.status IN ('confirmed', 'waitlist')
    ");

    $user_role = $_SESSION['role'] ?? 'participant';
    $stmt->execute([$registration_id, $user_id, $user_role]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        // Provide more detailed error information
        $debug_stmt = $pdo->prepare("
            SELECT r.*, u.email, ud.full_name
            FROM registrations r
            JOIN users u ON r.student_id = u.user_id
            LEFT JOIN userdetails ud ON u.user_id = ud.user_id
            WHERE r.registration_id = ?
        ");
        $debug_stmt->execute([$registration_id]);
        $debug_reg = $debug_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$debug_reg) {
            throw new Exception('Registration not found');
        } elseif ($debug_reg['status'] !== 'confirmed' && $debug_reg['status'] !== 'waitlist') {
            throw new Exception('Registration not confirmed or access denied. Status: ' . $debug_reg['status']);
        } elseif ($debug_reg['student_id'] != $user_id && $user_role !== 'admin') {
            throw new Exception('Access denied. This registration belongs to another user.');
        } else {
            throw new Exception('Registration not found, not confirmed, or access denied');
        }
    }
    
    // Use QRCodeGenerator to handle download
    $download_result = QRCodeGenerator::downloadQRCode($registration_id, $pdo);
    
    if (!$download_result['success']) {
        throw new Exception($download_result['error']);
    }
    
    // Set headers for file download
    header('Content-Type: ' . $download_result['mime_type']);
    header('Content-Disposition: attachment; filename="' . $download_result['filename'] . '"');
    header('Content-Length: ' . strlen(base64_decode($download_result['qr_data'])));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Output the QR code image
    echo base64_decode($download_result['qr_data']);
    exit;
    
} catch (Exception $e) {
    // Return error page
    http_response_code(400);
    echo '<html><body><h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
}
