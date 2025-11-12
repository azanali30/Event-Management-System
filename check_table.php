<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Registrations table structure:\n";
    $stmt = $pdo->query('DESCRIBE registrations');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\nAll registrations:\n";
    $stmt = $pdo->query('SELECT * FROM registrations');
    while ($row = $stmt->fetch()) {
        print_r($row);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
