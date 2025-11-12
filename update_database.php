<?php
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>Updating Registrations Table Structure</h2>";

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "<h3>Adding payment fields to registrations table...</h3>";
    
    // Check if payment_screenshot column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'payment_screenshot'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `registrations` ADD COLUMN `payment_screenshot` VARCHAR(255) DEFAULT NULL AFTER `status`");
        echo "<p>✅ Added payment_screenshot column</p>";
    } else {
        echo "<p>ℹ️ payment_screenshot column already exists</p>";
    }
    
    // Check if payment_details column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'payment_details'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `registrations` ADD COLUMN `payment_details` TEXT DEFAULT NULL AFTER `payment_screenshot`");
        echo "<p>✅ Added payment_details column</p>";
    } else {
        echo "<p>ℹ️ payment_details column already exists</p>";
    }
    
    // Update status enum to include 'pending' and 'waitlist_pending'
    try {
        $pdo->exec("ALTER TABLE `registrations` MODIFY COLUMN `status` ENUM('pending','confirmed','waitlist','waitlist_pending','cancelled') DEFAULT 'pending'");
        echo "<p>✅ Updated status enum values</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Status enum update: " . $e->getMessage() . "</p>";
    }
    
    // Add index for status if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE `registrations` ADD INDEX `idx_status` (`status`)");
        echo "<p>✅ Added status index</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Status index: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>✅ Database update completed successfully!</h3>";
    
    // Show current table structure
    echo "<h3>Current registrations table structure:</h3>";
    $stmt = $pdo->query("DESCRIBE registrations");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>