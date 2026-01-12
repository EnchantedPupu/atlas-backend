<?php
session_start();

// Simple admin credentials (in production, use database)
$admin_username = 'admin';
$admin_password = 'admin123';

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: admin_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATLAS Admin - Login</title>
    <link rel="icon" type="image/png" href="assets/images/atlas-logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/atlas-logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 25%, #c084fc 50%, #d8b4fe 75%, #e9d5ff 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 0.5rem;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            animation: float 20s ease-in-out infinite;
            z-index: -1;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(1deg); }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.2);
            width: 100%;
            max-width: min(380px, calc(100vw - 1rem));
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.2);
            height: fit-content;
            max-height: calc(100vh - 1rem);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
            flex-shrink: 0;
        }
        
        .logo {
            width: min(90px, 20vw);
            height: min(90px, 20vw);
            margin: 0 auto 0.75rem;
            display: block;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }
        
        .logo:hover {
            transform: scale(1.05);
        }
        
        .login-header h1 {
            font-size: clamp(1.6rem, 4vw, 2rem);
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #7c3aed, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .login-header p {
            color: #64748b;
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            font-weight: 500;
        }
        
        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #7c3aed, #a855f7);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: clamp(0.65rem, 1.5vw, 0.75rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
            flex-shrink: 0;
        }
        
        .form-group {
            margin-bottom: 1rem;
            flex-shrink: 0;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #7c3aed;
            font-weight: 600;
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
        }
        
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.75rem 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #a855f7;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.1);
            transform: translateY(-1px);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #7c3aed, #a855f7);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: clamp(0.95rem, 2vw, 1.05rem);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
            flex-shrink: 0;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #6d28d9, #9333ea);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error {
            color: #dc2626;
            text-align: center;
            margin-top: 1rem;
            padding: 0.75rem;
            background: rgba(254, 226, 226, 0.8);
            border: 1px solid #fecaca;
            border-radius: 12px;
            font-weight: 500;
            backdrop-filter: blur(10px);
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            flex-shrink: 0;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            flex-shrink: 0;
        }
        
        .back-link a {
            color: #7c3aed;
            text-decoration: none;
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            font-weight: 600;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: rgba(124, 58, 237, 0.05);
            display: inline-block;
        }
        
        .back-link a:hover {
            color: #a855f7;
            background: rgba(168, 85, 247, 0.1);
            transform: translateY(-1px);
        }
        
        /* Mobile Landscape - Compact layout */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 0.25rem;
            }
            
            .login-container {
                padding: 1rem;
                max-height: calc(100vh - 0.5rem);
            }
            
            .login-header {
                margin-bottom: 1rem;
            }
            
            .logo {
                width: 60px;
                height: 60px;
                margin-bottom: 0.5rem;
            }
            
            .admin-badge {
                padding: 0.25rem 0.5rem;
                margin-bottom: 0.5rem;
            }
            
            .form-group {
                margin-bottom: 0.75rem;
            }
            
            .back-link {
                margin-top: 0.75rem;
                padding-top: 0.75rem;
            }
        }
        
        /* Very Short Screens */
        @media (max-height: 500px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-header {
                margin-bottom: 0.75rem;
            }
            
            .logo {
                width: 50px;
                height: 50px;
                margin-bottom: 0.5rem;
            }
            
            .admin-badge {
                padding: 0.2rem 0.4rem;
                margin-bottom: 0.25rem;
            }
            
            .form-group {
                margin-bottom: 0.5rem;
            }
            
            input[type="text"], input[type="password"] {
                padding: 0.6rem 0.75rem;
            }
            
            .btn-login {
                padding: 0.6rem;
            }
            
            .back-link {
                margin-top: 0.5rem;
                padding-top: 0.5rem;
            }
        }
        
        /* Small Mobile Portrait */
        @media (max-width: 360px) {
            body {
                padding: 0.25rem;
            }
            
            .login-container {
                padding: 1.25rem;
                border-radius: 16px;
            }
            
            .logo {
                width: 70px;
                height: 70px;
            }
        }
        
        /* Very Small Screens */
        @media (max-width: 320px) {
            .login-container {
                padding: 1rem;
            }
            
            .logo {
                width: 60px;
                height: 60px;
            }
        }
        
        /* Tablet and larger */
        @media (min-width: 768px) and (min-height: 700px) {
            body {
                padding: 1rem;
            }
            
            .login-container {
                padding: 2rem;
                max-width: 400px;
            }
            
            .logo {
                width: 100px;
                height: 100px;
                margin-bottom: 1rem;
            }
            
            .login-header {
                margin-bottom: 2rem;
            }
            
            .admin-badge {
                padding: 0.4rem 0.8rem;
                margin-bottom: 0.75rem;
            }
            
            .form-group {
                margin-bottom: 1.25rem;
            }
            
            input[type="text"], input[type="password"] {
                padding: 0.875rem 1rem;
            }
            
            .btn-login {
                padding: 0.875rem;
            }
            
            .back-link {
                margin-top: 1.5rem;
                padding-top: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="assets/images/atlas-logo.png" alt="ATLAS Logo" class="logo">
            <div class="admin-badge">Administrator</div>
            <h1>ATLAS</h1>
            <p>Admin Panel - Please login to continue</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Admin Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" name="login" class="btn-login">Login</button>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
        </form>
        
        <div class="back-link">
            <a href="index.php">← Back to User Login</a>
        </div>
    </div>
</body>
</html>
