<?php
require_once '../config/database.php';
require_once '../config/config.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get filters from URL parameters
$search = $_GET['search'] ?? '';
$event_id = $_GET['event_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$export_format = $_GET['export'] ?? 'csv';

try {
    // Build query with same filters as registrations.php
    $query = "
        SELECT 
            r.id as registration_id,
            r.student_name as user_name,
            r.student_email as user_email,
            r.student_mobile as user_mobile,
            r.student_department as user_department,
            e.title as event_title,
            e.event_date,
            e.event_time,
            e.venue,
            r.registered_on,
            CASE WHEN r.approved_at IS NOT NULL THEN 'approved' ELSE 'pending' END as status
        FROM registration r
        LEFT JOIN events e ON r.event_id = e.event_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply filters
    if ($search) {
        $query .= " AND (r.student_name LIKE ? OR r.student_email LIKE ? OR e.title LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($event_id) {
        $query .= " AND r.event_id = ?";
        $params[] = $event_id;
    }
    
    if ($status_filter) {
        $query .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY r.registered_on DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate filename with timestamp
    $filename = 'registrations_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    fputcsv($output, [
        'Registration ID',
        'Participant Name',
        'Email',
        'Mobile',
        'Department',
        'Event Title',
        'Event Date',
        'Event Time',
        'Venue',
        'Registration Date',
        'Status'
    ]);
    
    // Write data rows
    foreach ($registrations as $registration) {
        fputcsv($output, [
            $registration['registration_id'],
            $registration['user_name'],
            $registration['user_email'],
            $registration['user_mobile'] ?: 'N/A',
            $registration['user_department'] ?: 'N/A',
            $registration['event_title'],
            $registration['event_date'] ? date('Y-m-d', strtotime($registration['event_date'])) : 'N/A',
            $registration['event_time'] ? date('H:i', strtotime($registration['event_time'])) : 'N/A',
            $registration['venue'] ?: 'N/A',
            date('Y-m-d H:i:s', strtotime($registration['registered_on'])),
            ucfirst($registration['status'])
        ]);
    }
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    header('Location: registrations.php?error=' . urlencode('Export failed. Please try again.'));
    exit();
}
?>