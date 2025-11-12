<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Add UID column
    $pdo->exec("ALTER TABLE registrations ADD COLUMN uid VARCHAR(20) UNIQUE");
    echo "✅ Added UID column to registrations table\n";
    
    // Add qr_path column
    $pdo->exec("ALTER TABLE registrations ADD COLUMN qr_path VARCHAR(255)");
    echo "✅ Added qr_path column to registrations table\n";
    
} catch (Exception $e) {
    echo "Columns might already exist: " . $e->getMessage() . "\n";
}
?>
