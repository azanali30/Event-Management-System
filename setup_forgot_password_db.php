<?php
/**
 * Setup Forgot Password Database Tables
 * Run this once to create the required tables for forgot password functionality
 */

require_once 'config/config.php';
require_once 'config/database.php';

echo "<h1>🔧 Setting up Forgot Password Database Tables</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "<h3>📊 Reading SQL file...</h3>";
    
    // Read the SQL file
    $sqlFile = 'database/forgot_password_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "<p>✅ SQL file loaded successfully</p>";
    
    echo "<h3>🗄️ Creating database tables...</h3>";
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            
            // Extract table name for display
            if (preg_match('/CREATE TABLE.*?`([^`]+)`/i', $statement, $matches)) {
                echo "<p>✅ Created table: <strong>{$matches[1]}</strong></p>";
            } elseif (preg_match('/INSERT INTO.*?`([^`]+)`/i', $statement, $matches)) {
                echo "<p>✅ Inserted data into: <strong>{$matches[1]}</strong></p>";
            } elseif (preg_match('/CREATE INDEX.*?`([^`]+)`/i', $statement, $matches)) {
                echo "<p>✅ Created index: <strong>{$matches[1]}</strong></p>";
            } else {
                echo "<p>✅ Executed SQL statement</p>";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            echo "<p style='color: orange;'>⚠️ Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🎉 Database Setup Complete!</h3>";
    echo "<p><strong>✅ Successful operations:</strong> $successCount</p>";
    echo "<p><strong>⚠️ Warnings/Skipped:</strong> $errorCount</p>";
    echo "</div>";
    
    // Verify tables were created
    echo "<h3>🔍 Verifying created tables...</h3>";
    
    $tables = [
        'password_reset_otps' => 'OTP tokens storage',
        'password_reset_logs' => 'Password reset activity logs',
        'sms_config' => 'SMS gateway configuration',
        'sms_usage' => 'SMS usage tracking'
    ];
    
    foreach ($tables as $tableName => $description) {
        try {
            $stmt = $pdo->query("DESCRIBE `$tableName`");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>✅ <strong>$tableName</strong> - $description (" . count($columns) . " columns)</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ <strong>$tableName</strong> - Failed to verify</p>";
        }
    }
    
    echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>📋 What was created:</h3>";
    echo "<ul>";
    echo "<li><strong>password_reset_otps</strong> - Stores OTP codes for email/mobile verification</li>";
    echo "<li><strong>password_reset_logs</strong> - Logs all password reset activities for security</li>";
    echo "<li><strong>sms_config</strong> - Configuration for SMS gateway (Twilio)</li>";
    echo "<li><strong>sms_usage</strong> - Tracks SMS usage and costs</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🔧 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Configure SMS gateway credentials (Twilio/other)</li>";
    echo "<li>Test email OTP functionality</li>";
    echo "<li>Test mobile OTP functionality</li>";
    echo "<li>Add forgot password links to login pages</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h2>❌ ERROR</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='index.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px;'>← Back to Home</a>";
echo "<a href='pages/login.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px;'>Go to Login</a>";
echo "</div>";

echo "</div>";
?>
