<?php
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>Creating Missing Database Tables</h2>";

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Create registrations table
    echo "<h3>Creating registrations table...</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS `registrations` (
      `registration_id` int(11) NOT NULL AUTO_INCREMENT,
      `event_id` int(11) NOT NULL,
      `student_id` int(11) NOT NULL,
      `registered_on` timestamp NOT NULL DEFAULT current_timestamp(),
      `status` enum('confirmed','cancelled','waitlist') DEFAULT 'confirmed',
      PRIMARY KEY (`registration_id`),
      KEY `event_id` (`event_id`),
      KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "<p>✅ registrations table created successfully</p>";
    
    // Create mediagallery table
    echo "<h3>Creating mediagallery table...</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS `mediagallery` (
      `media_id` int(11) NOT NULL AUTO_INCREMENT,
      `event_id` int(11) NOT NULL,
      `file_type` enum('image','video') DEFAULT NULL,
      `file_url` varchar(255) DEFAULT NULL,
      `uploaded_by` int(11) NOT NULL,
      `uploaded_on` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`media_id`),
      KEY `event_id` (`event_id`),
      KEY `uploaded_by` (`uploaded_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "<p>✅ mediagallery table created successfully</p>";
    
    // Create feedback table
    echo "<h3>Creating feedback table...</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS `feedback` (
      `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
      `event_id` int(11) NOT NULL,
      `student_id` int(11) NOT NULL,
      `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
      `comments` text DEFAULT NULL,
      `submitted_on` timestamp NOT NULL DEFAULT current_timestamp(),
      `status` enum('pending','approved','rejected') DEFAULT 'pending',
      PRIMARY KEY (`feedback_id`),
      KEY `event_id` (`event_id`),
      KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "<p>✅ feedback table created successfully</p>";
    
    echo "<h3>🎉 All tables created successfully!</h3>";
    
    // Verify tables exist
    echo "<h3>Verifying tables:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_tables = ['users', 'userdetails', 'events', 'registrations', 'mediagallery', 'feedback'];
    
    foreach ($required_tables as $table) {
        if (in_array($table, $tables)) {
            echo "<p>✅ $table - EXISTS</p>";
        } else {
            echo "<p>❌ $table - MISSING</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<p><a href="check_database_tables.php">Check Database Tables</a></p>
<p><a href="admin/dashboard.php">Try Admin Dashboard</a></p>
