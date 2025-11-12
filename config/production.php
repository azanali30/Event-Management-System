<?php
/**
 * Production PHP Configuration
 * Include this at the top of your main files
 */

// Disable error display in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Set error reporting level
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set timezone
date_default_timezone_set('UTC'); // Change to your timezone

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Enable if using HTTPS
ini_set('session.use_strict_mode', 1);

// Hide PHP version
header_remove('X-Powered-By');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// HTTPS redirect (uncomment if using HTTPS)
// if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
//     $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
//     header("Location: $redirectURL");
//     exit();
// }
?>