<?php
/**
 * Secure QR Code Download Script
 * 
 * Features:
 * - Accepts registration_id via GET
 * - Session-based authentication and authorization
 * - Admin or owner access control
 * - PDO with prepared statements
 * - Endroid QR Code library for PNG generation
 * - JSON QR data with user ID, event name, registration ID, date
 * - Proper download headers
 * - Comprehensive error handling
 * - No HTML output - image only
 */

// Prevent any output buffering and clean existing buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);     // Log errors instead

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include required files
    require_once 'config/config.php';
    require_once 'config/database.php';
    require_once 'vendor/autoload.php';
    
    use Endroid\QrCode\QrCode;
    use Endroid\QrCode\Writer\PngWriter;
    use Endroid\QrCode\Encoding\Encoding;
    use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
    use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
    
    // Validate session
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        throw new Exception('Authentication required. Please log in.');
    }
    
    // Get and validate registration_id
    $registration_id = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);
    if ($registration_id === false || $registration_id <= 0) {
        http_response_code(400);
        throw new Exception('Invalid registration ID provided.');
    }
    
    // Database connection
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Determine which table structure to use based on what exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'event_registrations'")->rowCount();
    $use_event_registrations = $table_check > 0;
    
    if ($use_event_registrations) {
        // Use event_registrations table structure
        $sql = "
            SELECT 
                er.id as registration_id,
                er.user_id,
                er.event_id,
                er.registration_date,
                er.status,
                u.id as user_table_id,
                u.first_name,
                u.last_name,
                u.email,
                u.student_id,
                u.role as user_role,
                e.id as event_table_id,
                e.title as event_name,
                e.event_date,
                e.start_time,
                e.venue
            FROM event_registrations er
            JOIN users u ON er.user_id = u.id
            JOIN events e ON er.event_id = e.id
            WHERE er.id = ? AND er.status IN ('registered', 'attended')
        ";
    } else {
        // Use registration table structure (legacy)
        $sql = "
            SELECT 
                r.id as registration_id,
                r.student_id as user_id,
                r.event_id,
                r.registered_on as registration_date,
                r.status,
                r.student_name as full_name,
                u.user_id as user_table_id,
                u.email,
                u.role as user_role,
                e.event_id as event_table_id,
                e.title as event_name,
                e.event_date,
                e.event_time as start_time,
                e.venue
            FROM registration r
            LEFT JOIN users u ON r.student_id = u.user_id
            JOIN events e ON r.event_id = e.event_id
            WHERE r.id = ? AND r.status IN ('confirmed', 'approved')
        ";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        http_response_code(404);
        throw new Exception('Registration not found or not approved.');
    }
    
    // Authorization check
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['role'];
    $registration_user_id = $registration['user_id'];
    
    $is_admin = ($current_user_role === 'admin');
    $is_owner = ($current_user_id == $registration_user_id);
    
    if (!$is_admin && !$is_owner) {
        http_response_code(403);
        throw new Exception('Access denied. You can only download your own QR codes.');
    }
    
    // Prepare QR code data
    $qr_data = [
        'user_id' => $registration['user_id'],
        'event_name' => $registration['event_name'],
        'registration_id' => $registration['registration_id'],
        'date' => $registration['event_date'],
        'time' => $registration['start_time'] ?? null,
        'venue' => $registration['venue'] ?? null,
        'timestamp' => time(),
        'hash' => substr(md5($registration['user_id'] . $registration['event_name'] . $registration['registration_id']), 0, 8)
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
    
    // Get PNG data
    $png_data = $result->getString();
    
    // Validate PNG data
    if (empty($png_data) || substr($png_data, 0, 4) !== "\x89PNG") {
        http_response_code(500);
        throw new Exception('Failed to generate valid QR code.');
    }
    
    // Generate safe filename
    $event_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['event_name']);
    $user_name_safe = '';
    
    if ($use_event_registrations) {
        $user_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', 
            $registration['first_name'] . '_' . $registration['last_name']);
    } else {
        $user_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', 
            $registration['full_name'] ?? 'User');
    }
    
    $filename = "QR_{$event_name_safe}_{$user_name_safe}_{$registration_id}.png";
    
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
    // Log the error
    error_log("QR Download Error: " . $e->getMessage() . " | User: " . ($_SESSION['user_id'] ?? 'unknown') . " | Registration: " . ($registration_id ?? 'unknown'));
    
    // Set appropriate HTTP status code if not already set
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    
    // For security, don't output detailed error messages in production
    // Instead, create a simple error image
    $error_message = "QR Code Error";
    
    // Create a simple error QR code
    try {
        if (class_exists('Endroid\QrCode\QrCode')) {
            $error_qr = QrCode::create($error_message)
                ->setSize(200)
                ->setMargin(10);
            
            $writer = new PngWriter();
            $result = $writer->write($error_qr);
            
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="qr_error.png"');
            header('Content-Length: ' . strlen($result->getString()));
            
            echo $result->getString();
        } else {
            // Fallback: create minimal PNG error image
            $img = imagecreate(200, 50);
            $bg = imagecolorallocate($img, 255, 255, 255);
            $text_color = imagecolorallocate($img, 255, 0, 0);
            imagestring($img, 3, 50, 20, 'QR Error', $text_color);
            
            header('Content-Type: image/png');
            imagepng($img);
            imagedestroy($img);
        }
    } catch (Exception $fallback_error) {
        // Ultimate fallback - just send error status
        http_response_code(500);
        exit;
    }
}

// Ensure no additional output
exit;
?>
