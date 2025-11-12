<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';

admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_event') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $long_description = trim($_POST['long_description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $venue = trim($_POST['venue'] ?? '');
        $category = $_POST['category'] ?? '';
        $max_participants = (int)($_POST['max_participants'] ?? 0);
        $registration_deadline = $_POST['registration_deadline'] ?? '';
        $registration_fee = (float)($_POST['registration_fee'] ?? 0);
        $image = $_POST['image'] ?? '';
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        if ($title && $description && $event_date && $start_time && $venue && $category) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO events (
                        title, description, long_description, event_date, start_time, end_time, 
                        venue, category, max_participants, registration_deadline, organizer_id, 
                        status, featured, image, registration_fee, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $title, $description, $long_description, $event_date, $start_time, $end_time,
                    $venue, $category, $max_participants, $registration_deadline, $_SESSION['user_id'],
                    $featured, $image, $registration_fee
                ]);
                
                $message = 'Event added successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding event: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $long_description = trim($_POST['long_description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $venue = trim($_POST['venue'] ?? '');
        $category = $_POST['category'] ?? '';
        $max_participants = (int)($_POST['max_participants'] ?? 0);
        $registration_deadline = $_POST['registration_deadline'] ?? '';
        $registration_fee = (float)($_POST['registration_fee'] ?? 0);
        $image = $_POST['image'] ?? '';
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        if ($event_id && $title && $description && $event_date && $start_time && $venue && $category) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE events SET 
                        title = ?, description = ?, long_description = ?, event_date = ?, 
                        start_time = ?, end_time = ?, venue = ?, category = ?, 
                        max_participants = ?, registration_deadline = ?, featured = ?, 
                        image = ?, registration_fee = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $title, $description, $long_description, $event_date, $start_time, $end_time,
                    $venue, $category, $max_participants, $registration_deadline, $featured, 
                    $image, $registration_fee, $event_id
                ]);
                
                $message = 'Event updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating event: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        
        if ($event_id) {
            try {
                // First delete related registrations
        $stmt = $pdo->prepare("DELETE FROM registration WHERE event_id = ?");
                $stmt->execute([$event_id]);
                
                // Then delete the event
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$event_id]);
                
                $message = 'Event deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting event: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get all events
$stmt = $pdo->query("
    SELECT e.*, u.first_name, u.last_name, 
           (SELECT COUNT(*) FROM registration WHERE event_id = e.event_id) as registration_count
    FROM events e 
    LEFT JOIN users u ON e.organizer_id = u.id 
    ORDER BY e.created_at DESC
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event for editing if requested
$editEvent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$editId]);
    $editEvent = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Admin Panel</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .form-container { max-width: 800px; margin: 20px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group textarea { height: 100px; resize: vertical; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .events-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .events-table th, .events-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .events-table th { background: #f8f9fa; font-weight: bold; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .upload-area { border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 4px; margin-top: 10px; }
        .upload-area.dragover { border-color: #007bff; background: #f8f9ff; }
        .image-preview { max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1><?php echo $editEvent ? 'Edit Event' : 'Manage Events'; ?></h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Event Form -->
        <div class="form-container">
            <h2><?php echo $editEvent ? 'Edit Event' : 'Add New Event'; ?></h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $editEvent ? 'update_event' : 'add_event'; ?>">
                <?php if ($editEvent): ?>
                    <input type="hidden" name="event_id" value="<?php echo $editEvent['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="title">Event Title *</label>
                    <input type="text" id="title" name="title" required
                           value="<?php echo $editEvent ? htmlspecialchars($editEvent['title']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Short Description *</label>
                    <textarea id="description" name="description" required><?php echo $editEvent ? htmlspecialchars($editEvent['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="long_description">Detailed Description</label>
                    <textarea id="long_description" name="long_description" style="height: 150px;"><?php echo $editEvent ? htmlspecialchars($editEvent['long_description']) : ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="event_date">Event Date *</label>
                        <input type="date" id="event_date" name="event_date" required
                               value="<?php echo $editEvent ? $editEvent['event_date'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="start_time">Start Time *</label>
                        <input type="time" id="start_time" name="start_time" required
                               value="<?php echo $editEvent ? $editEvent['start_time'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time"
                               value="<?php echo $editEvent ? $editEvent['end_time'] : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="venue">Venue *</label>
                        <input type="text" id="venue" name="venue" required
                               value="<?php echo $editEvent ? htmlspecialchars($editEvent['venue']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="technical" <?php echo ($editEvent && $editEvent['category'] === 'technical') ? 'selected' : ''; ?>>Technical</option>
                            <option value="cultural" <?php echo ($editEvent && $editEvent['category'] === 'cultural') ? 'selected' : ''; ?>>Cultural</option>
                            <option value="sports" <?php echo ($editEvent && $editEvent['category'] === 'sports') ? 'selected' : ''; ?>>Sports</option>
                            <option value="workshop" <?php echo ($editEvent && $editEvent['category'] === 'workshop') ? 'selected' : ''; ?>>Workshop</option>
                            <option value="seminar" <?php echo ($editEvent && $editEvent['category'] === 'seminar') ? 'selected' : ''; ?>>Seminar</option>
                            <option value="competition" <?php echo ($editEvent && $editEvent['category'] === 'competition') ? 'selected' : ''; ?>>Competition</option>
                            <option value="other" <?php echo ($editEvent && $editEvent['category'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="max_participants">Max Participants</label>
                        <input type="number" id="max_participants" name="max_participants" min="0"
                               value="<?php echo $editEvent ? $editEvent['max_participants'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="registration_fee">Registration Fee ($)</label>
                        <input type="number" id="registration_fee" name="registration_fee" min="0" step="0.01"
                               value="<?php echo $editEvent ? $editEvent['registration_fee'] : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="registration_deadline">Registration Deadline</label>
                        <input type="datetime-local" id="registration_deadline" name="registration_deadline"
                               value="<?php echo $editEvent && $editEvent['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($editEvent['registration_deadline'])) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="image">Event Image</label>
                    <input type="hidden" id="image" name="image" value="<?php echo $editEvent ? htmlspecialchars($editEvent['image']) : ''; ?>">
                    <div class="upload-area" id="uploadArea">
                        <p>Click to upload or drag and drop an image</p>
                        <input type="file" id="imageFile" accept="image/*" style="display: none;">
                    </div>
                    <?php if ($editEvent && $editEvent['image']): ?>
                        <img src="../<?php echo htmlspecialchars($editEvent['image']); ?>" alt="Current image" class="image-preview" id="imagePreview">
                    <?php else: ?>
                        <img id="imagePreview" class="image-preview" style="display: none;">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="featured" <?php echo ($editEvent && $editEvent['featured']) ? 'checked' : ''; ?>>
                        Featured Event (will appear on homepage)
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editEvent ? 'Update Event' : 'Add Event'; ?>
                    </button>
                    <?php if ($editEvent): ?>
                        <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Events List -->
        <div class="form-container">
            <h2>All Events</h2>

            <?php if (empty($events)): ?>
                <p>No events found. <a href="manage_events.php">Add your first event</a>.</p>
            <?php else: ?>
                <table class="events-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Venue</th>
                            <th>Registrations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                    <?php if ($event['featured']): ?>
                                        <span style="color: #ffc107; font-size: 12px;">★ Featured</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                <td><?php echo ucfirst($event['category']); ?></td>
                                <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                <td><?php echo $event['registration_count']; ?>/<?php echo $event['max_participants'] ?: '∞'; ?></td>
                                <td>
                                    <span style="color: <?php echo $event['status'] === 'approved' ? 'green' : ($event['status'] === 'pending' ? 'orange' : 'red'); ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="manage_events.php?edit=<?php echo $event['id']; ?>" class="btn btn-secondary" style="font-size: 12px; padding: 5px 10px;">Edit</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size: 12px; padding: 5px 10px;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Image upload functionality
        const uploadArea = document.getElementById('uploadArea');
        const imageFile = document.getElementById('imageFile');
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');

        uploadArea.addEventListener('click', () => imageFile.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });

        imageFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileUpload(e.target.files[0]);
            }
        });

        function handleFileUpload(file) {
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file.');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'event');
            formData.append('csrf_token', '<?php echo SecurityHelper::generateCSRFToken(); ?>');

            uploadArea.innerHTML = '<p>Uploading...</p>';

            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    imageInput.value = data.filepath;
                    imagePreview.src = '../' + data.filepath;
                    imagePreview.style.display = 'block';
                    uploadArea.innerHTML = '<p>✓ Image uploaded successfully</p>';
                } else {
                    alert('Upload failed: ' + data.message);
                    uploadArea.innerHTML = '<p>Click to upload or drag and drop an image</p>';
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Upload failed. Please try again.');
                uploadArea.innerHTML = '<p>Click to upload or drag and drop an image</p>';
            });
        }
    </script>
</body>
</html>
