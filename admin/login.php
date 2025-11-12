<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (isset($_SESSION['user_id']) && hasPermission(ROLE_ADMIN)) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();


$email = '';
$password = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Require admin role
            if ($user['role'] !== ROLE_ADMIN) {
                $error = 'You do not have permission to access admin panel.';
            } else {
                $resolvedId = isset($user['id']) ? (int)$user['id'] : (isset($user['user_id']) ? (int)$user['user_id'] : 0);
                $resolvedName = isset($user['username']) ? $user['username'] : ($user['name'] ?? ($user['email'] ?? 'User'));
                $_SESSION['user_id'] = $resolvedId;
                $_SESSION['user_name'] = $resolvedName;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: ' . SITE_URL . '/admin/dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
    <link rel="stylesheet" href="./admin.css">
</head>
<body>
    <div style="display:grid;place-items:center;min-height:100vh;padding:1rem;">
        <div class="card" style="width:100%;max-width:420px;">
            <h2 style="margin:0 0 .25rem;">Admin Login</h2>
            <p style="margin:0 0 1rem;color:#94a3b8;">Sign in to manage the site</p>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" autocomplete="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                </div>
                <button class="btn" type="submit">Sign in</button>
            </form>
        </div>
    </div>
</body>
</html>


