<?php
/**
 * Manual Database Setup for Forgot Password Tables
 * This script creates the required tables one by one
 */

require_once '../config/database.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Setting up Forgot Password Database Tables</h2>";
    echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
    
    // Table creation queries
    $tables = [
        'password_reset_otps' => "
            CREATE TABLE IF NOT EXISTS password_reset_otps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                mobile VARCHAR(20) NULL,
                otp_code VARCHAR(10) NOT NULL,
                otp_type ENUM('email', 'mobile') NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                is_verified TINYINT(1) DEFAULT 0,
                is_used TINYINT(1) DEFAULT 0,
                attempts INT DEFAULT 0,
                verified_at DATETIME NULL,
                used_at DATETIME NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id),
                INDEX idx_email (email),
                INDEX idx_mobile (mobile),
                INDEX idx_expires_at (expires_at),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'password_reset_logs' => "
            CREATE TABLE IF NOT EXISTS password_reset_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                mobile VARCHAR(20) NULL,
                action VARCHAR(50) NOT NULL,
                otp_type ENUM('email', 'mobile') NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                details JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_email (email),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'sms_config' => "
            CREATE TABLE IF NOT EXISTS sms_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                provider VARCHAR(50) NOT NULL,
                api_key VARCHAR(255) NOT NULL,
                api_secret VARCHAR(255) NULL,
                sender_id VARCHAR(20) NOT NULL,
                base_url VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                daily_limit INT DEFAULT 1000,
                cost_per_sms DECIMAL(10,4) DEFAULT 0.05,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'sms_usage' => "
            CREATE TABLE IF NOT EXISTS sms_usage (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                mobile VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                provider VARCHAR(50) NOT NULL,
                message_id VARCHAR(100) NULL,
                status ENUM('sent', 'delivered', 'failed', 'pending') DEFAULT 'pending',
                cost DECIMAL(10,4) DEFAULT 0.00,
                response_data JSON NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_mobile (mobile),
                INDEX idx_status (status),
                INDEX idx_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($tables as $tableName => $query) {
        echo "<h3>📋 Creating table: $tableName</h3>";
        
        try {
            $result = $db->exec($query);
            echo "<p style='color: green;'>✅ Table '$tableName' created successfully!</p>";
            $successCount++;
            
            // Verify table exists
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: blue;'>🔍 Verified: Table '$tableName' exists in database</p>";
                
                // Show table structure
                $stmt = $db->prepare("DESCRIBE $tableName");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<details><summary>📊 Table Structure ($tableName)</summary>";
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
                foreach ($columns as $column) {
                    echo "<tr>";
                    echo "<td>{$column['Field']}</td>";
                    echo "<td>{$column['Type']}</td>";
                    echo "<td>{$column['Null']}</td>";
                    echo "<td>{$column['Key']}</td>";
                    echo "<td>{$column['Default']}</td>";
                    echo "</tr>";
                }
                echo "</table></details>";
            }
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error creating table '$tableName': " . $e->getMessage() . "</p>";
            $errorCount++;
        }
        
        echo "<hr>";
    }
    
    // Insert default SMS configuration
    echo "<h3>⚙️ Setting up default SMS configuration</h3>";
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM sms_config");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $stmt = $db->prepare("
                INSERT INTO sms_config (provider, api_key, api_secret, sender_id, base_url, is_active) 
                VALUES ('mock', 'demo_key', 'demo_secret', 'EVENTS', 'https://api.mock-sms.com', 0)
            ");
            $stmt->execute();
            echo "<p style='color: green;'>✅ Default SMS configuration added (inactive for demo)</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ SMS configuration already exists</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error setting up SMS config: " . $e->getMessage() . "</p>";
    }
    
    // Summary
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;'>";
    echo "<h2>📊 Setup Summary</h2>";
    echo "<p><strong>✅ Successful operations:</strong> $successCount</p>";
    echo "<p><strong>❌ Failed operations:</strong> $errorCount</p>";
    
    if ($errorCount == 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>🎉 Database Setup Complete!</h3>";
        echo "<p>All tables have been created successfully. The forgot password system is ready to use.</p>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ul>";
        echo "<li>Test the forgot password functionality</li>";
        echo "<li>Configure SMS provider settings if needed</li>";
        echo "<li>Review security settings and rate limits</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>⚠️ Setup Issues Detected</h3>";
        echo "<p>Some tables failed to create. Please check the error messages above and resolve any issues.</p>";
        echo "</div>";
    }
    echo "</div>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px;'>";
    echo "<h2>❌ Database Connection Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}

h2, h3 {
    color: #333;
}

details {
    margin: 10px 0;
}

summary {
    cursor: pointer;
    font-weight: bold;
    padding: 5px;
    background: #e9ecef;
    border-radius: 3px;
}

table {
    width: 100%;
    max-width: 800px;
}

th, td {
    padding: 8px;
    text-align: left;
}

th {
    background-color: #f8f9fa;
}

hr {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #ddd;
}
</style>
