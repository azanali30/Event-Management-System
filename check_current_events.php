<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Current Events in Database:\n";
    echo "==========================\n\n";
    
    $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date ASC");
    $events = $stmt->fetchAll();
    
    if (empty($events)) {
        echo "No events found in database.\n";
    } else {
        foreach ($events as $event) {
            echo "Event ID: " . $event['event_id'] . "\n";
            echo "Title: " . $event['title'] . "\n";
            echo "Description: " . $event['description'] . "\n";
            echo "Date: " . $event['event_date'] . "\n";
            echo "Time: " . $event['event_time'] . "\n";
            echo "Venue: " . $event['venue'] . "\n";
            echo "Category: " . $event['category'] . "\n";
            echo "Status: " . $event['status'] . "\n";
            echo "Featured: " . ($event['featured'] ? 'Yes' : 'No') . "\n";
            echo "Max Participants: " . $event['max_participants'] . "\n";
            echo "Current Participants: " . $event['current_participants'] . "\n";
            echo "Registration Deadline: " . $event['registration_deadline'] . "\n";
            echo "Requirements: " . $event['requirements'] . "\n";
            echo "---\n\n";
        }
    }
    
    echo "Total Events: " . count($events) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
