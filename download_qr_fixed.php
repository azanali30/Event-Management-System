<?php
/**
 * Secure QR Code Download System
 * Works with actual registration table structure (student_id, not user_id)
 * 
 * Features:
 * - Uses correct 'registration' table with student_id
 * - Secure user authentication and authorization
 * - Admin can access all QR codes, users only their own
 * - Generates QR codes with registration details
 * - Proper download headers and error handling
 * - SQL injection protection with prepared statements
 */

// Use statements must be at top level
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;

// Prevent any output before headers
if (ob_get_level()) {
    ob_end_clean();
}

// Error handling - log errors but don't display them
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Include required files
require_once 'config/config.php';
require_once 'config/database.php';

/**
 * Generate QR code using available library
 */
function generateQRCode($data, $size = 400) {
    // Try Endroid library first if available
    if (class_exists('Endroid\QrCode\QrCode')) {
        try {
            $qr_code = QrCode::create($data)
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium())
                ->setSize($size)
                ->setMargin(10);
            
            $writer = new PngWriter();
            $result = $writer->write($qr_code);
            return $result->getString();
        } catch (Exception $e) {
            // Fall back to API if Endroid fails
        }
    }
    
    // Fallback to online API
    $qr_text = urlencode($data);
    $api_url = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&ecc=M&format=png&data=" . $qr_text;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Event Management System');
    
    $qr_png_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200 && $qr_png_data !== false && empty($curl_error)) {
        if (substr($qr_png_data, 0, 4) === "\x89PNG" && strlen($qr_png_data) > 100) {
            return $qr_png_data;
        }
    }
    
    throw new Exception('Failed to generate QR code');
}

/**
 * Main execution
 */
try {
    // 1. Check authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        throw new Exception('Authentication required. Please log in to access QR codes.');
    }
    
    // 2. Get and validate registration_id
    $registration_id = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);
    if (!$registration_id || $registration_id <= 0) {
        http_response_code(400);
        throw new Exception('Invalid or missing registration ID.');
    }
    
    // 3. Database connection
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 4. Fetch registration with event details using correct table structure
    $stmt = $pdo->prepare("
        SELECT 
            r.id as registration_id,
            r.student_id,
            r.event_id,
            r.student_name,
            r.student_email,
            r.status,
            r.registered_on,
            r.approved_at,
            r.qr_code,
            r.payment_screenshot,
            r.payment_details,
            e.title as event_name,
            e.event_date,
            e.event_time,
            e.venue,
            e.category,
            e.description
        FROM registration r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.id = ? AND r.status IN ('confirmed', 'approved', 'pending')
    ");
    
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        http_response_code(404);
        throw new Exception('Registration not found or not approved.');
    }
    
    // 5. Authorization check - Admin or Owner only
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['role'];
    $registration_student_id = $registration['student_id'];
    
    $is_admin = ($current_user_role === 'admin');
    $is_owner = ($current_user_id == $registration_student_id);
    
    if (!$is_admin && !$is_owner) {
        http_response_code(403);
        throw new Exception('Access denied. You can only download your own QR codes.');
    }
    
    // 6. Generate QR code data with registration details
    $qr_data = json_encode([
        'registration_id' => $registration['registration_id'],
        'student_id' => $registration['student_id'],
        'event_id' => $registration['event_id'],
        'event_name' => $registration['event_name'],
        'student_name' => $registration['student_name'],
        'student_email' => $registration['student_email'],
        'event_date' => $registration['event_date'],
        'event_time' => $registration['event_time'],
        'venue' => $registration['venue'],
        'status' => $registration['status'],
        'generated_at' => date('Y-m-d H:i:s'),
        'hash' => substr(md5($registration['registration_id'] . $registration['student_id'] . $registration['event_id'] . 'event_qr_salt'), 0, 8)
    ], JSON_UNESCAPED_UNICODE);
    
    // 7. Check if QR code exists in database
    $qr_png_data = null;
    if ($registration['qr_code']) {
        $decoded = base64_decode($registration['qr_code']);
        if (substr($decoded, 0, 4) === "\x89PNG" && strlen($decoded) > 100) {
            $qr_png_data = $decoded;
        }
    }
    
    // 8. Generate new QR code if needed
    if (!$qr_png_data) {
        $qr_png_data = generateQRCode($qr_data, 400);
        
        // Save to database for future use
        $base64_qr = base64_encode($qr_png_data);
        $update_stmt = $pdo->prepare("UPDATE registration SET qr_code = ? WHERE id = ?");
        $update_stmt->execute([$base64_qr, $registration_id]);
    }
    
    // 9. Validate PNG data
    if (empty($qr_png_data) || substr($qr_png_data, 0, 4) !== "\x89PNG") {
        http_response_code(500);
        throw new Exception('Failed to generate valid QR code.');
    }
    
    // 10. Generate safe filename
    $event_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['event_name']);
    $student_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['student_name']);
    $filename = "QR_{$event_name_safe}_{$student_name_safe}_{$registration_id}.png";
    
    // 11. Set download headers
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($qr_png_data));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    
    // 12. Output PNG data
    echo $qr_png_data;
    
} catch (Exception $e) {
    // Log the error with context
    $error_context = [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'registration_id' => $registration_id ?? 'unknown',
        'user_role' => $_SESSION['role'] ?? 'unknown',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    error_log("QR Download Error: " . json_encode($error_context));
    
    // Set appropriate HTTP status if not already set
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    
    // Create error QR code as fallback
    try {
        $error_message = "QR Code Error\nRegistration: " . ($registration_id ?? 'Unknown');
        $error_qr = generateQRCode($error_message, 200);
        
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qr_error.png"');
        header('Content-Length: ' . strlen($error_qr));
        
        echo $error_qr;
    } catch (Exception $fallback_error) {
        // Ultimate fallback - create simple error image
        $img = imagecreate(200, 100);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $text_color = imagecolorallocate($img, 255, 0, 0);
        imagestring($img, 3, 10, 40, 'QR Code Error', $text_color);
        
        header('Content-Type: image/png');
        imagepng($img);
        imagedestroy($img);
    }
}

// Ensure clean exit
exit;
?>
