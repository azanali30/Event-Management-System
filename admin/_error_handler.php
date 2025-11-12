<?php
/**
 * Centralized Error Handling and Validation Utilities
 * Provides consistent error handling, input validation, and security measures
 */

class ErrorHandler {
    private static $errors = [];
    private static $notices = [];
    
    /**
     * Add an error message
     */
    public static function addError($message) {
        self::$errors[] = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Add a notice message
     */
    public static function addNotice($message) {
        self::$notices[] = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get all errors
     */
    public static function getErrors() {
        return self::$errors;
    }
    
    /**
     * Get all notices
     */
    public static function getNotices() {
        return self::$notices;
    }
    
    /**
     * Check if there are any errors
     */
    public static function hasErrors() {
        return !empty(self::$errors);
    }
    
    /**
     * Clear all errors and notices
     */
    public static function clear() {
        self::$errors = [];
        self::$notices = [];
    }
    
    /**
     * Get success message (alias for getNotices)
     */
    public static function getSuccessMessage() {
        return self::getNotices();
    }
    
    /**
     * Display all error and notice messages as HTML
     */
    public static function displayMessages() {
        $output = '';
        
        // Display errors
        foreach (self::$errors as $error) {
            $output .= '<div class="alert alert-danger">' . $error . '</div>';
        }
        
        // Display notices
        foreach (self::$notices as $notice) {
            $output .= '<div class="alert alert-success">' . $notice . '</div>';
        }
        
        return $output;
    }
    
    /**
     * Log error to file and optionally display user-friendly message
     */
    public static function logError($error, $userMessage = null) {
        // Log technical error
        error_log(date('Y-m-d H:i:s') . ' - ' . $error . PHP_EOL, 3, '../logs/error.log');
        
        // Add user-friendly message
        if ($userMessage) {
            self::addError($userMessage);
        } else {
            self::addError('An unexpected error occurred. Please try again.');
        }
    }
}

class InputValidator {
    /**
     * Validate and sanitize integer input
     */
    public static function validateInt($input, $min = null, $max = null) {
        $value = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return null;
        }
        
        if ($min !== null && $value < $min) {
            return null;
        }
        
        if ($max !== null && $value > $max) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate and sanitize email input
     */
    public static function validateEmail($input) {
        return filter_var(trim($input), FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate and sanitize string input
     */
    public static function validateString($input, $minLength = 0, $maxLength = null) {
        $value = trim(strip_tags($input));
        
        if (strlen($value) < $minLength) {
            return null;
        }
        
        if ($maxLength !== null && strlen($value) > $maxLength) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate enum values (like status fields)
     */
    public static function validateEnum($input, $allowedValues) {
        $value = trim($input);
        return in_array($value, $allowedValues, true) ? $value : null;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = null) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        if ($maxSize && $file['size'] > $maxSize) {
            ErrorHandler::addError('File size exceeds maximum allowed size.');
            return null;
        }
        
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        if (!empty($allowedTypes) && !in_array($extension, $allowedTypes)) {
            ErrorHandler::addError('File type not allowed.');
            return null;
        }
        
        return $file;
    }
    
    /**
     * Sanitize HTML output
     */
    public static function sanitizeOutput($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * General sanitize method for input data
     */
    public static function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate integer with alias method name
     */
    public static function validateInteger($input, $min = null, $max = null) {
        return self::validateInt($input, $min, $max);
    }
}

class SecurityHelper {
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Rate limiting check (simple implementation)
     */
    public static function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
        $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        
        // Clean old entries
        if (isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = array_filter(
                $_SESSION['rate_limit'][$key],
                function($timestamp) use ($now, $timeWindow) {
                    return ($now - $timestamp) < $timeWindow;
                }
            );
        } else {
            $_SESSION['rate_limit'][$key] = [];
        }
        
        // Check if limit exceeded
        if (count($_SESSION['rate_limit'][$key]) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $_SESSION['rate_limit'][$key][] = $now;
        return true;
    }
    
    /**
     * Secure redirect
     */
    public static function redirect($url) {
        // Prevent open redirect vulnerabilities
        if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\/]/', $url)) {
            $url = '/admin/dashboard.php';
        }
        
        header('Location: ' . $url);
        exit();
    }
}

class DatabaseHelper {
    /**
     * Execute query with error handling
     */
    public static function executeQuery($pdo, $query, $params = [], $userErrorMessage = null) {
        try {
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new PDOException('Query execution failed');
            }
            
            return $stmt;
        } catch (PDOException $e) {
            ErrorHandler::logError(
                'Database error: ' . $e->getMessage() . ' Query: ' . $query,
                $userErrorMessage ?: 'Database operation failed. Please try again.'
            );
            return false;
        }
    }
    
    /**
     * Begin transaction with error handling
     */
    public static function beginTransaction($pdo) {
        try {
            return $pdo->beginTransaction();
        } catch (PDOException $e) {
            ErrorHandler::logError('Failed to begin transaction: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Commit transaction with error handling
     */
    public static function commitTransaction($pdo) {
        try {
            return $pdo->commit();
        } catch (PDOException $e) {
            ErrorHandler::logError('Failed to commit transaction: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rollback transaction with error handling
     */
    public static function rollbackTransaction($pdo) {
        try {
            return $pdo->rollback();
        } catch (PDOException $e) {
            ErrorHandler::logError('Failed to rollback transaction: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute query and fetch all results
     */
    public static function fetchAll($pdo, $query, $params = [], $userErrorMessage = null) {
        try {
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new PDOException('Query execution failed');
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            ErrorHandler::logError(
                'Database error: ' . $e->getMessage() . ' Query: ' . $query,
                $userErrorMessage ?: 'Database operation failed. Please try again.'
            );
            return false;
        }
    }
    
    /**
     * Execute query and fetch single result
     */
    public static function fetchOne($pdo, $query, $params = [], $userErrorMessage = null) {
        try {
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new PDOException('Query execution failed');
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            ErrorHandler::logError(
                'Database error: ' . $e->getMessage() . ' Query: ' . $query,
                $userErrorMessage ?: 'Database operation failed. Please try again.'
            );
            return false;
        }
    }
}

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0755, true);
}

?>