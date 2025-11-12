<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Users in system:\n";
    $stmt = $pdo->query('SELECT user_id, email FROM users');
    while ($row = $stmt->fetch()) {
        echo 'ID: ' . $row['user_id'] . ' - Email: ' . $row['email'] . "\n";
    }
    
    echo "\nUser details:\n";
    $stmt = $pdo->query('SELECT u.user_id, u.email, ud.full_name FROM users u LEFT JOIN userdetails ud ON u.user_id = ud.user_id');
    while ($row = $stmt->fetch()) {
        echo 'ID: ' . $row['user_id'] . ' - Email: ' . $row['email'] . ' - Name: ' . ($row['full_name'] ?: 'No name') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
