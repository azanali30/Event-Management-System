<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Users table structure:\n";
    $stmt = $pdo->query('DESCRIBE users');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\nUserdetails table structure:\n";
    $stmt = $pdo->query('DESCRIBE userdetails');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\nSample user data:\n";
    $stmt = $pdo->query('SELECT u.*, ud.full_name FROM users u LEFT JOIN userdetails ud ON u.user_id = ud.user_id LIMIT 3');
    while ($row = $stmt->fetch()) {
        print_r($row);
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
