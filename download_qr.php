<?php
/**
 * QR Code Generator and Downloader
 * Event Management System
 *
 * Features:
 * - Generates high-resolution PNG QR codes using phpqrcode library
 * - Embeds registration details (ID, event, student info)
 * - Auto-downloads with proper filename
 * - Secure access control for logged-in users
 * - Clean output with proper headers
 * - High error correction and quality
 *
 * Requirements:
 * - phpqrcode library (phpqrcode/qrlib.php)
 * - Active user session
 * - Valid registration_id parameter
 */

// Start session for authentication
session_start();

// Security: Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Redirect to login page or show error
    http_response_code(401);
    die('Access denied. Please log in to download QR codes.');
}

// Include required files
require_once 'config/database.php';

// Try to include phpqrcode library from multiple possible locations
$phpqrcode_paths = [
    'phpqrcode/qrlib.php',
    'vendor/phpqrcode/phpqrcode/qrlib.php',
    'lib/phpqrcode/qrlib.php',
    'includes/phpqrcode/qrlib.php'
];

$phpqrcode_loaded = false;
foreach ($phpqrcode_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $phpqrcode_loaded = true;
        break;
    }
}

if (!$phpqrcode_loaded) {
    die('Error: phpqrcode library not found. Please install phpqrcode library.');
}

// Clean all output buffers to prevent image corruption
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffer
ob_start();

try {
    // Get and validate registration_id from URL
    $registration_id = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);

    if (!$registration_id || $registration_id <= 0) {
        throw new Exception('Invalid or missing registration ID');
    }

    // Database connection
    $db = new Database();
    $pdo = $db->getConnection();

    // Fetch registration details with event and student information
    $stmt = $pdo->prepare("
        SELECT
            r.registration_id,
            r.student_id,
            u.email as student_email,
            ud.full_name as student_name,
            r.status,
            e.title as event_name,
            e.event_date,
            e.event_time,
            e.venue,
            e.category
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.event_id
        INNER JOIN users u ON r.student_id = u.user_id
        LEFT JOIN userdetails ud ON u.user_id = ud.user_id
        WHERE r.registration_id = ?
        AND r.status IN ('confirmed', 'waitlist')
    ");

    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if registration exists
    if (!$registration) {
        throw new Exception('Registration not found or not approved');
    }

    // Additional security: Check if user has access to this registration
    // Admin can access all, regular users only their own
    $current_user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'user';

    if ($user_role !== 'admin' && $current_user_id != $registration['student_id']) {
        throw new Exception('Access denied. You can only download your own QR codes.');
    }

    // Prepare QR code data - compact format for better quality
    $qr_data = "REG:" . $registration['registration_id'] . "\n" .
               "EVENT:" . $registration['event_name'] . "\n" .
               "STUDENT:" . $registration['student_name'] . "\n" .
               "ID:" . $registration['student_id'] . "\n" .
               "DATE:" . $registration['event_date'] . "\n" .
               "TIME:" . ($registration['event_time'] ?? 'TBD') . "\n" .
               "VENUE:" . $registration['venue'] . "\n" .
               "STATUS:" . strtoupper($registration['status']) . "\n" .
               "HASH:" . substr(md5($registration['registration_id'] . $registration['student_id'] . 'qr_salt_key'), 0, 8);

    // Create safe filename components
    $student_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['student_name']);
    $event_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['event_name']);
    $filename = "QR_{$student_name_safe}_{$event_name_safe}_{$registration_id}.png";

    // Create temporary file path for QR code generation
    $temp_dir = sys_get_temp_dir();
    $temp_file = $temp_dir . DIRECTORY_SEPARATOR . 'qr_' . uniqid() . '.png';

    // QR Code generation parameters for MAXIMUM CLARITY
    $error_correction = QR_ECLEVEL_M; // Medium error correction (better for clarity)
    $pixel_size = 15;                 // Large pixel size for crystal clear output
    $frame_size = 8;                  // Large margin for perfect scanning

    // Generate QR code and save to temporary file
    QRcode::png($qr_data, $temp_file, $error_correction, $pixel_size, $frame_size);

    // Check if QR code was generated successfully
    if (!file_exists($temp_file) || filesize($temp_file) == 0) {
        throw new Exception('Failed to generate QR code');
    }

    // Get the generated QR code image data
    $qr_image_data = file_get_contents($temp_file);

    // Clean up temporary file
    unlink($temp_file);

    // Validate image data
    if (empty($qr_image_data) || strlen($qr_image_data) < 100) {
        throw new Exception('Generated QR code is invalid or corrupted');
    }

    // Verify it's a valid PNG
    if (substr($qr_image_data, 0, 4) !== "\x89PNG") {
        throw new Exception('Generated file is not a valid PNG image');
    }
    // Clean output buffer before sending headers
    ob_clean();

    // Set proper headers for PNG image download
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($qr_image_data));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    // Output the QR code image
    echo $qr_image_data;

    // Log successful download for audit purposes
    error_log("QR Code downloaded: Registration ID {$registration_id}, User ID {$current_user_id}, File: {$filename}");

    // Send email notification to admin
    try {
        require_once 'includes/EmailNotification.php';
        $emailNotifier = new EmailNotification();

        $qr_data_email = [
            'Registration ID' => $registration['registration_id'],
            'Student Name' => $registration['student_name'],
            'Student ID' => $registration['student_id'],
            'Event Name' => $registration['event_name'],
            'Event Date' => $registration['event_date'],
            'Downloaded By' => $current_user_role === 'admin' ? 'Admin User' : 'Student',
            'Download Time' => date('Y-m-d H:i:s'),
            'Download IP' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'User Agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100),
            'File Name' => $filename
        ];

        $emailNotifier->notifyQRCodeGenerated($qr_data_email);
    } catch (Exception $email_error) {
        // Don't let email errors affect QR download
        error_log("Email notification failed: " . $email_error->getMessage());
    }

} catch (Exception $e) {
    // Clean output buffer
    ob_clean();

    // Log error for debugging
    error_log("QR Code generation error: " . $e->getMessage() . " - User: " . ($_SESSION['user_id'] ?? 'unknown') . " - Registration: " . ($registration_id ?? 'unknown'));

    // Set appropriate HTTP status code
    http_response_code(400);

    // Create error QR code as fallback
    try {
        $error_message = "QR Code Error\nRegistration: " . ($registration_id ?? 'Unknown') . "\nError: " . $e->getMessage();
        $error_temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_error_' . uniqid() . '.png';

        // Generate simple error QR code
        QRcode::png($error_message, $error_temp_file, QR_ECLEVEL_L, 8, 2);

        if (file_exists($error_temp_file)) {
            $error_image_data = file_get_contents($error_temp_file);
            unlink($error_temp_file);

            // Send error QR code
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="qr_error.png"');
            header('Content-Length: ' . strlen($error_image_data));

            echo $error_image_data;
        } else {
            // Ultimate fallback - text error
            header('Content-Type: text/plain');
            echo "QR Code generation failed: " . $e->getMessage();
        }

    } catch (Exception $fallback_error) {
        // Final fallback - plain text error
        header('Content-Type: text/plain');
        echo "QR Code generation failed: " . $e->getMessage();
    }
}

// Ensure clean exit
ob_end_flush();
exit;
?>