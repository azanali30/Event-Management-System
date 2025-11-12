<?php
/**
 * Direct Database Setup - This will definitely work!
 * Run this script to create all required tables
 */

// Direct database connection without using classes
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'event';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - EventSphere</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 2rem auto; padding: 1rem; background: #f8fafc; }
        .container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1e293b; margin-bottom: 1rem; }
        .success { color: #059669; background: #ecfdf5; padding: 1rem; border-radius: 6px; margin: 1rem 0; border-left: 4px solid #059669; }
        .error { color: #dc2626; background: #fef2f2; padding: 1rem; border-radius: 6px; margin: 1rem 0; border-left: 4px solid #dc2626; }
        .info { color: #0369a1; background: #f0f9ff; padding: 1rem; border-radius: 6px; margin: 1rem 0; border-left: 4px solid #0369a1; }
        .step { margin: 1rem 0; padding: 0.5rem 0; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 0.5rem 0.5rem 0.5rem 0; }
        .btn:hover { background: #2563eb; }
        pre { background: #f1f5f9; padding: 1rem; border-radius: 6px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 EventSphere Database Setup</h1>";

try {
    echo "<div class='step'>Step 1: Connecting to MySQL server...</div>";
    
    // Connect to MySQL server first (without database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Connected to MySQL server successfully!</div>";
    
    echo "<div class='step'>Step 2: Creating database if not exists...</div>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `$database`");
    
    echo "<div class='success'>✅ Database '$database' created/selected successfully!</div>";
    
    echo "<div class='step'>Step 3: Creating all required tables...</div>";
    
    // Array of all table creation queries
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `first_name` varchar(50) NOT NULL,
            `last_name` varchar(50) NOT NULL,
            `phone` varchar(20) DEFAULT NULL,
            `student_id` varchar(20) DEFAULT NULL,
            `department` varchar(100) DEFAULT NULL,
            `role` enum('participant','organizer','admin') NOT NULL DEFAULT 'participant',
            `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
            `email_verified` tinyint(1) DEFAULT 0,
            `verification_token` varchar(255) DEFAULT NULL,
            `reset_token` varchar(255) DEFAULT NULL,
            `reset_expires` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`),
            KEY `role` (`role`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        'events' => "CREATE TABLE IF NOT EXISTS `events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(200) NOT NULL,
            `description` text DEFAULT NULL,
            `long_description` text DEFAULT NULL,
            `event_date` date NOT NULL,
            `start_time` time NOT NULL,
            `end_time` time DEFAULT NULL,
            `venue` varchar(200) NOT NULL,
            `category` enum('technical','cultural','sports','workshop','seminar','competition','other') NOT NULL,
            `max_participants` int(11) DEFAULT 0,
            `current_participants` int(11) DEFAULT 0,
            `registration_deadline` datetime DEFAULT NULL,
            `organizer_id` int(11) NOT NULL,
            `status` enum('draft','pending','approved','ongoing','completed','cancelled') DEFAULT 'draft',
            `featured` tinyint(1) DEFAULT 0,
            `image` varchar(255) DEFAULT NULL,
            `requirements` text DEFAULT NULL,
            `contact_info` text DEFAULT NULL,
            `registration_fee` decimal(10,2) DEFAULT 0.00,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `organizer_id` (`organizer_id`),
            KEY `category` (`category`),
            KEY `status` (`status`),
            KEY `event_date` (`event_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        'event_registrations' => "CREATE TABLE IF NOT EXISTS `event_registrations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `event_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
            `status` enum('registered','attended','cancelled','waitlist') DEFAULT 'registered',
            `qr_code` varchar(255) DEFAULT NULL,
            `check_in_time` timestamp NULL DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
            `payment_reference` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_registration` (`event_id`,`user_id`),
            KEY `user_id` (`user_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        'attendance' => "CREATE TABLE IF NOT EXISTS `attendance` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `event_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `check_in_time` timestamp NOT NULL DEFAULT current_timestamp(),
            `check_out_time` timestamp NULL DEFAULT NULL,
            `attendance_status` enum('present','absent','late') DEFAULT 'present',
            `marked_by` int(11) DEFAULT NULL,
            `notes` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_attendance` (`event_id`,`user_id`),
            KEY `user_id` (`user_id`),
            KEY `marked_by` (`marked_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        'announcements' => "CREATE TABLE IF NOT EXISTS `announcements` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(200) NOT NULL,
            `content` text NOT NULL,
            `type` enum('info','success','warning','error') DEFAULT 'info',
            `target_audience` enum('all','students','organizers','admins') DEFAULT 'all',
            `created_by` int(11) NOT NULL,
            `status` enum('draft','published','archived') DEFAULT 'draft',
            `publish_date` timestamp NOT NULL DEFAULT current_timestamp(),
            `expire_date` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `created_by` (`created_by`),
            KEY `status` (`status`),
            KEY `publish_date` (`publish_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        
        'notifications' => "CREATE TABLE IF NOT EXISTS `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `title` varchar(200) NOT NULL,
            `message` text NOT NULL,
            `type` enum('event_reminder','registration_confirmation','event_update','general','certificate_ready') DEFAULT 'general',
            `related_event_id` int(11) DEFAULT NULL,
            `read_status` tinyint(1) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `related_event_id` (`related_event_id`),
            KEY `read_status` (`read_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];
    
    $createdTables = [];
    foreach ($tables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            $createdTables[] = $tableName;
            echo "<div class='success'>✅ Table '$tableName' created successfully!</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>❌ Error creating table '$tableName': " . $e->getMessage() . "</div>";
        }
    }
    
    echo "<div class='step'>Step 4: Adding admin user and sample data...</div>";
    
    // Insert admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, first_name, last_name, role, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@college.edu', $adminPassword, 'System', 'Administrator', 'admin', 'active', 1]);
    
    // Insert organizer user
    $organizerPassword = password_hash('organizer123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, first_name, last_name, phone, department, role, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['organizer', 'organizer@college.edu', $organizerPassword, 'John', 'Smith', '+1234567890', 'Computer Science', 'organizer', 'active', 1]);
    
    // Insert student user
    $studentPassword = password_hash('student123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, first_name, last_name, phone, student_id, department, role, status, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['student', 'student@college.edu', $studentPassword, 'Jane', 'Doe', '+1234567891', 'STU001', 'Computer Science', 'participant', 'active', 1]);
    
    echo "<div class='success'>✅ Admin, organizer, and student users created!</div>";
    
    // Add sample events
    $sampleEvents = [
        ['Annual Tech Symposium 2024', 'Join industry leaders and tech innovators for cutting-edge workshops.', '2024-03-15', '09:00:00', '17:00:00', 'Main Auditorium', 'technical', 150, 1, 'approved', 1],
        ['Sports Championship 2024', 'Inter-college sports competition featuring multiple sports.', '2024-03-18', '08:00:00', '18:00:00', 'Sports Complex', 'sports', 300, 1, 'approved', 1],
        ['Cultural Fest - Harmony 2024', 'Celebrate diversity through music, dance, and art.', '2024-03-20', '18:00:00', '22:00:00', 'College Grounds', 'cultural', 500, 1, 'approved', 1]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO events (title, description, event_date, start_time, end_time, venue, category, max_participants, organizer_id, status, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sampleEvents as $event) {
        $stmt->execute($event);
    }
    
    echo "<div class='success'>✅ Sample events added!</div>";
    
    echo "<div class='step'>Step 5: Verifying setup...</div>";
    
    // Check all tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='info'><strong>Database Tables Created:</strong></div>";
    echo "<table><tr><th>Table Name</th><th>Status</th></tr>";
    foreach ($allTables as $table) {
        echo "<tr><td><strong>$table</strong></td><td>✅ Ready</td></tr>";
    }
    echo "</table>";
    
    // Count records
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $eventCount = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    
    echo "<div class='success'>
        <h3>🎉 Database Setup Complete!</h3>
        <p><strong>Summary:</strong></p>
        <ul>
            <li>✅ Database '$database' created</li>
            <li>✅ " . count($allTables) . " tables created</li>
            <li>✅ $userCount users added (admin, organizer, student)</li>
            <li>✅ $eventCount sample events added</li>
        </ul>
        
        <h4>🔐 Login Credentials:</h4>
        <table>
            <tr><th>Role</th><th>Email</th><th>Password</th></tr>
            <tr><td><strong>Admin</strong></td><td>admin@college.edu</td><td>admin123</td></tr>
            <tr><td><strong>Organizer</strong></td><td>organizer@college.edu</td><td>organizer123</td></tr>
            <tr><td><strong>Student</strong></td><td>student@college.edu</td><td>student123</td></tr>
        </table>
        
        <h4>🚀 Ready to Use:</h4>
        <a href='admin/login.php' class='btn'>Admin Login</a>
        <a href='admin/dashboard.php' class='btn'>Admin Dashboard</a>
        <a href='pages/events.php' class='btn'>View Events</a>
        <a href='index.php' class='btn'>Main Website</a>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>Setup Failed:</strong> " . $e->getMessage() . "</div>";
    echo "<div class='info'>
        <strong>Manual Setup Instructions:</strong><br>
        1. Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a><br>
        2. Create database 'event' if it doesn't exist<br>
        3. Run the SQL commands from the create_tables.sql file
    </div>";
}

echo "</div></body></html>";
?>
