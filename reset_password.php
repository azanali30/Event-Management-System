<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $email = 'azanali3005@gmail.com';
    $new_password = 'test123';
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update the password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $result = $stmt->execute([$hashed_password, $email]);
    
    if ($result) {
        echo "✅ Password updated successfully for $email";
        echo "<br>New password: $new_password";
        echo "<br>Password hash: " . substr($hashed_password, 0, 30) . "...";
        
        // Test the new password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($new_password, $user['password'])) {
            echo "<br>✅ Password verification test: SUCCESS";
        } else {
            echo "<br>❌ Password verification test: FAILED";
        }
    } else {
        echo "❌ Failed to update password";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

<p><a href="test_login_simple.php">Test Login Now</a></p>
<p><a href="pages/login.php">Go to Login Page</a></p>
