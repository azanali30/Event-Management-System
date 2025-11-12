<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h2>Create/Update Admin User</h2>";
    
    $admin_email = 'azanali3005@gmail.com';
    $admin_password = 'test123';
    $admin_name = 'Admin User';
    
    // Check if admin user already exists
    $stmt = $conn->prepare("SELECT user_id, email, name, role FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_user) {
        echo "<h3>Existing User Found:</h3>";
        echo "<ul>";
        echo "<li>ID: {$existing_user['user_id']}</li>";
        echo "<li>Email: {$existing_user['email']}</li>";
        echo "<li>Name: {$existing_user['name']}</li>";
        echo "<li>Role: <strong>{$existing_user['role']}</strong></li>";
        echo "</ul>";
        
        // Update to admin role and reset password
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET role = 'admin', password = ?, name = ? WHERE email = ?");
        $result = $stmt->execute([$hashed_password, $admin_name, $admin_email]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ User updated successfully!</p>";
            echo "<p>Role set to: <strong>admin</strong></p>";
            echo "<p>Password reset to: <strong>test123</strong></p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to update user</p>";
        }
        
    } else {
        echo "<h3>Creating New Admin User:</h3>";
        
        // Create new admin user
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
        $result = $stmt->execute([$admin_name, $admin_email, $hashed_password]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Admin user created successfully!</p>";
            echo "<ul>";
            echo "<li>Email: <strong>{$admin_email}</strong></li>";
            echo "<li>Password: <strong>{$admin_password}</strong></li>";
            echo "<li>Role: <strong>admin</strong></li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create admin user</p>";
        }
    }
    
    // Verify the admin user
    echo "<hr>";
    echo "<h3>Verification:</h3>";
    
    $stmt = $conn->prepare("SELECT user_id, email, name, password, role FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin_user) {
        echo "<p>✅ Admin user exists in database</p>";
        echo "<p>Role: <strong>{$admin_user['role']}</strong></p>";
        
        // Test password
        $password_check = password_verify($admin_password, $admin_user['password']);
        echo "<p>Password verification: " . ($password_check ? "✅ SUCCESS" : "❌ FAILED") . "</p>";
        
        // Test role constant
        echo "<p>ROLE_ADMIN constant: <strong>" . ROLE_ADMIN . "</strong></p>";
        echo "<p>Role match: " . ($admin_user['role'] === ROLE_ADMIN ? "✅ YES" : "❌ NO") . "</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Admin user not found after creation/update</p>";
    }
    
    echo "<hr>";
    echo "<h3>Test Admin Login:</h3>";
    echo "<p><a href='admin/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a></p>";
    echo "<p>Use credentials: <strong>{$admin_email}</strong> / <strong>{$admin_password}</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; max-width: 800px; }
    h2, h3 { color: #333; }
    ul { background: #f9f9f9; padding: 15px; border-radius: 5px; }
    hr { margin: 20px 0; }
</style>
