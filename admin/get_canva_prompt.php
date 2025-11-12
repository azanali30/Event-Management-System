<?php
/**
 * Get Canva QR Prompt for Registration
 */

session_start();
require_once '../config/database.php';
require_once '../includes/QRCodeGenerator.php';

// Check admin authentication
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Unauthorized access';
    exit;
}

if (!isset($_GET['registration_id'])) {
    http_response_code(400);
    echo 'Registration ID required';
    exit;
}

try {
    $registrationId = (int)$_GET['registration_id'];
    
    $db = new Database();
    $pdo = $db->getConnection();
    $qrGenerator = new QRCodeGenerator($pdo);
    
    $prompt = $qrGenerator->getCanvaPrompt($registrationId);
    
    header('Content-Type: text/plain');
    echo $prompt;
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
?>
