<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once './_error_handler.php';
admin_require_login();

// Clear any previous errors
ErrorHandler::clear();

$db = new Database();
$pdo = $db->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $upload_path = null; // Initialize to avoid undefined variable errors
    try {
        // CSRF Protection
        if (!SecurityHelper::validateCSRFToken($_POST)) {
            throw new Exception('Invalid security token. Please try again.');
        }

        // Rate limiting
        $rate_limiter = new RateLimiter();
        if (!$rate_limiter->checkLimit('media_upload_' . $_SESSION['user_id'], 5, 300)) {
            throw new Exception('Too many upload attempts. Please wait before trying again.');
        }

        // Input validation
        $event_id = InputValidator::validateInteger($_POST['event_id'] ?? 0, 'Event');
        $caption = InputValidator::sanitizeString($_POST['caption'] ?? '', 500);

        // Validate inputs
        if (empty($_FILES['media_file']['name'])) {
            ErrorHandler::addError('Please select a media file to upload.');
        }

        if ($event_id <= 0) {
            ErrorHandler::addError('Please select an event.');
        }

        if (!ErrorHandler::hasErrors()) {
            $file = $_FILES['media_file'];
            $file_name = basename($file['name']);
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];

            // Check for upload errors
            if ($file_error !== UPLOAD_ERR_OK) {
                ErrorHandler::addError('File upload failed. Please try again.');
            } else {
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/avi', 'video/mov'];
                $file_type = mime_content_type($file_tmp);

                if (!in_array($file_type, $allowed_types)) {
                    ErrorHandler::addError('Invalid file type. Please upload images (JPEG, PNG, GIF) or videos (MP4, AVI, MOV).');
                } else {
                    // Check file size (max 10MB)
                    $max_size = 10 * 1024 * 1024; // 10MB
                    if ($file_size > $max_size) {
                        ErrorHandler::addError('File size too large. Maximum size is 10MB.');
                    } else {
                        // Create upload directory if it doesn't exist
                        $upload_dir = '../uploads/gallery/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        // Generate unique filename
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $unique_name;
                        $db_path = 'uploads/gallery/' . $unique_name;

                        // Move uploaded file
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            // Determine file type for database
                            $media_type = strpos($file_type, 'image/') === 0 ? 'image' : 'video';

                            // Insert into database
                            try {
                                $query = '
                                    INSERT INTO mediagallery (event_id, file_type, file_url, uploaded_by, caption, uploaded_on) 
                                    VALUES (?, ?, ?, ?, ?, NOW())
                                ';

                                $result = DatabaseHelper::execute($query, [
                                    $event_id,
                                    $media_type,
                                    $db_path,
                                    $_SESSION['user_id'],
                                    $caption
                                ]);

                                if ($result) {
                                    ErrorHandler::addSuccess('Media uploaded successfully!');
                                    // Clear form data
                                    $_POST = [];
                                } else {
                                    ErrorHandler::addError('Failed to save media information to database.');
                                    // Delete uploaded file if database insert failed
                                    if (file_exists($upload_path)) {
                                        unlink($upload_path);
                                    }
                                }
                            } catch (Exception $e) {
                                ErrorHandler::addError('Database error: Failed to save media information.');
                                error_log('Media upload database error: ' . $e->getMessage());
                                // Delete uploaded file if database insert failed
                                if (file_exists($upload_path)) {
                                    unlink($upload_path);
                                }
                            }
                        } else {
                            ErrorHandler::addError('Failed to upload file. Please try again.');
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        ErrorHandler::addError('An error occurred during file upload. Please try again.');
        error_log('Media upload error: ' . $e->getMessage());

        // Clean up uploaded file if it exists
        if ($upload_path !== null && file_exists($upload_path)) {
            unlink($upload_path);
        }
    }
}


// Get events for dropdown
try {
    $events = DatabaseHelper::fetchAll($pdo, 'SELECT event_id, title FROM events ORDER BY title');
    if (empty($events)) {
        $events = [];
    }   
} catch (Exception $e) {
    ErrorHandler::addError('Failed to load events.');
    error_log('Events query error: ' . $e->getMessage());
    $events = [];
}

// Get current admin user info
$current_user = DatabaseHelper::fetchOne($pdo, 'SELECT name, email FROM users WHERE user_id = ?', [$_SESSION['user_id']]);
$admin_name = $current_user['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Media - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <span class="dot"></span>
                <div class="brand-text">
                    <div class="brand-title">EventSphere</div>
                    <div class="brand-subtitle">Admin Panel</div>
                </div>
            </div>
            <nav class="nav">
                <div class="nav-section">
                    <div class="nav-section-title">Overview</div>
                    <a href="./dashboard.php">
                        <i data-lucide="layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="./users.php">
                        <i data-lucide="users"></i>
                        <span>Users</span>
                    </a>
                    <a href="./events.php">
                        <i data-lucide="calendar"></i>
                        <span>Events</span>
                    </a>
                    <a href="./registrations.php">
                        <i data-lucide="user-check"></i>
                        <span>Registrations</span>
                    </a>
                    <a href="./gallery.php" class="active">
                        <i data-lucide="image"></i>
                        <span>Gallery</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Analytics</div>
                    <a href="./reports.php">
                        <i data-lucide="bar-chart-3"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="./settings.php">
                        <i data-lucide="settings"></i>
                        <span>Settings</span>
                    </a>
                    <a href="./logout.php" class="nav-logout">
                        <i data-lucide="log-out"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        <main>
            <div class="topbar">
                <div class="topbar-left">
                    <div class="page-title">
                        <h1>Add Media</h1>
                        <p class="page-subtitle">Upload new media files to the gallery</p>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-welcome">
                        <div class="welcome-text">
                            <span class="greeting">Welcome back,</span>
                            <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        </div>
                        <div class="user-avatar">
                            <i data-lucide="user-circle"></i>
                        </div>
                    </div>
                    <div class="topbar-actions">
                        <a class="btn-topbar" href="<?php echo SITE_URL; ?>" target="_blank" title="View Website">
                            <i data-lucide="external-link"></i>
                            <span>View Site</span>
                        </a>
                        <button class="btn-topbar" onclick="toggleNotifications()" title="Notifications">
                            <i data-lucide="bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="content">
                <!-- Breadcrumbs -->
                <div class="breadcrumbs">
                    <a href="./dashboard.php">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="./gallery.php">Gallery</a>
                    <span class="breadcrumb-separator">/</span>
                    <span>Add Media</span>
                </div>

                <!-- Alerts -->
                <?php ErrorHandler::displayMessages(); ?>

                <!-- Upload Form -->
                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data" class="form">
                        <?php echo SecurityHelper::generateCSRFToken(); ?>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="event_id" class="label">Event *</label>
                                <select name="event_id" id="event_id" class="select" required>
                                    <option value="">Select an event</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['event_id']; ?>" 
                                                <?php echo (isset($_POST['event_id']) && $_POST['event_id'] == $event['event_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="caption" class="label">Caption</label>
                                <input type="text" name="caption" id="caption" class="input" 
                                       placeholder="Enter a caption for this media..."
                                       value="<?php echo htmlspecialchars($_POST['caption'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="media_file" class="label">Media File *</label>
                            <div class="file-upload-area" onclick="document.getElementById('media_file').click()">
                                <input type="file" name="media_file" id="media_file" class="file-input" 
                                       accept="image/*,video/*" required onchange="updateFileName(this)">
                                <div class="file-upload-content">
                                    <i data-lucide="upload" style="width: 48px; height: 48px; color: var(--primary-color); margin-bottom: 1rem;"></i>
                                    <h3>Click to upload media</h3>
                                    <p>Supports images (JPEG, PNG, GIF) and videos (MP4, AVI, MOV)</p>
                                    <p>Maximum file size: 10MB</p>
                                    <div id="file-name" class="file-name"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="./gallery.php" class="btn secondary">
                                <i data-lucide="arrow-left"></i>
                                Back to Gallery
                            </a>
                            <button type="submit" class="btn">
                                <i data-lucide="upload"></i>
                                Upload Media
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        
        function toggleNotifications() {
            alert('Notifications feature coming soon!');
        }
        
        function updateFileName(input) {
            const fileName = document.getElementById('file-name');
            if (input.files && input.files[0]) {
                fileName.textContent = 'Selected: ' + input.files[0].name;
                fileName.style.display = 'block';
            } else {
                fileName.style.display = 'none';
            }
        }
    </script>
</body>
</html>
