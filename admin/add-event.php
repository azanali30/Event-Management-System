<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once './_error_handler.php';

admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

// Local CSRF helpers (fallback if global SecurityHelper is unavailable)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!function_exists('csrf_ensure_token')) {
    function csrf_ensure_token(): void {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    function csrf_field(): string {
        csrf_ensure_token();
        $t = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $t . '">';
    }
    function csrf_validate(string $token): bool {
        csrf_ensure_token();
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Clear previous messages
ErrorHandler::clear();

// Server-side processing: validate, handle uploads, insert into DB
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_validate($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = strtolower(trim($_POST['category'] ?? ''));
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $max_participants = (int)($_POST['max_participants'] ?? 0);
        $reg_deadline = trim($_POST['registration_deadline'] ?? '');
        // Normalize datetime-local (YYYY-MM-DDTHH:MM) -> MySQL DATETIME (YYYY-MM-DD HH:MM:SS)
        if ($reg_deadline !== '') {
            $reg_deadline = str_replace('T', ' ', $reg_deadline);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $reg_deadline)) {
                $reg_deadline .= ':00';
            }
        }
        // Extra fields from form are ignored by current table schema
        $department = trim($_POST['department'] ?? '');
        $staff_names = trim($_POST['staff_names'] ?? '');
        $waitlist_enabled = isset($_POST['waitlist']) ? 1 : 0;
        $certificate_required = isset($_POST['certificate_required']) ? (int)$_POST['certificate_required'] : 0;

        $errors = [];
        if ($title === '') { $errors[] = 'Event Title is required.'; }
        if ($description === '') { $errors[] = 'Description is required.'; }
        if ($category === '') { $errors[] = 'Category is required.'; }
        if ($date === '') { $errors[] = 'Date is required.'; }
        if ($time === '') { $errors[] = 'Time is required.'; }
        if ($venue === '') { $errors[] = 'Venue is required.'; }
        if ($max_participants < 1) { $errors[] = 'Maximum Participants must be at least 1.'; }
        if ($reg_deadline === '') { $errors[] = 'Registration Deadline is required.'; }
        if ($department === '') { $errors[] = 'Organizing Department is required.'; }
        if ($staff_names === '') { $errors[] = 'Organizing Staff Name(s) is required.'; }

        $organizerId = $_SESSION['user_id'] ?? null;
        if (!$organizerId) { $errors[] = 'Organizer not found in session.'; }

        if ($certificate_required === 1) {
            $fee = (float)($_POST['certificate_fee'] ?? 0);
            if ($fee < 0) { $errors[] = 'Certificate Fee cannot be negative.'; }
        }

        // Handle file uploads (optional; not stored in current schema)
        $banner_path = null;
        $rulebook_path = null;

        $uploadsRoot = realpath(__DIR__ . '/../uploads');
        if ($uploadsRoot === false) { throw new Exception('Uploads directory not found.'); }
        $eventsDir = $uploadsRoot . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR;
        $docsDir = $uploadsRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR;
        if (!is_dir($eventsDir)) { @mkdir($eventsDir, 0775, true); }
        if (!is_dir($docsDir)) { @mkdir($docsDir, 0775, true); }

        if (!empty($_FILES['banner']['name'])) {
            if ($_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                $mime = mime_content_type($_FILES['banner']['tmp_name']);
                if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) {
                    $errors[] = 'Banner image must be JPG, PNG, WEBP, or GIF.';
                } else {
                    $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                    $safe = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $eventsDir . $safe;
                    if (!move_uploaded_file($_FILES['banner']['tmp_name'], $dest)) {
                        $errors[] = 'Failed to save banner image.';
                    } else {
                        $banner_path = 'uploads/events/' . $safe;
                    }
                }
            } else {
                $errors[] = 'Error uploading banner image.';
            }
        }

        if (!empty($_FILES['rulebook']['name'])) {
            if ($_FILES['rulebook']['error'] === UPLOAD_ERR_OK) {
                $mime = mime_content_type($_FILES['rulebook']['tmp_name']);
                if ($mime !== 'application/pdf') {
                    $errors[] = 'Rulebook must be a PDF file.';
                } else {
                    $safe = 'rulebook_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    $dest = $docsDir . $safe;
                    if (!move_uploaded_file($_FILES['rulebook']['tmp_name'], $dest)) {
                        $errors[] = 'Failed to save rulebook PDF.';
                    } else {
                        $rulebook_path = 'uploads/docs/' . $safe;
                    }
                }
            } else {
                $errors[] = 'Error uploading rulebook PDF.';
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $e) { ErrorHandler::addError($e); }
        } else {
            // Ensure exceptions for this insert even if global PDO isn't set
            try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $t) { /* ignore */ }

            // Insert only columns available in provided schema
            $stmt = $pdo->prepare(
                "INSERT INTO events (
                    title, description, category, event_date, event_time, venue, organizer_id, status, created_at
                ) VALUES (
                    :title, :description, :category, :event_date, :event_time, :venue, :organizer_id, 'pending', NOW()
                )"
            );

            $params = [
                ':title' => $title,
                ':description' => $description,
                ':category' => $category,
                ':event_date' => $date,
                ':event_time' => $time,
                ':venue' => $venue,
                ':organizer_id' => $organizerId,
            ];

            $ok = false;
            try {
                $ok = $stmt->execute($params);
            } catch (Throwable $ex) {
                ErrorHandler::addError('Database error while saving event: ' . $ex->getMessage());
            }

            if ($ok) {
                // Redirect to events page with success flag
                header('Location: ./events.php?created=1');
                exit;
            } else {
                $info = $stmt->errorInfo();
                if (!empty($info[2])) {
                    ErrorHandler::addError('Failed to save event: ' . $info[2]);
                } else {
                    ErrorHandler::addError('Failed to save event due to an unknown error.');
                }
            }
        }
    } catch (Exception $e) {
        ErrorHandler::addError($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
                    <a href="./events.php" class="active">
                        <i data-lucide="calendar"></i>
                        <span>Events</span>
                    </a>
                    <a href="./feedback.php">
                        <i data-lucide="message-square"></i>
                        <span>Feedback</span>
                    </a>
                    <a href="./gallery.php">
                        <i data-lucide="image"></i>
                        <span>Media Gallery</span>
                    </a>
                    <a href="./certificates.php">
                        <i data-lucide="award"></i>
                        <span>Certificates</span>
                    </a>
                    <a href="./announcements.php">
                        <i data-lucide="megaphone"></i>
                        <span>Announcements</span>
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

        <main class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Add New Event</h1>
                </div>
                <div class="topbar-right">
                    <div class="user-menu">
                        <span>Admin</span>
                        <i data-lucide="chevron-down"></i>
                    </div>
                </div>
            </header>

            <div class="content">
                <div class="breadcrumbs">
                    <a href="./dashboard.php">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="./events.php">Events</a>
                    <span class="breadcrumb-separator">/</span>
                    <span>Add Event</span>
                </div>

                <?php ErrorHandler::displayMessages(); ?>

                <div class="form-container">
                    <div class="form-header">
                        <h3>Event Details</h3>
                        <p>Fill in the information below to create a new event.</p>
                    </div>

                    <form id="addEventForm" action="add-event.php" method="post" enctype="multipart/form-data" novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="form-grid">
                            <div class="field">
                                <label for="title" class="required">Event Title</label>
                                <input class="input" type="text" id="title" name="title" placeholder="Enter event title" required>
                                <div class="error" data-error-for="title"></div>
                            </div>

                            <div class="field">
                                <label for="category" class="required">Category</label>
                                <select class="select" id="category" name="category" required>
                                    <option value="">Select category</option>
                                    <option value="Technical">Technical</option>
                                    <option value="Cultural">Cultural</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Workshop">Workshop</option>
                                </select>
                                <div class="error" data-error-for="category"></div>
                            </div>

                            <div class="field">
                                <label for="date" class="required">Date</label>
                                <input class="input" type="date" id="date" name="date" required>
                                <div class="error" data-error-for="date"></div>
                            </div>

                            <div class="field">
                                <label for="time" class="required">Time</label>
                                <input class="input" type="time" id="time" name="time" required>
                                <div class="error" data-error-for="time"></div>
                            </div>

                            <div class="field">
                                <label for="venue" class="required">Venue</label>
                                <input class="input" type="text" id="venue" name="venue" placeholder="e.g., Main Auditorium" required>
                                <div class="error" data-error-for="venue"></div>
                            </div>

                            <div class="field">
                                <label for="max_participants" class="required">Maximum Participants</label>
                                <input class="input" type="number" id="max_participants" name="max_participants" min="1" step="1" required>
                                <div class="error" data-error-for="max_participants"></div>
                            </div>

                            <div class="field">
                                <label for="registration_deadline" class="required">Registration Deadline</label>
                                <input class="input" type="datetime-local" id="registration_deadline" name="registration_deadline" required>
                                <div class="error" data-error-for="registration_deadline"></div>
                            </div>

                            <div class="field">
                                <label for="department">Organizing Department</label>
                                <input class="input" type="text" id="department" name="department" placeholder="e.g., CSE Department">
                            </div>

                            <div class="field">
                                <label for="staff_names">Organizing Staff Name(s)</label>
                                <input class="input" type="text" id="staff_names" name="staff_names" placeholder="Comma-separated names">
                            </div>
                        </div>

                        <div class="field">
                            <label for="description" class="required">Event Description</label>
                            <textarea class="textarea" id="description" name="description" rows="5" placeholder="Describe the event, rules, eligibility, etc." required></textarea>
                        </div>

                        <div class="form-grid">
                            <div class="field">
                                <label class="required">Certificate Required</label>
                                <div class="flex gap-2">
                                    <label class="flex items-center gap-2"><input type="radio" name="certificate_required" value="1" id="cert_yes"> Yes</label>
                                    <label class="flex items-center gap-2"><input type="radio" name="certificate_required" value="0" id="cert_no" checked> No</label>
                                </div>
                            </div>

                            <div class="field" id="certificate_fee_group" style="display:none;">
                                <label for="certificate_fee">Certificate Fee (₹)</label>
                                <input class="input" type="number" id="certificate_fee" name="certificate_fee" min="0" step="0.01" placeholder="0.00">
                            </div>

                            <div class="field">
                                <label class="flex items-center gap-2" for="waitlist">
                                    <input type="checkbox" id="waitlist" name="waitlist" value="1"> Waitlist Enabled
                                </label>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="field">
                                <label for="banner">Upload Banner Image</label>
                                <input class="input" type="file" id="banner" name="banner" accept="image/*">
                            </div>
                            <div class="field">
                                <label for="rulebook">Upload Rulebook/Document (PDF)</label>
                                <input class="input" type="file" id="rulebook" name="rulebook" accept="application/pdf">
                            </div>
                        </div>

                        <div class="page-actions">
                            <button type="submit" class="btn btn-primary">Create Event</button>
                            <a href="./events.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        // Dynamic Certificate Fee visibility
        const certYes = document.getElementById('cert_yes');
        const certNo = document.getElementById('cert_no');
        const feeGroup = document.getElementById('certificate_fee_group');
        function toggleFee() {
            const show = certYes.checked;
            feeGroup.style.display = show ? 'block' : 'none';
            const feeInput = document.getElementById('certificate_fee');
            if (show) { feeInput.setAttribute('required', 'required'); }
            else { feeInput.removeAttribute('required'); feeInput.value = ''; }
        }
        certYes.addEventListener('change', toggleFee);
        certNo.addEventListener('change', toggleFee);
        toggleFee();

        // Client-side validation (basic)
        const form = document.getElementById('addEventForm');
        form.addEventListener('submit', function(e) {
            let valid = true;
            const requiredIds = ['title','category','date','time','venue','max_participants','registration_deadline'];
            requiredIds.forEach(id => {
                const el = document.getElementById(id);
                const err = document.querySelector(`[data-error-for="${id}"]`);
                if (err) err.textContent = '';
                if (!el.value) {
                    valid = false;
                    if (err) err.textContent = 'This field is required.';
                }
            });

            if (certYes.checked) {
                const fee = document.getElementById('certificate_fee');
                if (!fee.value || Number(fee.value) < 0) {
                    valid = false;
                    alert('Certificate fee must be a non-negative number.');
                }
            }

            if (!valid) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>


