<?php
/**
 * Setup QR System
 * Run this file once to set up the QR code system
 */

require_once 'config/database.php';

echo "<h1>QR Code System Setup</h1>";

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "<h2>1. Updating Database Schema...</h2>";
    
    // Check if columns already exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM registrations LIKE 'uid'");
    $stmt->execute();
    $uidExists = $stmt->fetch();
    
    if (!$uidExists) {
        // Add QR code related columns
        $sql = "
        ALTER TABLE `registrations` 
        ADD COLUMN `uid` VARCHAR(50) UNIQUE NULL AFTER `status`,
        ADD COLUMN `qr_path` VARCHAR(255) NULL AFTER `uid`,
        ADD COLUMN `attendance_status` ENUM('absent', 'present') DEFAULT 'absent' AFTER `qr_path`,
        ADD COLUMN `attendance_time` TIMESTAMP NULL AFTER `attendance_status`,
        ADD COLUMN `attendance_ip` VARCHAR(45) NULL AFTER `attendance_time`
        ";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Database columns added successfully!</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Database columns already exist.</p>";
    }
    
    echo "<h2>2. Creating QR Codes Directory...</h2>";
    
    // Create qr_codes directory
    $qrDir = __DIR__ . '/qr_codes';
    if (!is_dir($qrDir)) {
        if (mkdir($qrDir, 0755, true)) {
            echo "<p style='color: green;'>✅ QR codes directory created successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create QR codes directory. Please create it manually.</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ QR codes directory already exists.</p>";
    }
    
    // Create .htaccess for qr_codes directory (security)
    $htaccessContent = "# Prevent direct access to QR code files\n";
    $htaccessContent .= "# Only allow access through download script\n";
    $htaccessContent .= "Order Deny,Allow\n";
    $htaccessContent .= "Deny from all\n";
    $htaccessContent .= "Allow from 127.0.0.1\n";
    $htaccessContent .= "Allow from ::1\n";
    
    $htaccessPath = $qrDir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, $htaccessContent);
        echo "<p style='color: green;'>✅ Security .htaccess file created for QR codes directory.</p>";
    }
    
    echo "<h2>3. Checking Dependencies...</h2>";
    
    // Check if Composer autoload exists
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "<p style='color: green;'>✅ Composer dependencies found.</p>";
        
        // Check if endroid/qr-code is installed
        if (class_exists('Endroid\QrCode\QrCode')) {
            echo "<p style='color: green;'>✅ QR Code library (endroid/qr-code) is installed.</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ QR Code library not found. Please run: composer require endroid/qr-code</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Composer not found. Please install dependencies:</p>";
        echo "<pre>composer require endroid/qr-code</pre>";
    }
    
    echo "<h2>4. Testing Database Connection...</h2>";
    
    // Test database connection and show some stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM registrations");
    $stmt->execute();
    $totalRegs = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as confirmed FROM registrations WHERE status = 'confirmed'");
    $stmt->execute();
    $confirmedRegs = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as with_qr FROM registrations WHERE qr_path IS NOT NULL");
    $stmt->execute();
    $qrRegs = $stmt->fetchColumn();
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    echo "<ul>";
    echo "<li>Total registrations: <strong>$totalRegs</strong></li>";
    echo "<li>Confirmed registrations: <strong>$confirmedRegs</strong></li>";
    echo "<li>Registrations with QR codes: <strong>$qrRegs</strong></li>";
    echo "</ul>";
    
    echo "<h2>5. Setup Complete!</h2>";
    echo "<p style='color: green; font-size: 1.2em;'>🎉 QR Code system is ready to use!</p>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='admin/qr_management.php'>Go to QR Management Panel</a></li>";
    echo "<li>Generate QR codes for confirmed registrations</li>";
    echo "<li>Download and distribute QR codes to participants</li>";
    echo "<li>Use the attendance.php page for QR code scanning</li>";
    echo "</ol>";
    
    echo "<h3>File Structure:</h3>";
    echo "<ul>";
    echo "<li><strong>admin/qr_management.php</strong> - Admin panel for QR management</li>";
    echo "<li><strong>attendance.php</strong> - QR code scanning and attendance marking</li>";
    echo "<li><strong>download_qr_code.php</strong> - Secure QR code download</li>";
    echo "<li><strong>includes/QRCodeGenerator.php</strong> - QR code generation class</li>";
    echo "<li><strong>qr_codes/</strong> - Directory for storing QR code images</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Setup failed: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR System Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        
        h1, h2, h3 {
            color: #333;
        }
        
        h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        h2 {
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        
        ul, ol {
            margin: 15px 0;
        }
        
        li {
            margin: 5px 0;
        }
        
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- PHP output will be displayed here -->
</body>
</html>
