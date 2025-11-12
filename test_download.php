<?php
// Simulate the download QR code process
require_once 'config/database.php';
require_once 'includes/qr_code_generator.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulate logged in user (user_id = 2, which is the student_id for registration 15)
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'participant';

try {
    $registration_id = 15;
    $user_id = $_SESSION['user_id'];
    
    echo "Testing download for registration_id: $registration_id, user_id: $user_id\n";
    
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
        echo "Registration not found, not confirmed, or access denied\n";
        echo "Checking what we have:\n";
        
        // Debug query
        $debug_stmt = $pdo->prepare("
            SELECT r.*, u.email, ud.full_name 
            FROM registrations r
            JOIN users u ON r.student_id = u.user_id
            LEFT JOIN userdetails ud ON u.user_id = ud.user_id
            WHERE r.registration_id = ?
        ");
        $debug_stmt->execute([$registration_id]);
        $debug_reg = $debug_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($debug_reg) {
            echo "Found registration but access denied:\n";
            print_r($debug_reg);
            echo "User ID: $user_id, Student ID: " . $debug_reg['student_id'] . "\n";
            echo "Status: " . $debug_reg['status'] . "\n";
            echo "User role: $user_role\n";
        } else {
            echo "No registration found at all\n";
        }
        exit;
    }
    
    echo "Registration found and access granted!\n";
    print_r($registration);
    
    // Use QRCodeGenerator to handle download
    $download_result = QRCodeGenerator::downloadQRCode($registration_id, $pdo);
    
    if (!$download_result['success']) {
        echo "QR generation failed: " . $download_result['error'] . "\n";
    } else {
        echo "QR generation successful!\n";
        echo "Filename: " . $download_result['filename'] . "\n";
        echo "Data size: " . strlen(base64_decode($download_result['qr_data'])) . " bytes\n";
        
        // Save the QR code to a file for testing
        $qr_data = base64_decode($download_result['qr_data']);
        file_put_contents('test_qr_15.png', $qr_data);
        echo "QR code saved as test_qr_15.png\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
