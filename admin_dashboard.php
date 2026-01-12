<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// User roles
$roles = [
    'VO' => 'Valuation Officer',
    'OIC' => 'Officer In Charge',
    'SS' => 'Staff Surveyors',
    'FI' => 'Field Inspector',
    'AS' => 'Assistant Surveyor',
    'SD' => 'Senior Draughtman',
    'PP' => 'Pelukis Pelan'
];

// Handle user creation
if (isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $name = trim($_POST['name']);
    $profile_picture = null;
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $profile_picture = $upload_path;
            } else {
                $error = 'Failed to upload profile picture';
            }
        } else {
            $error = 'Invalid file format. Only JPG, JPEG, PNG, and GIF are allowed';
        }
    }
    
    if ($username && $password && $role && $name && !isset($error)) {
        try {
            // Check if username already exists
            $check_stmt = $db->prepare("SELECT user_id FROM User WHERE username = ?");
            $check_stmt->execute([$username]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'Username already exists';
            } else {
                // Hash password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO User (name, role, username, password, profile_picture, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $role, $username, $hashed_password, $profile_picture]);
                $success = 'User created successfully';
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else if (!isset($error)) {
        $error = 'All fields are required';
    }
}

// Handle profile picture update
if (isset($_POST['update_profile_picture'])) {
    $user_id = $_POST['user_id'];
    $profile_picture = null;
    
    if (isset($_FILES['new_profile_picture']) && $_FILES['new_profile_picture']['error'] == 0) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['new_profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['new_profile_picture']['tmp_name'], $upload_path)) {
                try {
                    // Get old profile picture to delete
                    $old_pic_stmt = $db->prepare("SELECT profile_picture FROM User WHERE user_id = ?");
                    $old_pic_stmt->execute([$user_id]);
                    $old_pic = $old_pic_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Update profile picture
                    $stmt = $db->prepare("UPDATE User SET profile_picture = ? WHERE user_id = ?");
                    $stmt->execute([$upload_path, $user_id]);
                    
                    // Delete old profile picture if exists
                    if ($old_pic['profile_picture'] && file_exists($old_pic['profile_picture'])) {
                        unlink($old_pic['profile_picture']);
                    }
                    
                    $success = 'Profile picture updated successfully';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Failed to upload profile picture';
            }
        } else {
            $error = 'Invalid file format. Only JPG, JPEG, PNG, and GIF are allowed';
        }
    } else {
        $error = 'Please select a valid image file';
    }
}

// Handle password reset
if (isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    if ($user_id && $new_password) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE User SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Get username for success message
            $user_stmt = $db->prepare("SELECT username FROM User WHERE user_id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            $success = 'Password reset successfully for user: ' . $user['username'];
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid user or password';
    }
}

// Handle role modification
if (isset($_POST['modify_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    if ($user_id && $new_role) {
        try {
            $stmt = $db->prepare("UPDATE User SET role = ? WHERE user_id = ?");
            $stmt->execute([$new_role, $user_id]);
            
            // Get username for success message
            $user_stmt = $db->prepare("SELECT username FROM User WHERE user_id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            $success = 'Role updated successfully for user: ' . $user['username'];
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid user or role';
    }
}

// Handle user deletion
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    try {
        // Get user's profile picture before deletion
        $pic_stmt = $db->prepare("SELECT profile_picture FROM User WHERE user_id = ?");
        $pic_stmt->execute([$user_id]);
        $user_data = $pic_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the user from database
        $stmt = $db->prepare("DELETE FROM User WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Delete profile picture file if it exists
        if ($user_data && $user_data['profile_picture'] && file_exists($user_data['profile_picture'])) {
            unlink($user_data['profile_picture']);
        }
        
        $success = 'User and associated files deleted successfully';
    } catch(PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get edit user data
$edit_user = null;
if (isset($_GET['edit_user'])) {
    $user_id = $_GET['edit_user'];
    try {
        $stmt = $db->prepare("SELECT * FROM User WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle role filter and search
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all users with optional role filtering and search
try {
    $sql = "SELECT * FROM User WHERE 1=1";
    $params = [];
    
    // Add search condition
    if ($search_query) {
        $sql .= " AND (name LIKE ? OR username LIKE ?)";
        $search_param = '%' . $search_query . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add role filter condition
    if ($role_filter && $role_filter !== 'all') {
        $sql .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $users_stmt = $db->prepare($sql);
    $users_stmt->execute($params);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total users count (unfiltered)
    $total_stmt = $db->prepare("SELECT COUNT(*) as total FROM User");
    $total_stmt->execute();
    $total_users = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get filtered users count
    $filtered_count = count($users);
    
    // Get role statistics
    $role_stats = [];
    foreach ($roles as $role_code => $role_name) {
        $role_count_stmt = $db->prepare("SELECT COUNT(*) as count FROM User WHERE role = ?");
        $role_count_stmt->execute([$role_code]);
        $role_stats[$role_code] = $role_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $users = [];
    $total_users = 0;
    $filtered_count = 0;
    $role_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            line-height: 1.6;
        }
        
        .navbar {
            background: #6b46c1;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 1.5rem;
        }
        
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-logout {
            background: #dc2626;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .btn-logout:hover {
            background: #b91c1c;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #6b46c1;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4c1d95;
            font-weight: 500;
        }
        
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="password"]:focus, select:focus {
            outline: none;
            border-color: #8b5cf6;
        }
        
        .btn-primary {
            background: #8b5cf6;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 0.25rem 0.5rem;
            text-decoration: none;
            border-radius: 3px;
            font-size: 0.8rem;
            transition: background-color 0.3s;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }
        
        .btn-secondary {
            background: #6366f1;
            color: white;
            padding: 0.25rem 0.5rem;
            text-decoration: none;
            border-radius: 3px;
            font-size: 0.8rem;
            transition: background-color 0.3s;
            margin-right: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: #4f46e5;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
            padding: 0.25rem 0.5rem;
            text-decoration: none;
            border-radius: 3px;
            font-size: 0.8rem;
            transition: background-color 0.3s;
            margin-right: 0.5rem;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .close:hover {
            color: #000;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .users-table th, .users-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .users-table th {
            background: #f3f4f6;
            color: #6b46c1;
            font-weight: 600;
        }
        
        .role-badge {
            background: #8b5cf6;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .alert {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #8b5cf6, #6b46c1);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-header {
            color: #6b46c1;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-select {
            min-width: 200px;
        }
        
        .btn-filter {
            background: #8b5cf6;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-filter:hover {
            background: #7c3aed;
        }
        
        .btn-clear {
            background: #6b7280;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .btn-clear:hover {
            background: #4b5563;
        }
        
        .filter-info {
            background: #f3f4f6;
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 1rem;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .role-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .role-stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #8b5cf6;
        }
        
        .role-stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #6b46c1;
            margin-bottom: 0.25rem;
        }
        
        .role-stat-label {
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .search-section {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 2px solid #e5e7eb;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #d1d5db;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            margin-bottom: 0.5rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #8b5cf6;
        }
        
        .search-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn-search {
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-search:hover {
            background: #059669;
        }
        
        .filter-info {
            background: #f3f4f6;
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 1rem;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .search-highlight {
            background-color: #fef3c7;
            padding: 0.1rem 0.2rem;
            border-radius: 3px;
        }
        
        .profile-picture-section {
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #8b5cf6;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 100%;
            aspect-ratio: 1;
        }
        
        .profile-picture-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #8b5cf6;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
            color: #6b7280;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .preview-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #8b5cf6;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: none;
        }
        
        .remove-preview {
            background: #dc2626;
            color: white;
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-top: 0.5rem;
            display: none;
        }
        
        .remove-preview:hover {
            background: #b91c1c;
        }
        
        .profile-preview-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .profile-info {
            flex: 1;
            min-width: 200px;
        }
        
        .profile-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .user-profile-pic {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #8b5cf6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .user-profile-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #e5e7eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #8b5cf6;
            font-size: 1.1rem;
            color: #6b7280;
            font-weight: bold;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .file-input {
            width: 100%;
            max-width: 300px;
            padding: 0.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        
        .file-input:focus {
            outline: none;
            border-color: #8b5cf6;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href='dashboard.php' style="text-decoration: none; color: white;"><h1>DATA Admin</h2></a>
        <div class="user-info">
            <span>Welcome, Admin</span>
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div>Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($roles); ?></div>
                <div>Available Roles</div>
            </div>
            <?php if ($role_filter && $role_filter !== 'all'): ?>
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="stat-number"><?php echo $filtered_count; ?></div>
                <div>Filtered Results</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Role Statistics -->
        <div class="role-stats">
            <?php foreach ($roles as $role_code => $role_name): ?>
            <div class="role-stat-card">
                <div class="role-stat-number"><?php echo $role_stats[$role_code] ?? 0; ?></div>
                <div class="role-stat-label"><?php echo $role_code; ?> - <?php echo $role_name; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <div class="card">
                <h2><?php echo $edit_user ? 'Edit User' : 'Create New User'; ?></h2>
                <?php if ($edit_user): ?>
                    <p><strong>Editing:</strong> <?php echo htmlspecialchars($edit_user['username']); ?></p>
                    <div style="margin: 1rem 0;">
                        <a href="dashboard.php" class="btn-secondary">← Back to Create New User</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if (!$edit_user): ?>
                        <div class="form-group">
                            <label>Profile Picture</label>
                            <div class="profile-picture-section">
                                <div class="profile-picture-placeholder" id="placeholder">
                                    👤
                                </div>
                                <img class="preview-image" id="preview" alt="Preview">
                                <input type="file" name="profile_picture" class="file-input" accept="image/*" id="profilePictureInput" onchange="previewImage(this, 'preview', 'placeholder', 'removeBtn')">
                                <button type="button" class="remove-preview" id="removeBtn" onclick="removePreview('profilePictureInput', 'preview', 'placeholder', 'removeBtn')">Remove</button>
                                <small style="color: #6b7280;">Optional - JPG, JPEG, PNG, or GIF format (max 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $code => $name): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $code; ?> - <?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="create_user" class="btn-primary">Create User</button>
                    <?php endif; ?>
                </form>
                
                <?php if ($edit_user): ?>
                    <!-- Update Profile Picture Form -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #6b46c1; margin-bottom: 1rem;">Update Profile Picture</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                            <div class="form-group">
                                <label>Current Profile Picture</label>
                                <div class="profile-preview-container">
                                    <div class="profile-info">
                                        <?php if ($edit_user['profile_picture'] && file_exists($edit_user['profile_picture'])): ?>
                                            <img src="<?php echo $edit_user['profile_picture']; ?>" class="profile-picture-preview" alt="Profile Picture" id="currentPicture">
                                        <?php else: ?>
                                            <div class="profile-picture-placeholder" id="editPlaceholder">
                                                <?php echo strtoupper(substr($edit_user['name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <img class="preview-image" id="editPreview" alt="Preview" style="display: none;">
                                    </div>
                                    <div class="profile-actions">
                                        <input type="file" name="new_profile_picture" class="file-input" accept="image/*" id="editProfilePictureInput" onchange="previewEditImage(this)" required>
                                        <button type="button" class="remove-preview" id="editRemoveBtn" onclick="removeEditPreview()" style="display: none;">Remove Preview</button>
                                        <small style="color: #6b7280;">JPG, JPEG, PNG, or GIF format (max 5MB)</small>
                                        <button type="submit" name="update_profile_picture" class="btn-primary">Update Picture</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Reset Password Form -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #6b46c1; margin-bottom: 1rem;">Reset Password</h3>
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            <button type="submit" name="reset_password" class="btn-warning">Reset Password</button>
                        </form>
                    </div>
                    
                    <!-- Modify Role Form -->
                    <div>
                        <h3 style="color: #6b46c1; margin-bottom: 1rem;">Modify Role</h3>
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                            <div class="form-group">
                                <label for="new_role">Current Role: <?php echo $edit_user['role']; ?> - <?php echo $roles[$edit_user['role']]; ?></label>
                                <select id="new_role" name="new_role" required>
                                    <option value="">Select New Role</option>
                                    <?php foreach ($roles as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo $edit_user['role'] === $code ? 'selected' : ''; ?>>
                                            <?php echo $code; ?> - <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="modify_role" class="btn-primary">Update Role</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>User Management</h2>
                
                <!-- Search and Filter Section -->
                <div class="filter-section">
                    <div class="filter-header">Search & Filter Users</div>
                    
                    <!-- Search Section -->
                    <div class="search-section">
                        <form method="GET" class="search-controls">
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Search by name or username..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                            
                            <select name="role_filter" class="filter-select">
                                <option value="all" <?php echo $role_filter === 'all' || $role_filter === '' ? 'selected' : ''; ?>>All Roles</option>
                                <?php foreach ($roles as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $role_filter === $code ? 'selected' : ''; ?>>
                                        <?php echo $code; ?> - <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" class="btn-search">Search</button>
                            
                            <?php if ($search_query || ($role_filter && $role_filter !== 'all')): ?>
                                <a href="dashboard.php" class="btn-clear">Clear All</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Filter Info -->
                    <div class="filter-info">
                        <?php 
                        $filter_text = [];
                        if ($search_query) {
                            $filter_text[] = "Search: \"" . htmlspecialchars($search_query) . "\"";
                        }
                        if ($role_filter && $role_filter !== 'all') {
                            $filter_text[] = "Role: " . $role_filter . " - " . $roles[$role_filter];
                        }
                        
                        if (!empty($filter_text)) {
                            echo "Showing " . $filtered_count . " user(s) with filters: " . implode(" | ", $filter_text);
                        } else {
                            echo "Showing all " . $total_users . " user(s)";
                        }
                        ?>
                    </div>
                </div>
                
                <?php if (empty($users)): ?>
                    <?php if ($search_query || ($role_filter && $role_filter !== 'all')): ?>
                        <p>No users found matching your search criteria.</p>
                        <p><a href="dashboard.php" class="btn-secondary">Clear filters</a> to see all users.</p>
                    <?php else: ?>
                        <p>No users created yet.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?php if ($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                                            <img src="<?php echo $user['profile_picture']; ?>" class="user-profile-pic" alt="Profile">
                                        <?php else: ?>
                                            <div class="user-profile-placeholder">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $name = htmlspecialchars($user['name']);
                                        if ($search_query) {
                                            $name = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<span class="search-highlight">$1</span>', $name);
                                        }
                                        echo $name;
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $username = htmlspecialchars($user['username']);
                                        if ($search_query) {
                                            $username = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<span class="search-highlight">$1</span>', $username);
                                        }
                                        echo $username;
                                        ?>
                                    </td>
                                    <td>
                                        <span class="role-badge">
                                            <?php echo $user['role']; ?> - <?php echo $roles[$user['role']]; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit_user=<?php echo $user['user_id']; ?><?php echo $role_filter ? '&role_filter=' . $role_filter : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>" class="btn-secondary">Edit</a>
                                            <a href="#" onclick="openPasswordModal('<?php echo $user['user_id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')" class="btn-warning">Reset Pass</a>
                                            <a href="?delete_user=<?php echo $user['user_id']; ?><?php echo $role_filter ? '&role_filter=' . $role_filter : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>" 
                                               class="btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Password Reset Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePasswordModal()">&times;</span>
            <h3 style="color: #6b46c1; margin-bottom: 1rem;">Quick Password Reset</h3>
            <form method="POST">
                <input type="hidden" id="modal_user_id" name="user_id">
                <div class="form-group">
                    <label>User: <span id="modal_username"></span></label>
                </div>
                <div class="form-group">
                    <label for="modal_new_password">New Password</label>
                    <input type="password" id="modal_new_password" name="new_password" required>
                </div>
                <button type="submit" name="reset_password" class="btn-warning">Reset Password</button>
                <button type="button" onclick="closePasswordModal()" style="margin-left: 1rem; background: #6b7280; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
            </form>
        </div>
    </div>
    
    <script>
        function openPasswordModal(userId, username) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_username').textContent = username;
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.getElementById('modal_new_password').value = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('passwordModal');
            if (event.target === modal) {
                closePasswordModal();
            }
        }
        
        function previewImage(input, previewId, placeholderId, removeBtnId) {
            const file = input.files[0];
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            const removeBtn = document.getElementById(removeBtnId);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                    removeBtn.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function removePreview(inputId, previewId, placeholderId, removeBtnId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            const removeBtn = document.getElementById(removeBtnId);
            
            input.value = '';
            preview.src = '';
            preview.style.display = 'none';
            placeholder.style.display = 'flex';
            removeBtn.style.display = 'none';
        }
        
        function previewEditImage(input) {
            const file = input.files[0];
            const preview = document.getElementById('editPreview');
            const currentPicture = document.getElementById('currentPicture');
            const placeholder = document.getElementById('editPlaceholder');
            const removeBtn = document.getElementById('editRemoveBtn');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    removeBtn.style.display = 'block';
                    
                    // Hide current picture or placeholder
                    if (currentPicture) {
                        currentPicture.style.display = 'none';
                    }
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            }
        }
        
        function removeEditPreview() {
            const input = document.getElementById('editProfilePictureInput');
            const preview = document.getElementById('editPreview');
            const currentPicture = document.getElementById('currentPicture');
            const placeholder = document.getElementById('editPlaceholder');
            const removeBtn = document.getElementById('editRemoveBtn');
            
            input.value = '';
            preview.src = '';
            preview.style.display = 'none';
            removeBtn.style.display = 'none';
            
            // Show original picture or placeholder
            if (currentPicture) {
                currentPicture.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'flex';
            }
        }
    </script>
</body>
</html>
