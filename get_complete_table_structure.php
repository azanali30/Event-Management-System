<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=event', 'root', '');
    
    echo "Complete Registration Table Structure:\n";
    echo "====================================\n";
    
    // Get table structure with SHOW COLUMNS which is more comprehensive
    $result = $pdo->query('SHOW COLUMNS FROM registration');
    $columns = [];
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
        echo sprintf("%-20s | %-30s | %-10s | %-10s | %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL'
        );
    }
    
    echo "\nTotal columns: " . count($columns) . "\n";
    echo "Column list: " . implode(', ', $columns) . "\n\n";
    
    // Check if we can insert a test record structure
    echo "Testing INSERT query structure:\n";
    echo "==============================\n";
    
    $insert_columns = implode(', ', array_filter($columns, function($col) {
        return !in_array($col, ['id', 'registered_on']); // Exclude auto-generated columns
    }));
    
    $placeholders = str_repeat('?, ', count(explode(', ', $insert_columns)) - 1) . '?';
    
    echo "INSERT INTO registration ($insert_columns) VALUES ($placeholders)\n\n";
    
    // Show current form fields that need to match
    echo "Form fields that should match database:\n";
    echo "=====================================\n";
    echo "- student_id (from session)\n";
    echo "- event_id (from URL parameter)\n";
    echo "- student_name (from session/user table)\n";
    echo "- student_email (from session/user table)\n";
    echo "- payment_screenshot (file upload)\n";
    echo "- payment_details (textarea)\n";
    echo "- status (set to 'pending')\n";
    echo "- approved_at (NULL initially)\n";
    echo "- qr_code (empty string initially)\n";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>