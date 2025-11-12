<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Starting database structure updates...\n";
    
    // Check if name column already exists in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'name'");
    if ($stmt->rowCount() == 0) {
        echo "Adding 'name' column to users table...\n";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `name` VARCHAR(100) NOT NULL AFTER `email`");
        echo "✅ Added 'name' column to users table\n";
        
        // Update existing users to have a name (using email as temporary name)
        $pdo->exec("UPDATE `users` SET `name` = SUBSTRING_INDEX(`email`, '@', 1) WHERE `name` = '' OR `name` IS NULL");
        echo "✅ Updated existing users with default names\n";
    } else {
        echo "✅ 'name' column already exists in users table\n";
    }
    
    // Check current structure
    echo "\nCurrent users table structure:\n";
    $stmt = $pdo->query("DESCRIBE users");
    while ($row = $stmt->fetch()) {
        echo "- {$row['Field']}: {$row['Type']}\n";
    }
    
    echo "\nCurrent userdetails table structure:\n";
    $stmt = $pdo->query("DESCRIBE userdetails");
    while ($row = $stmt->fetch()) {
        echo "- {$row['Field']}: {$row['Type']}\n";
    }
    
    echo "\n✅ Database structure update completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error updating database structure: " . $e->getMessage() . "\n";
}
?>
