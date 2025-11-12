<?php
/**
 * Admin QR Code Download Script
 * Allows administrators to download QR codes for any registration
 */

// Clean any existing output
if (ob_get_level()) {
    ob_end_clean();
}

require_once 'config/config.php';
require_once 'config/database.php';

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate QR code using online API
 */
function generateQRCode($data) {
    $qr_text = urlencode($data);
    $api_url = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&format=png&data=" . $qr_text;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EventSphere QR Generator');
    
    $qr_png_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200 && $qr_png_data !== false && empty($curl_error)) {
        if (substr($qr_png_data, 0, 4) === "\x89PNG" && strlen($qr_png_data) > 100) {
            return $qr_png_data;
        } else {
            throw new Exception("API returned invalid PNG data");
        }
    } else {
        throw new Exception("Failed to generate QR code via API. HTTP: $http_code" . 
                          ($curl_error ? ", Error: $curl_error" : ""));
    }
}

try {
    // Check admin authentication
    $user_role = $_SESSION['role'] ?? 'visitor';
    
    if ($user_role !== 'admin') {
        throw new Exception('Access denied. Admin privileges required.');
    }
    
    // Get registration ID from URL
    $registration_id = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : 0;
    
    if ($registration_id <= 0) {
        throw new Exception('Invalid registration ID provided');
    }
    
    // Database connection
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get registration data (admin can access any registration)
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            student_id,
            r.student_name,
            r.event_id, 
            r.qr_code,
            e.title as event_title,
            e.event_date,
            e.event_time,
            e.venue,
            e.category
        FROM registration r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.id = ?
    ");
    
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        throw new Exception('Registration not found');
    }
    
    // Check if QR code already exists in database
    $qr_png_data = null;
    
    if ($registration['qr_code']) {
        // Decode existing QR code
        $decoded = base64_decode($registration['qr_code']);
        if (substr($decoded, 0, 4) === "\x89PNG" && strlen($decoded) > 100) {
            $qr_png_data = $decoded;
        }
    }
    
    // Generate new QR code if not exists or corrupted
    if (!$qr_png_data) {
        // Create QR data with registration information
        $qr_data = json_encode([
            'name' => $registration['student_name'],
            'student_id' => $registration['student_id'],
            'event' => $registration['event_title'],
            'date' => $registration['event_date'],
            'time' => $registration['event_time'],
            'venue' => $registration['venue'],
            'reg_id' => $registration['id'],
            'hash' => substr(md5($registration['student_id'] . $registration['event_title'] . $registration['event_date']), 0, 8)
        ]);
        
        // Generate QR code
        $qr_png_data = generateQRCode($qr_data);
        
        // Save to database for future use
        $base64_data = base64_encode($qr_png_data);
        $update_stmt = $pdo->prepare("UPDATE registration SET qr_code = ? WHERE id = ?");
        $update_stmt->execute([$base64_data, $registration_id]);
    }
    
    // Generate filename
    $safe_event_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['event_title']);
    $safe_student_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $registration['student_name']);
    $filename = "QR_{$safe_event_name}_{$safe_student_name}_{$registration_id}.png";
    
    // Set headers for file download
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($qr_png_data));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Pragma: no-cache');
    
    // Output the QR code image
    echo $qr_png_data;
    exit;
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Admin QR Download Error: " . $e->getMessage());
    
    // Return user-friendly error page
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin QR Code Download Error</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
            .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
            .error-title { color: #d32f2f; font-size: 24px; margin-bottom: 20px; }
            .error-message { color: #666; font-size: 16px; line-height: 1.5; margin-bottom: 20px; }
            .back-button { background: #1976d2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
            .back-button:hover { background: #1565c0; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1 class="error-title">Admin QR Code Download Error</h1>
            <p class="error-message"><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href="javascript:history.back()" class="back-button">Go Back</a>
        </div>
    </body>
    </html>
    <?php
}
?>
