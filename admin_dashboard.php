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

// Pagination settings
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Count total jobs (groups) for pagination
$total_jobs_sql = "SELECT COUNT(DISTINCT sj.surveyjob_no) as total_jobs
                   FROM forms f 
                   LEFT JOIN surveyjob sj ON f.surveyjob_id = sj.survey_job_id 
                   WHERE f.form_data LIKE '%proof_signature%'";
try {
    $count_stmt = $db->prepare($total_jobs_sql);
    $count_stmt->execute();
    $total_jobs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total_jobs'];
    $total_pages = ceil($total_jobs / $items_per_page);
} catch (PDOException $e) {
    $total_jobs = 0;
    $total_pages = 0;
}

// Fetch paginated jobs
$signaturesByJob = [];
try {
    // 1. Get Job IDs for current page
    $jobs_sql = "SELECT DISTINCT sj.surveyjob_no 
                 FROM forms f 
                 LEFT JOIN surveyjob sj ON f.surveyjob_id = sj.survey_job_id 
                 WHERE f.form_data LIKE '%proof_signature%'
                 ORDER BY sj.surveyjob_no ASC
                 LIMIT :limit OFFSET :offset";
    
    $jobs_stmt = $db->prepare($jobs_sql);
    $jobs_stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $jobs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $jobs_stmt->execute();
    $paginated_jobs = $jobs_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($paginated_jobs)) {
        // 2. Fetch full form data for these specific jobs
        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($paginated_jobs), '?'));
        
        $sig_sql = "SELECT f.form_id, f.form_type, f.form_data, sj.surveyjob_no 
                    FROM forms f 
                    LEFT JOIN surveyjob sj ON f.surveyjob_id = sj.survey_job_id 
                    WHERE sj.surveyjob_no IN ($placeholders)
                    ORDER BY sj.surveyjob_no, f.created_at DESC";
        
        $sig_stmt = $db->prepare($sig_sql);
        $sig_stmt->execute($paginated_jobs);
        $all_forms = $sig_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_forms as $form) {
            $data = json_decode($form['form_data'], true);
            if (!$data) continue;

            // Determine Job Number
            $jobNo = $form['surveyjob_no'] ?? 'Unknown Job';
            
            // Determine Lot Number
            $lotNo = 'Unknown Lot';
            if (isset($data['tanah']['lot_no']) && !empty($data['tanah']['lot_no'])) {
                 $lotNo = trim($data['tanah']['lot_no']);
            } else if (isset($data['lot_no']) && !empty($data['lot_no'])) {
                 $lotNo = trim($data['lot_no']);
            } else if (isset($data['lot']) && !empty($data['lot'])) {
                 $lotNo = trim($data['lot']);
            }

            // Extract specific Form ID from JSON if available, otherwise fallback
            $specificFormId = isset($data['form_id']) ? $data['form_id'] : $form['form_type'];

            // Check for proof_signature block
            if (isset($data['proof_signature']) && is_array($data['proof_signature'])) {
                 if (!isset($signaturesByJob[$jobNo])) {
                     $signaturesByJob[$jobNo] = [];
                 }
                 if (!isset($signaturesByJob[$jobNo][$lotNo])) {
                     $signaturesByJob[$jobNo][$lotNo] = [];
                 }
                 
                 $signaturesByJob[$jobNo][$lotNo][] = [
                     'signatures' => $data['proof_signature'],
                     'form_type' => $form['form_type'],
                     'display_id' => $specificFormId, // Store specific ID for display
                     'db_id' => $form['form_id']
                 ];
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching signatures: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ATLAS System</title>
    <link rel="icon" type="image/png" href="assets/images/atlas-logo.png">
    <style>
        /* Modern SaaS Design System - Admin Theme (Purple) */
        :root {
            --color-primary: #8b5cf6;
            --color-primary-hover: #7c3aed;
            --color-primary-light: #ddd6fe;
            --color-secondary: #0F172A;
            --color-bg-primary: #F8FAFC;
            --color-bg-secondary: #FFFFFF;
            --color-text-primary: #0F172A;
            --color-text-secondary: #64748B;
            --color-text-tertiary: #94A3B8;
            --color-border: #E2E8F0;
            --color-border-hover: #CBD5E1;
            --color-success: #10B981;
            --color-success-bg: #D1FAE5;
            --color-warning: #F59E0B;
            --color-warning-bg: #FEF3C7;
            --color-error: #EF4444;
            --color-error-bg: #FEE2E2;
            --color-info: #3B82F6;
            --color-info-bg: #DBEAFE;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --font-sans: 'Segoe UI', 'Inter', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--color-bg-primary); color: var(--color-text-primary); line-height: 1.6; min-height: 100vh; }
        
        /* Layout */
        .main-layout { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 260px;
            background: #FFFFFF;
            border-right: 1px solid var(--color-border);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--color-bg-secondary);
        }

        .sidebar-logo {
            font-size: 24px;
        }
        
        .sidebar-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--color-primary);
        }
        
        .nav-menu { padding: 16px; flex: 1; }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            color: var(--color-text-secondary);
            text-decoration: none;
            border-radius: var(--radius-md);
            margin-bottom: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .nav-item:hover, .nav-item.active {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .nav-icon { width: 20px; margin-right: 12px; text-align: center; }
        
        .content-area {
            flex: 1;
            margin-left: 260px;
            background: var(--color-bg-primary);
        }

        /* Navbar */
        .navbar {
            background: var(--color-bg-secondary);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--color-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar h1 { font-size: 20px; font-weight: 600; }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-logout {
            background: var(--color-error);
            color: white;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        
        .btn-logout:hover { background: #dc2626; }

        /* Container & Cards */
        .container { padding: 32px; max-width: 1400px; margin: 0 auto; }
        
        .card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-sm);
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--color-primary);
        }
        
        .stat-number { font-size: 2.5rem; font-weight: 700; color: var(--color-primary); margin-bottom: 4px; }
        .stat-label { color: var(--color-text-secondary); font-size: 0.95rem; }

        /* Forms & Inputs */
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--color-text-primary); }
        
        input, select {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        
        input:focus, select:focus { outline: none; border-color: var(--color-primary); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .btn-primary { background: var(--color-primary); color: white; }
        .btn-primary:hover { background: var(--color-primary-hover); }
        
        .btn-secondary { background: #64748B; color: white; }
        .btn-secondary:hover { background: #475569; }

        .btn-danger { background: var(--color-error); color: white; text-decoration: none; padding: 6px 12px; font-size: 0.85rem; border-radius: 4px; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; padding: 12px; background: var(--color-bg-primary); color: var(--color-text-secondary); font-weight: 600; border-bottom: 1px solid var(--color-border); }
        td { padding: 12px; border-bottom: 1px solid var(--color-border); color: var(--color-text-primary); }
        
        /* Collapsible Signatures */
        .accordion {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            overflow: hidden;
        }
        
        .accordion-header {
            background: var(--color-bg-primary);
            padding: 16px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--color-text-primary);
            transition: background 0.2s;
        }
        
        .accordion-header:hover { background: var(--color-primary-light); color: var(--color-primary); }
        
        .accordion-content {
            display: none;
            padding: 20px;
            border-top: 1px solid var(--color-border);
            animation: slideDown 0.3s ease;
        }
        
        .accordion.active > .accordion-content { display: block; }
        
        .sub-accordion {
            margin-left: 20px;
            margin-top: 10px;
            border-left: 2px solid var(--color-border);
        }
        
        .sub-accordion-header {
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 500;
            color: var(--color-text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sub-accordion-header:hover { color: var(--color-primary); }
        
        .sub-accordion-content { display: none; padding: 10px 16px; }
        .sub-accordion.active > .sub-accordion-content { display: block; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .helper-text { font-size: 0.85rem; color: var(--color-text-secondary); margin-top: 4px; }
        
        .section { display: none; }
        .section.active { display: block; }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.mobile-open { transform: translateX(0); }
            .content-area { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="main-layout">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">🔒</div>
                <span class="sidebar-title">ATLAS Admin</span>
            </div>
            <div class="nav-menu">
                <a onclick="showSection('users')" class="nav-item active" id="nav-users">
                    <span class="nav-icon">👥</span> User Management
                </a>
                <a onclick="showSection('signatures')" class="nav-item" id="nav-signatures">
                    <span class="nav-icon">✍️</span> Proof Signatures
                </a>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <nav class="navbar">
                <h1>Admin Dashboard</h1>
                <div class="user-profile">
                    <span>Welcome, Admin</span>
                    <a href="?logout=1" class="btn-logout">Logout</a>
                </div>
            </nav>

            <div class="container">
                <!-- Notifications -->
                <?php if (isset($success)): ?>
                    <div style="background: var(--color-success-bg); color: var(--color-success); padding: 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #bbf7d0;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div style="background: var(--color-error-bg); color: var(--color-error); padding: 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #fecaca;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- User Management -->
                <div id="users" class="section active">
                    <div class="card">
                        <h2 style="margin-bottom: 20px; color: var(--color-primary);"><?php echo $edit_user ? 'Edit User' : 'Create New User'; ?></h2>
                        <?php if ($edit_user): ?>
                            <div style="margin-bottom: 20px;">
                                <a href="admin_dashboard.php" class="btn-secondary" style="text-decoration: none; padding: 6px 12px; font-size: 0.9rem;">← Cancel Edit</a>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                        <?php if (!$edit_user): ?>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="name" required placeholder="John Doe">
                                </div>
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" name="username" required placeholder="johndoe">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label>Password</label>
                                    <input type="password" name="password" required>
                                </div>
                                <div class="form-group">
                                    <label>Role</label>
                                    <select name="role" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($roles as $code => $name): ?>
                                            <option value="<?php echo $code; ?>"><?php echo $code; ?> - <?php echo $name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Profile Picture (Optional)</label>
                                <input type="file" name="profile_picture" accept="image/*">
                            </div>
                            <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                        <?php else: ?>
                            <!-- Edit Mode simplified for layout demonstration -->
                            <p>Editing users involves re-uploading pictures or resetting passwords. Use the specific forms below.</p>
                        <?php endif; ?>
                        </form>
                    </div>

                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="color: var(--color-text-primary);">System Users</h2>
                            <form method="GET" style="display: flex; gap: 10px;">
                                <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 200px;">
                                <button type="submit" class="btn btn-primary">Search</button>
                            </form>
                        </div>
                        
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <?php if ($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                                                    <img src="<?php echo $user['profile_picture']; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #64748b;">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <div style="font-size: 0.85rem; color: var(--color-text-secondary);">@<?php echo htmlspecialchars($user['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="background: var(--color-info-bg); color: var(--color-info); padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                                <?php echo $user['role']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <a href="?edit_user=<?php echo $user['user_id']; ?>" class="btn-secondary" style="padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem;">Edit</a>
                                                <a href="?delete_user=<?php echo $user['user_id']; ?>" class="btn-danger" onclick="return confirm('Delete user?');">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Proof Signatures -->
                <div id="signatures" class="section">
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                            <h2 style="color: var(--color-primary); margin: 0;">Proof Signatures Viewer</h2>
                            <div style="font-size: 0.9rem; color: var(--color-text-secondary);">
                                Showing <?php echo count($signaturesByJob); ?> Jobs (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)
                            </div>
                        </div>

                        <?php if (empty($signaturesByJob)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--color-text-secondary);">
                                <div style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;">🗂️</div>
                                <p>No proof signatures found in the system.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($signaturesByJob as $jobNo => $lots): ?>
                                <div class="accordion">
                                    <div class="accordion-header" onclick="toggleAccordion(this)">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-briefcase"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                            <span>Job No: <?php echo htmlspecialchars($jobNo); ?></span>
                                        </div>
                                        <span class="chevron">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                        </span>
                                    </div>
                                    <div class="accordion-content">
                                        <?php foreach ($lots as $lotNo => $entries): ?>
                                            <div class="sub-accordion">
                                                <div class="sub-accordion-header" onclick="toggleSubAccordion(this)">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                        <span>Lot: <?php echo htmlspecialchars($lotNo); ?></span>
                                                    </div>
                                                </div>
                                                <div class="sub-accordion-content">
                                                    <?php foreach ($entries as $entry): ?>
                                                        <a href="pages/view_form.php?form_id=<?php echo $entry['db_id']; ?>" target="_blank" style="text-decoration: none; display: block; color: inherit;">
                                                            <div style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'">
                                                                <div style="font-size: 0.9rem; font-weight: 600; color: var(--color-primary); margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; display: flex; justify-content: space-between;">
                                                                    <span>Form ID: <?php echo htmlspecialchars($entry['display_id']); ?></span>
                                                                    <span style="font-weight: 400; color: var(--color-text-tertiary); font-size: 0.8rem;"><?php echo htmlspecialchars($entry['form_type']); ?></span>
                                                                </div>
                                                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
                                                                    <?php foreach ($entry['signatures'] as $key => $sigData): ?>
                                                                        <?php if (!empty($sigData)): ?>
                                                                            <?php 
                                                                                $imgSrc = (strpos($sigData, 'data:image') === 0) ? $sigData : "data:image/jpeg;base64," . $sigData;
                                                                            ?>
                                                                            <div style="background: #f8fafc; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
                                                                                <div style="height: 120px; display: flex; align-items: center; justify-content: center; background: white; border-bottom: 1px solid #e2e8f0;">
                                                                                    <img src="<?php echo $imgSrc; ?>" style="max-height: 100px; max-width: 100%; object-fit: contain;">
                                                                                </div>
                                                                                <div style="padding: 10px; font-size: 0.8rem; color: var(--color-text-secondary); text-align: center; font-weight: 500; background: #f1f5f9;">
                                                                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Pagination Controls -->
                            <?php if ($total_pages > 1): ?>
                                <div style="display: flex; justify-content: center; gap: 10px; margin-top: 30px;">
                                    <?php if ($current_page > 1): ?>
                                        <a href="?page=<?php echo $current_page - 1; ?>&section=signatures" class="btn btn-secondary">Previous</a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed;">Previous</button>
                                    <?php endif; ?>

                                    <?php if ($current_page < $total_pages): ?>
                                        <a href="?page=<?php echo $current_page + 1; ?>&section=signatures" class="btn btn-primary">Next</a>
                                    <?php else: ?>
                                        <button class="btn btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">Next</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            
            // Show target section
            document.getElementById(sectionId).classList.add('active');
            document.getElementById('nav-' + sectionId).classList.add('active');
        }

        // Retain view after form submission or pagination
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit_user') || urlParams.has('search')) {
            showSection('users');
        } else if (urlParams.has('page') || urlParams.get('section') === 'signatures') {
            showSection('signatures');
        }

        // Accordion functionality
        function toggleAccordion(header) {
            const parent = header.parentElement;
            parent.classList.toggle('active');
            // chevron rotation handled by CSS ideally, or we swap icon if needed
            // simple swap
            const chevron = header.querySelector('.chevron svg');
            if (parent.classList.contains('active')) {
                chevron.style.transform = 'rotate(180deg)';
                chevron.style.transition = 'transform 0.3s ease';
            } else {
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        function toggleSubAccordion(header) {
            const parent = header.parentElement;
            parent.classList.toggle('active');
        }
        

    </script>
</body>
</html>
