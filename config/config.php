<?php
// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'College Event Management System');
define('SITE_URL', 'http://localhost/event');
define('SITE_EMAIL', 'admin@college.edu');

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', 'uploads/');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);

// User roles
define('ROLE_VISITOR', 'visitor');
define('ROLE_PARTICIPANT', 'participant');
define('ROLE_ORGANIZER', 'organizer');
define('ROLE_ADMIN', 'admin');

// Event status
define('EVENT_DRAFT', 'draft');
define('EVENT_PENDING', 'pending');
define('EVENT_APPROVED', 'approved');
define('EVENT_ONGOING', 'ongoing');
define('EVENT_COMPLETED', 'completed');
define('EVENT_CANCELLED', 'cancelled');

// Include database connection
require_once 'database.php';

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? ROLE_VISITOR;
}

function hasPermission($required_role) {
    $roles = [ROLE_VISITOR, ROLE_PARTICIPANT, ROLE_ORGANIZER, ROLE_ADMIN];
    $user_role = getUserRole();
    
    $user_level = array_search($user_role, $roles);
    $required_level = array_search($required_role, $roles);
    
    return $user_level >= $required_level;
}

function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? ROLE_PARTICIPANT
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/pages/login.php');
        exit;
    }
}

function requirePermission($role) {
    if (!hasPermission($role)) {
        header('Location: ' . SITE_URL . '/pages/unauthorized.php');
        exit;
    }
}
