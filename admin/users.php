<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';

admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

$message = '';
$messageType = '';

function valid_role($role) {
    return in_array($role, ['admin', 'organizer', 'participant'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $name = trim($_POST['name'] ?? '');

        if (!$email) {
            $message = 'Valid email is required.';
            $messageType = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $messageType = 'error';
        } elseif (!valid_role($role)) {
            $message = 'Invalid role selected.';
            $messageType = 'error';
        } elseif (empty($name)) {
            $message = 'Name is required.';
            $messageType = 'error';
        } else {
            try {
                // Check if email already exists
                $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    $message = 'Email already exists.';
                    $messageType = 'error';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (email, password, role, name, created_at) VALUES (?, ?, ?, ?, NOW())');
                    $stmt->execute([$email, $hash, $role, $name]);

                    $message = 'User created successfully.';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $name = trim($_POST['name'] ?? '');

        if (!$id) {
            $message = 'Invalid user ID.';
            $messageType = 'error';
        } elseif (!$email) {
            $message = 'Valid email is required.';
            $messageType = 'error';
        } elseif (!valid_role($role)) {
            $message = 'Invalid role selected.';
            $messageType = 'error';
        } elseif (empty($name)) {
            $message = 'Name is required.';
            $messageType = 'error';
        } elseif ($password && strlen($password) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $messageType = 'error';
        } else {
            try {
                // Check if email exists for other users
                $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
                $checkStmt->execute([$email, $id]);
                if ($checkStmt->fetch()) {
                    $message = 'Email already exists for another user.';
                    $messageType = 'error';
                } else {
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('UPDATE users SET email=?, password=?, role=?, name=? WHERE user_id=?');
                        $stmt->execute([$email, $hash, $role, $name, $id]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET email=?, role=?, name=? WHERE user_id=?');
                        $stmt->execute([$email, $role, $name, $id]);
                    }
                    $message = 'User updated successfully.';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $message = 'Invalid user ID.';
            $messageType = 'error';
        } elseif ($id === (int)($_SESSION['user_id'] ?? 0)) {
            $message = 'You cannot delete your own account while logged in.';
            $messageType = 'error';
        } else {
            try {
                // Check if user exists and get role for logging
                $userStmt = $pdo->prepare('SELECT email, role FROM users WHERE user_id = ?');
                $userStmt->execute([$id]);
                $userData = $userStmt->fetch();

                if (!$userData) {
                    $message = 'User not found.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
                    $stmt->execute([$id]);

                    $message = 'User deleted successfully.';
                    $messageType = 'success';
                    error_log("Admin {$_SESSION['user_email']} deleted user {$userData['email']} (ID: $id)");
                }
            } catch (Exception $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'bulk_delete') {
        $user_ids = $_POST['user_ids'] ?? [];
        $current_user_id = (int)($_SESSION['user_id'] ?? 0);

        if (empty($user_ids)) {
            $message = 'No users selected for deletion.';
            $messageType = 'error';
        } else {
            try {
                $deleted_count = 0;
                foreach ($user_ids as $id) {
                    $id = (int)$id;
                    if ($id && $id !== $current_user_id) {
                        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
                        if ($stmt->execute([$id])) {
                            $deleted_count++;
                        }
                    }
                }

                if ($deleted_count > 0) {
                    $message = "Successfully deleted $deleted_count user(s).";
                    $messageType = 'success';
                } else {
                    $message = 'No users were deleted.';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'bulk_role_change') {
        $user_ids = $_POST['user_ids'] ?? [];
        $new_role = $_POST['new_role'] ?? '';
        $current_user_id = (int)($_SESSION['user_id'] ?? 0);

        if (empty($user_ids)) {
            $message = 'No users selected for role change.';
            $messageType = 'error';
        } elseif (!valid_role($new_role)) {
            $message = 'Invalid role selected.';
            $messageType = 'error';
        } else {
            try {
                $updated_count = 0;
                foreach ($user_ids as $id) {
                    $id = (int)$id;
                    if ($id && $id !== $current_user_id) {
                        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                        if ($stmt->execute([$new_role, $id])) {
                            $updated_count++;
                        }
                    }
                }

                if ($updated_count > 0) {
                    $message = "Successfully updated role for $updated_count user(s) to " . ucfirst($new_role) . ".";
                    $messageType = 'success';
                } else {
                    $message = 'No users were updated.';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Initialize default values
$admin_name = 'Admin';
$users = [];

try {
    // Get current admin user info
    $current_user_stmt = $pdo->prepare('SELECT name, email FROM users WHERE user_id = ?');
    $current_user_stmt->execute([$_SESSION['user_id']]);
    $current_user = $current_user_stmt->fetch();
    $admin_name = $current_user['name'] ?? 'Admin';

    // Handle search and filtering
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';

    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($role_filter) && in_array($role_filter, ['admin', 'organizer', 'participant'])) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $users_stmt = $pdo->prepare("SELECT user_id, email, role, name, created_at FROM users $where_clause ORDER BY user_id DESC");
    $users_stmt->execute($params);
    $users = $users_stmt->fetchAll();
} catch (Exception $e) {
    $message = 'Database operation failed. Please try again.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-logo">
                    <i data-lucide="calendar"></i>
                </div>
                <div class="brand-title">EventSphere</div>
                <div class="brand-subtitle">ADMIN PANEL</div>
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
                    <a href="./users.php" class="active">
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
                    <a href="./registration-approvals.php">
                        <i data-lucide="user-plus"></i>
                        <span>Registration Approvals</span>
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
        <main>
            <div class="topbar">
                <div class="topbar-left">
                    <div class="page-title">
                        <h1>Users Management</h1>
                        <p class="page-subtitle">Manage user accounts and permissions</p>
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
                    <span>Users</span>
                </div>

                <!-- Alerts -->
                <?php if ($message): ?>
                    <div class="alert <?php echo $messageType === 'error' ? 'alert-error' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Create User Form -->
                <div class="form-container">
                    <div class="form-header">
                        <h3>Create New User</h3>
                        <p>Add a new user to the system</p>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="create">
                        <div class="field">
                            <label for="create-name" class="required">Name</label>
                            <input id="create-name" class="input" type="text" name="name" required maxlength="100">
                        </div>
                        <div class="field">
                            <label for="create-email" class="required">Email</label>
                            <input id="create-email" class="input" type="email" name="email" required maxlength="255">
                        </div>
                        <div class="field">
                            <label for="create-password" class="required">Password</label>
                            <input id="create-password" class="input" type="password" name="password" required minlength="6" maxlength="255">
                        </div>
                        <div class="field">
                            <label for="create-role" class="required">Role</label>
                            <select id="create-role" class="select" name="role">
                                <option value="participant">Participant</option>
                                <option value="organizer">Organizer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <button class="btn" type="submit">
                                <i data-lucide="user-plus"></i>
                                Create User
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Edit User Form -->
                <div class="form-container" id="edit-form" style="display: none;">
                    <div class="form-header">
                        <h3>Edit User</h3>
                        <p>Update user information</p>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" id="edit-id" name="id">
                        <div class="field">
                            <label for="edit-name" class="required">Name</label>
                            <input id="edit-name" class="input" type="text" name="name" required maxlength="100">
                        </div>
                        <div class="field">
                            <label for="edit-email" class="required">Email</label>
                            <input id="edit-email" class="input" type="email" name="email" required maxlength="255">
                        </div>
                        <div class="field">
                            <label for="edit-password">New Password</label>
                            <input id="edit-password" class="input" type="password" name="password" placeholder="Leave blank to keep current password" minlength="6" maxlength="255">
                        </div>
                        <div class="field">
                            <label for="edit-role" class="required">Role</label>
                            <select id="edit-role" class="select" name="role">
                                <option value="participant">Participant</option>
                                <option value="organizer">Organizer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1; display: flex; gap: 1rem;">
                            <button class="btn" type="submit">
                                <i data-lucide="save"></i>
                                Update User
                            </button>
                            <button type="button" class="btn secondary" onclick="cancelEdit()">
                                <i data-lucide="x"></i>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Search and Filter -->
                <div class="table-container">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">All Users</h3>
                                <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Manage system users and their roles</p>
                            </div>
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <span style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Showing <?php echo count($users); ?> user(s)
                                    <?php if (!empty($search) || !empty($role_filter)): ?>
                                        (filtered)
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Search and Filter Form -->
                        <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                            <div class="field" style="flex: 1; min-width: 200px;">
                                <label for="search" style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; display: block;">Search Users</label>
                                <input type="text" id="search" name="search" class="input" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="field" style="min-width: 150px;">
                                <label for="role" style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; display: block;">Filter by Role</label>
                                <select id="role" name="role" class="select">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="organizer" <?php echo $role_filter === 'organizer' ? 'selected' : ''; ?>>Organizer</option>
                                    <option value="participant" <?php echo $role_filter === 'participant' ? 'selected' : ''; ?>>Participant</option>
                                </select>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="btn secondary">
                                    <i data-lucide="search"></i>
                                    Search
                                </button>
                                <a href="users.php" class="btn secondary">
                                    <i data-lucide="x"></i>
                                    Clear
                                </a>
                            </div>
                        </form>
                    </div>

                <!-- Users Table -->
                <div>

                    <?php if (empty($users)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i data-lucide="users" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                            <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">No users found</h3>
                            <p style="color: var(--text-secondary);">Create your first user using the form above.</p>
                        </div>
                    <?php else: ?>
                        <!-- Bulk Actions -->
                        <div id="bulk-actions" style="padding: 1rem 1.5rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); display: none;">
                            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                <span style="font-size: 0.875rem; color: var(--text-secondary);">
                                    <span id="selected-count">0</span> user(s) selected
                                </span>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <select id="bulk-role" class="select" style="min-width: 120px;">
                                        <option value="">Change Role</option>
                                        <option value="participant">Participant</option>
                                        <option value="organizer">Organizer</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button type="button" class="btn secondary sm" onclick="bulkRoleChange()">
                                        <i data-lucide="user-cog"></i>
                                        Apply
                                    </button>
                                    <button type="button" class="btn danger sm" onclick="bulkDelete()">
                                        <i data-lucide="trash-2"></i>
                                        Delete Selected
                                    </button>
                                    <button type="button" class="btn secondary sm" onclick="clearSelection()">
                                        <i data-lucide="x"></i>
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                        </th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="user-checkbox" value="<?php echo (int)$u['user_id']; ?>" onchange="updateBulkActions()" <?php echo $u['user_id'] == $_SESSION['user_id'] ? 'disabled title="Cannot select your own account"' : ''; ?>>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 500; color: var(--text-primary);">
                                                        <?php echo htmlspecialchars($u['name'] ?? $u['email']); ?>
                                                        <?php if ($u['user_id'] == $_SESSION['user_id']): ?>
                                                            <span style="font-size: 0.75rem; background: var(--primary-color); color: white; padding: 0.125rem 0.375rem; border-radius: 0.25rem; margin-left: 0.5rem;">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?php echo htmlspecialchars($u['email']); ?> • ID: <?php echo (int)$u['user_id']; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php
                                                    echo $u['role'] === 'admin' ? 'danger' :
                                                        ($u['role'] === 'organizer' ? 'warning' : 'info');
                                                ?>">
                                                    <?php echo ucfirst($u['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php echo date('M j, Y', strtotime($u['created_at'] ?? 'now')); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <button class="btn secondary sm" onclick="fillEdit(<?php echo (int)$u['user_id']; ?>,'<?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES); ?>','<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($u['role'], ENT_QUOTES); ?>')" title="Edit User">
                                                        <i data-lucide="edit"></i>
                                                    </button>
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int)$u['user_id']; ?>">
                                                        <button class="btn danger sm" type="submit" title="Delete User">
                                                            <i data-lucide="trash-2"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Fill edit form function
        function fillEdit(id, name, email, role) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            document.getElementById('edit-password').value = '';

            // Show edit form
            const editForm = document.getElementById('edit-form');
            editForm.style.display = 'block';
            editForm.scrollIntoView({behavior: 'smooth'});
        }

        // Cancel edit function
        function cancelEdit() {
            const editForm = document.getElementById('edit-form');
            editForm.style.display = 'none';

            // Clear form
            document.getElementById('edit-id').value = '';
            document.getElementById('edit-name').value = '';
            document.getElementById('edit-email').value = '';
            document.getElementById('edit-role').value = 'participant';
            document.getElementById('edit-password').value = '';
        }

        // Bulk actions functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.user-checkbox:not([disabled])');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulk-actions');
            const selectedCount = document.getElementById('selected-count');

            selectedCount.textContent = checkboxes.length;

            if (checkboxes.length > 0) {
                bulkActions.style.display = 'block';
            } else {
                bulkActions.style.display = 'none';
            }

            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.user-checkbox:not([disabled])');
            const selectAll = document.getElementById('select-all');
            selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
            selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('select-all').checked = false;
            updateBulkActions();
        }

        function bulkDelete() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select users to delete.');
                return;
            }

            if (!confirm(`Are you sure you want to delete ${checkboxes.length} user(s)? This action cannot be undone.`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="bulk_delete">';

            checkboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }

        function bulkRoleChange() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const newRole = document.getElementById('bulk-role').value;

            if (checkboxes.length === 0) {
                alert('Please select users to update.');
                return;
            }

            if (!newRole) {
                alert('Please select a role.');
                return;
            }

            if (!confirm(`Are you sure you want to change the role of ${checkboxes.length} user(s) to ${newRole}?`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="bulk_role_change">';
            form.innerHTML += `<input type="hidden" name="new_role" value="${newRole}">`;

            checkboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const action = form.querySelector('input[name="action"]')?.value;

                    if (action === 'create' || action === 'update') {
                        const email = form.querySelector('input[name="email"]')?.value;
                        const password = form.querySelector('input[name="password"]')?.value;

                        if (!email) {
                            e.preventDefault();
                            alert('Email is required.');
                            return false;
                        }

                        if (action === 'create' && !password) {
                            e.preventDefault();
                            alert('Password is required for new users.');
                            return false;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>

