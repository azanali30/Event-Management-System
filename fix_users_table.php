<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=event', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Adding user_id column to users table\n";
    echo "===================================\n\n";
    
    // Add user_id column as AUTO_INCREMENT PRIMARY KEY at the beginning
    $pdo->exec('ALTER TABLE users ADD COLUMN user_id INT AUTO_INCREMENT PRIMARY KEY FIRST');
    echo "✓ user_id column added successfully\n\n";
    
    // Verify the structure
    echo "Updated users table structure:\n";
    $result = $pdo->query('SHOW COLUMNS FROM users');
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-15s | %-30s | %-5s | %-5s | %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL'
        );
    }
    
    // Show sample data
    echo "\nSample users with new user_id:\n";
    $users = $pdo->query('SELECT user_id, name, email, role FROM users LIMIT 3');
    while($user = $users->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$user['user_id']} - {$user['name']} ({$user['email']}) - Role: {$user['role']}\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // If the column already exists, just show the current structure
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "\nColumn already exists. Current structure:\n";
        try {
            $result = $pdo->query('SHOW COLUMNS FROM users');
            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                echo sprintf("%-15s | %-30s | %-5s | %-5s | %s\n", 
                    $row['Field'], 
                    $row['Type'], 
                    $row['Null'], 
                    $row['Key'], 
                    $row['Default'] ?? 'NULL'
                );
            }
        } catch(Exception $e2) {
            echo "Error showing structure: " . $e2->getMessage() . "\n";
        }
    }
}
?>