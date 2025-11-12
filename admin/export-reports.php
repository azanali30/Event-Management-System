<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
admin_require_login();

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get date range from query parameters
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    $export_format = $_GET['export'] ?? 'csv';
    
    // Validate dates
    if (!strtotime($start_date) || !strtotime($end_date)) {
        throw new Exception('Invalid date range provided.');
    }
    
    // Generate filename
    $filename = 'reports_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV headers for summary report
    fputcsv($output, ['Event Management System - Analytics Report']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Date Range: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, []);
    
    // Get comprehensive statistics
    $stats = [];
    
    // User statistics
    $stats['total_users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $new_users_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at <= ?');
    $new_users_stmt->execute([$start_date, $end_date . ' 23:59:59']);
    $stats['new_users'] = (int)$new_users_stmt->fetchColumn();
    
    // Event statistics
    $stats['total_events'] = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
    $stats['upcoming_events'] = (int)$pdo->query('SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()')->fetchColumn();
    $stats['past_events'] = (int)$pdo->query('SELECT COUNT(*) FROM events WHERE event_date < CURDATE()')->fetchColumn();
    
    // Registration statistics
    $stats['total_registrations'] = (int)$pdo->query('SELECT COUNT(*) FROM registration')->fetchColumn();
$stats['confirmed_registrations'] = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE approved_at IS NOT NULL')->fetchColumn();
$stats['pending_registrations'] = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE approved_at IS NULL')->fetchColumn();
    
    // Media statistics
    $stats['total_media'] = (int)$pdo->query('SELECT COUNT(*) FROM mediagallery')->fetchColumn();
    $stats['approved_media'] = (int)$pdo->query("SELECT COUNT(*) FROM mediagallery WHERE status = 'approved'")->fetchColumn();
    $stats['pending_media'] = (int)$pdo->query("SELECT COUNT(*) FROM mediagallery WHERE status = 'pending'")->fetchColumn();
    
    // Write summary statistics
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Users', $stats['total_users']]);
    fputcsv($output, ['New Users (Date Range)', $stats['new_users']]);
    fputcsv($output, ['Total Events', $stats['total_events']]);
    fputcsv($output, ['Upcoming Events', $stats['upcoming_events']]);
    fputcsv($output, ['Past Events', $stats['past_events']]);
    fputcsv($output, ['Total Registrations', $stats['total_registrations']]);
    fputcsv($output, ['Confirmed Registrations', $stats['confirmed_registrations']]);
    fputcsv($output, ['Pending Registrations', $stats['pending_registrations']]);
    fputcsv($output, ['Total Media', $stats['total_media']]);
    fputcsv($output, ['Approved Media', $stats['approved_media']]);
    fputcsv($output, ['Pending Media', $stats['pending_media']]);
    fputcsv($output, []);
    
    // Get top events by registration count
    $top_events = $pdo->query("
        SELECT 
            e.title,
            e.event_date,
            e.venue,
            COUNT(r.id) as registration_count
        FROM events e
        LEFT JOIN registration r ON e.event_id = r.event_id
        GROUP BY e.event_id
        ORDER BY registration_count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Write top events
    fputcsv($output, ['TOP EVENTS BY REGISTRATIONS']);
    fputcsv($output, ['Event Title', 'Event Date', 'Venue', 'Registration Count']);
    foreach ($top_events as $event) {
        fputcsv($output, [
            $event['title'],
            $event['event_date'],
            $event['venue'],
            $event['registration_count']
        ]);
    }
    fputcsv($output, []);
    
    // Get recent registrations
    $recent_registrations = $pdo->query("
        SELECT 
            r.student_email as user_email,
            e.title as event_title,
            r.registered_on,
            CASE WHEN r.approved_at IS NOT NULL THEN 'approved' ELSE 'pending' END as status
        FROM registration r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.registered_on >= '$start_date' AND r.registered_on <= '$end_date 23:59:59'
        ORDER BY r.registered_on DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Write recent registrations
    fputcsv($output, ['RECENT REGISTRATIONS (Date Range)']);
    fputcsv($output, ['User Email', 'Event Title', 'Registration Date', 'Status']);
    foreach ($recent_registrations as $registration) {
        fputcsv($output, [
            $registration['user_email'],
            $registration['event_title'],
            $registration['registered_on'],
            $registration['status']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    header('Location: reports.php?error=' . urlencode('Export failed. Please try again.'));
    exit;
}
?>