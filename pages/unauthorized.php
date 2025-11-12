<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - EVENTSPHERE</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
        }
        
        .header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 40px 30px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .content h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        
        .content p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 1.1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,123,255,0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 10px 20px rgba(108,117,125,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🚫</div>
            <h1>Access Denied</h1>
        </div>
        
        <div class="content">
            <h2>Unauthorized Access</h2>
            <p>You don't have permission to access this resource. This area is restricted to administrators only.</p>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <p>You are logged in as: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></strong></p>
                <a href="../pages/dashboard.php" class="btn">Go to Dashboard</a>
                <a href="../pages/events.php" class="btn btn-secondary">View Events</a>
            <?php else: ?>
                <p>Please log in with an administrator account to access this area.</p>
                <a href="../admin/login.php" class="btn">Admin Login</a>
                <a href="../pages/login.php" class="btn btn-secondary">User Login</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
