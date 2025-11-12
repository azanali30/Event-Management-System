<?php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;

// Clean any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Disable error display to prevent output before headers
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session
session_start();

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'vendor/autoload.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    // Get and validate registration_id
    $registration_id = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);
    if (!$registration_id || $registration_id <= 0) {
        http_response_code(400);
        throw new Exception('Invalid registration ID');
    }

    // Database connection
    $db = new Database();
    $pdo = $db->getConnection();

    // Fetch registration details with joined tables
    $stmt = $pdo->prepare("
        SELECT 
            r.id as registration_id,
            r.student_id as user_id,
            r.student_name,
            r.event_id,
            r.status,
            u.user_id as user_table_id,
            u.email,
            u.role as user_role,
            e.event_id as event_table_id,
            e.title as event_name,
            e.event_date,
            e.event_time,
            e.venue
        FROM registration r
        LEFT JOIN users u ON r.student_id = u.user_id
        JOIN events e ON r.event_id = e.event_id
        WHERE r.id = ? AND r.status IN ('confirmed', 'approved')
    ");
    
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        http_response_code(404);
        throw new Exception('Registration not found');
    }

    // Authorization check - admin or owner
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['role'];
    $registration_user_id = $registration['user_id'];

    $is_admin = ($current_user_role === 'admin');
    $is_owner = ($current_user_id == $registration_user_id);

    if (!$is_admin && !$is_owner) {
        http_response_code(403);
        throw new Exception('Access denied');
    }

    // Create QR code data as JSON
    $qr_data = [
        'registration_id' => $registration['registration_id'],
        'user_id' => $registration['user_id'],
        'event_name' => $registration['event_name'],
        'date' => $registration['event_date']
    ];

    $qr_json = json_encode($qr_data, JSON_UNESCAPED_UNICODE);

    // Generate QR code using Endroid library
    $qr_code = QrCode::create($qr_json)
        ->setEncoding(new Encoding('UTF-8'))
        ->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium())
        ->setSize(400)
        ->setMargin(10)
        ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

    $writer = new PngWriter();
    $result = $writer->write($qr_code);
    $png_data = $result->getString();

    // Validate PNG data
    if (empty($png_data) || substr($png_data, 0, 4) !== "\x89PNG") {
        http_response_code(500);
        throw new Exception('Failed to generate QR code');
    }

    // Generate safe filename
    $event_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['event_name']);
    $student_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['student_name']);
    $filename = "QR_{$event_name_safe}_{$student_name_safe}_{$registration_id}.png";

    // Set headers for PNG download
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($png_data));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output PNG data
    echo $png_data;

} catch (Exception $e) {
    // Log error
    error_log("QR Download Error: " . $e->getMessage() . " | User: " . ($_SESSION['user_id'] ?? 'unknown') . " | Registration: " . ($registration_id ?? 'unknown'));

    // Set appropriate HTTP status if not already set
    if (http_response_code() === 200) {
        http_response_code(500);
    }

    // Create error QR code
    try {
        $error_qr = QrCode::create('QR Code Error')
            ->setSize(200)
            ->setMargin(10);
        
        $writer = new PngWriter();
        $result = $writer->write($error_qr);
        
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qr_error.png"');
        header('Content-Length: ' . strlen($result->getString()));
        
        echo $result->getString();
    } catch (Exception $fallback_error) {
        // Ultimate fallback
        http_response_code(500);
        exit;
    }
}

exit;
?>
