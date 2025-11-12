<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Checking registrations for faz users:\n\n";
    
    // Get all faz users
    $stmt = $pdo->query("SELECT user_id, email FROM users WHERE email LIKE '%faz%'");
    $faz_users = $stmt->fetchAll();
    
    foreach ($faz_users as $user) {
        echo "User ID: " . $user['user_id'] . " - Email: " . $user['email'] . "\n";
        
        // Check registrations for this user
        $reg_stmt = $pdo->prepare("
            SELECT r.registration_id, r.event_id, r.status, e.title 
            FROM registrations r 
            JOIN events e ON r.event_id = e.event_id 
            WHERE r.student_id = ?
        ");
        $reg_stmt->execute([$user['user_id']]);
        $registrations = $reg_stmt->fetchAll();
        
        if ($registrations) {
            foreach ($registrations as $reg) {
                echo "  - Registration ID: " . $reg['registration_id'] . 
                     " | Event: " . $reg['title'] . 
                     " | Status: " . $reg['status'] . "\n";
            }
        } else {
            echo "  - No registrations found\n";
        }
        echo "\n";
    }
    
    // Also check if there are any confirmed registrations that can be used for testing
    echo "All confirmed registrations:\n";
    $stmt = $pdo->query("
        SELECT r.registration_id, r.student_id, r.status, u.email, e.title 
        FROM registrations r 
        JOIN users u ON r.student_id = u.user_id 
        JOIN events e ON r.event_id = e.event_id 
        WHERE r.status = 'confirmed' 
        ORDER BY r.registration_id
    ");
    
    while ($row = $stmt->fetch()) {
        echo "Reg ID: " . $row['registration_id'] . 
             " | User: " . $row['email'] . 
             " | Event: " . $row['title'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
