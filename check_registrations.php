<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->query("SELECT registration_id, status FROM registrations LIMIT 5");
    echo "Registrations:\n";
    while ($row = $stmt->fetch()) {
        echo "ID: " . $row['registration_id'] . " Status: " . $row['status'] . "\n";
    }
    
    // Update a registration to confirmed for testing
    $pdo->exec("UPDATE registrations SET status = 'confirmed' WHERE registration_id = 2");
    echo "Updated registration 2 to confirmed status\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
