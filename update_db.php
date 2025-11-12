<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "<h2>Updating Registrations Table</h2>";
    
    // Add payment_screenshot column if it doesn't exist
    $sql1 = "ALTER TABLE `registrations` 
             ADD COLUMN IF NOT EXISTS `payment_screenshot` VARCHAR(255) DEFAULT NULL AFTER `status`,
             ADD COLUMN IF NOT EXISTS `payment_details` TEXT DEFAULT NULL AFTER `payment_screenshot`";
    $pdo->exec($sql1);
    echo "<p>✅ Payment columns added successfully</p>";
    
    // Update the status enum to include 'pending' for payment approval workflow
    $sql2 = "ALTER TABLE `registrations` 
             MODIFY COLUMN `status` ENUM('pending','confirmed','waitlist','waitlist_pending','cancelled') DEFAULT 'pending'";
    $pdo->exec($sql2);
    echo "<p>✅ Status enum updated successfully</p>";
    
    // Add index for better performance on status queries
    $sql3 = "ALTER TABLE `registrations` 
             ADD INDEX IF NOT EXISTS `idx_status` (`status`)";
    $pdo->exec($sql3);
    echo "<p>✅ Index added successfully</p>";
    
    echo "<h3>✅ Registrations table updated successfully with payment fields!</h3>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>