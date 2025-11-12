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
    
    if ($action === 'add_gallery_item') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_id = $_POST['event_id'] ? (int)$_POST['event_id'] : null;
        $category = $_POST['category'] ?? '';
        $file_path = $_POST['file_path'] ?? '';
        $file_type = $_POST['file_type'] ?? 'image';
        
        if ($title && $description && $file_path && $category) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO gallery (
                        title, description, event_id, category, file_path, file_type, 
                        uploaded_by, status, upload_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
                ");
                
                $stmt->execute([
                    $title, $description, $event_id, $category, $file_path, $file_type, $_SESSION['user_id']
                ]);
                
                $message = 'Gallery item added successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding gallery item: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_gallery_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_id = $_POST['event_id'] ? (int)$_POST['event_id'] : null;
        $category = $_POST['category'] ?? '';
        $file_path = $_POST['file_path'] ?? '';
        $file_type = $_POST['file_type'] ?? 'image';
        
        if ($item_id && $title && $description && $file_path && $category) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE gallery SET 
                        title = ?, description = ?, event_id = ?, category = ?, 
                        file_path = ?, file_type = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $title, $description, $event_id, $category, $file_path, $file_type, $item_id
                ]);
                
                $message = 'Gallery item updated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating gallery item: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete_gallery_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        
        if ($item_id) {
            try {
                // Get file path to delete file
                $stmt = $pdo->prepare("SELECT file_path FROM gallery WHERE id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete from database
                $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
                $stmt->execute([$item_id]);
                
                // Delete file if exists
                if ($item && $item['file_path'] && file_exists('../' . $item['file_path'])) {
                    unlink('../' . $item['file_path']);
                }
                
                $message = 'Gallery item deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting gallery item: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get all gallery items
$stmt = $pdo->query("
    SELECT g.*, e.title as event_title, u.first_name, u.last_name
    FROM gallery g 
    LEFT JOIN events e ON g.event_id = e.id
    LEFT JOIN users u ON g.uploaded_by = u.id 
    ORDER BY g.upload_date DESC
");
$galleryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all events for dropdown
$stmt = $pdo->query("SELECT id, title FROM events ORDER BY title");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get gallery item for editing if requested
$editItem = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM gallery WHERE id = ?");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Gallery - Admin Panel</title>
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
        .gallery-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .gallery-table th, .gallery-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .gallery-table th { background: #f8f9fa; font-weight: bold; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .upload-area { border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 4px; margin-top: 10px; }
        .upload-area.dragover { border-color: #007bff; background: #f8f9ff; }
        .image-preview { max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 4px; }
        .gallery-thumbnail { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1><?php echo $editItem ? 'Edit Gallery Item' : 'Manage Gallery'; ?></h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Gallery Item Form -->
        <div class="form-container">
            <h2><?php echo $editItem ? 'Edit Gallery Item' : 'Add New Gallery Item'; ?></h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $editItem ? 'update_gallery_item' : 'add_gallery_item'; ?>">
                <?php if ($editItem): ?>
                    <input type="hidden" name="item_id" value="<?php echo $editItem['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required
                           value="<?php echo $editItem ? htmlspecialchars($editItem['title']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" required><?php echo $editItem ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="event_id">Related Event</label>
                        <select id="event_id" name="event_id">
                            <option value="">Select Event (Optional)</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>"
                                        <?php echo ($editItem && $editItem['event_id'] == $event['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="technical" <?php echo ($editItem && $editItem['category'] === 'technical') ? 'selected' : ''; ?>>Technical</option>
                            <option value="cultural" <?php echo ($editItem && $editItem['category'] === 'cultural') ? 'selected' : ''; ?>>Cultural</option>
                            <option value="sports" <?php echo ($editItem && $editItem['category'] === 'sports') ? 'selected' : ''; ?>>Sports</option>
                            <option value="workshop" <?php echo ($editItem && $editItem['category'] === 'workshop') ? 'selected' : ''; ?>>Workshop</option>
                            <option value="general" <?php echo ($editItem && $editItem['category'] === 'general') ? 'selected' : ''; ?>>General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="file_type">File Type *</label>
                        <select id="file_type" name="file_type" required>
                            <option value="image" <?php echo ($editItem && $editItem['file_type'] === 'image') ? 'selected' : ''; ?>>Image</option>
                            <option value="video" <?php echo ($editItem && $editItem['file_type'] === 'video') ? 'selected' : ''; ?>>Video</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="file_path">Media File *</label>
                    <input type="hidden" id="file_path" name="file_path" value="<?php echo $editItem ? htmlspecialchars($editItem['file_path']) : ''; ?>">
                    <div class="upload-area" id="uploadArea">
                        <p>Click to upload or drag and drop a file</p>
                        <input type="file" id="mediaFile" accept="image/*,video/*" style="display: none;">
                    </div>
                    <?php if ($editItem && $editItem['file_path']): ?>
                        <?php if ($editItem['file_type'] === 'image'): ?>
                            <img src="../<?php echo htmlspecialchars($editItem['file_path']); ?>" alt="Current image" class="image-preview" id="mediaPreview">
                        <?php else: ?>
                            <video controls class="image-preview" id="mediaPreview">
                                <source src="../<?php echo htmlspecialchars($editItem['file_path']); ?>" type="video/mp4">
                            </video>
                        <?php endif; ?>
                    <?php else: ?>
                        <img id="mediaPreview" class="image-preview" style="display: none;">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editItem ? 'Update Gallery Item' : 'Add Gallery Item'; ?>
                    </button>
                    <?php if ($editItem): ?>
                        <a href="manage_gallery.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Gallery Items List -->
        <div class="form-container">
            <h2>All Gallery Items</h2>

            <?php if (empty($galleryItems)): ?>
                <p>No gallery items found. <a href="manage_gallery.php">Add your first gallery item</a>.</p>
            <?php else: ?>
                <table class="gallery-table">
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($galleryItems as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item['file_type'] === 'image'): ?>
                                        <img src="../<?php echo htmlspecialchars($item['file_path']); ?>"
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             class="gallery-thumbnail">
                                    <?php else: ?>
                                        <video class="gallery-thumbnail" muted>
                                            <source src="../<?php echo htmlspecialchars($item['file_path']); ?>" type="video/mp4">
                                        </video>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                <td><?php echo ucfirst($item['category']); ?></td>
                                <td><?php echo $item['event_title'] ? htmlspecialchars($item['event_title']) : 'General'; ?></td>
                                <td><?php echo ucfirst($item['file_type']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($item['upload_date'])); ?></td>
                                <td>
                                    <a href="manage_gallery.php?edit=<?php echo $item['id']; ?>" class="btn btn-secondary" style="font-size: 12px; padding: 5px 10px;">Edit</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this gallery item?');">
                                        <input type="hidden" name="action" value="delete_gallery_item">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
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
        // Media upload functionality
        const uploadArea = document.getElementById('uploadArea');
        const mediaFile = document.getElementById('mediaFile');
        const filePathInput = document.getElementById('file_path');
        const mediaPreview = document.getElementById('mediaPreview');
        const fileTypeSelect = document.getElementById('file_type');

        uploadArea.addEventListener('click', () => mediaFile.click());

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

        mediaFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileUpload(e.target.files[0]);
            }
        });

        function handleFileUpload(file) {
            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');

            if (!isImage && !isVideo) {
                alert('Please select an image or video file.');
                return;
            }

            // Auto-set file type based on uploaded file
            fileTypeSelect.value = isImage ? 'image' : 'video';

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'gallery');
            formData.append('csrf_token', '<?php echo SecurityHelper::generateCSRFToken(); ?>');

            uploadArea.innerHTML = '<p>Uploading...</p>';

            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    filePathInput.value = data.filepath;

                    if (isImage) {
                        mediaPreview.innerHTML = '';
                        const img = document.createElement('img');
                        img.src = '../' + data.filepath;
                        img.className = 'image-preview';
                        mediaPreview.appendChild(img);
                    } else {
                        mediaPreview.innerHTML = '';
                        const video = document.createElement('video');
                        video.src = '../' + data.filepath;
                        video.className = 'image-preview';
                        video.controls = true;
                        mediaPreview.appendChild(video);
                    }

                    mediaPreview.style.display = 'block';
                    uploadArea.innerHTML = '<p>✓ File uploaded successfully</p>';
                } else {
                    alert('Upload failed: ' + data.message);
                    uploadArea.innerHTML = '<p>Click to upload or drag and drop a file</p>';
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Upload failed. Please try again.');
                uploadArea.innerHTML = '<p>Click to upload or drag and drop a file</p>';
            });
        }
    </script>
</body>
</html>
