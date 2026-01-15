<?php
session_start();
require_once 'config/role_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check if AS role should use app
if (RoleConfig::isAppOnlyRole($_SESSION['user_role'])) {
    $appOnlyMessage = RoleConfig::getAppOnlyMessage($_SESSION['user_role']);
}

// Get role-specific menus
$roleMenus = RoleConfig::getMenusForRole($_SESSION['user_role']);

// User roles mapping
$roles = [
    'VO' => 'Valuation Officer',
    'OIC' => 'Officer In Charge',
    'SS' => 'Staff Surveyors',
    'FI' => 'Field Inspector',
    'AS' => 'Assistant Surveyor',
    'SD' => 'Senior Draughtman',
    'PP' => 'Pelukis Pelan'
];

// Additional menu items for better navigation
$additionalMenus = [
    'job_list' => [
        'title' => 'Job List',
        'file' => 'job_list.php',
        'icon' => '📋',
        'description' => 'View and manage all survey jobs'
    ]
];

// Merge additional menus with role-specific menus
foreach ($additionalMenus as $key => $menu) {
    if (!isset($roleMenus[$key])) {
        $roleMenus[$key] = $menu;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ATLAS System</title>
    <link rel="icon" type="image/png" href="assets/images/atlas-logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/atlas-logo.png">
    <style>
        /* Modern SaaS Design System */
        :root {
            --color-primary: #2563EB;
            --color-primary-hover: #1D4ED8;
            --color-primary-light: #DBEAFE;
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
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: var(--color-bg-primary);
            color: var(--color-text-primary);
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .main-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
            border-right: 1px solid var(--color-border);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar.collapsed {
            width: 0;
            border-right: none;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 16px;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-bg-secondary);
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: var(--shadow-sm);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-sidebar {
            width: 32px;
            height: 32px;
            transition: transform 0.3s ease;
        }

        .sidebar-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--color-text-primary);
            letter-spacing: -0.5px;
        }

        .toggle-btn {
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .toggle-btn:hover {
            background: var(--color-bg-primary);
            border-color: var(--color-border-hover);
            color: var(--color-text-primary);
            transform: scale(1.05);
            box-shadow: var(--shadow-sm);
        }

        .nav-menu {
            padding: 16px 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            color: var(--color-text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            border-radius: var(--radius-md);
            margin-bottom: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            position: relative;
        }

        .nav-item:hover {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(59, 130, 246, 0.08) 100%);
            color: var(--color-text-primary);
            transform: translateX(4px);
            box-shadow: var(--shadow-xs);
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--color-primary) 0%, #3B82F6 100%);
            color: white;
            font-weight: 600;
            box-shadow: var(--shadow-md);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 70%;
            background: white;
            border-radius: 0 4px 4px 0;
            opacity: 0.8;
        }

        .nav-item.app-only-notice {
            background: var(--color-warning-bg);
            color: var(--color-warning);
            cursor: default;
        }

        .nav-icon {
            width: 20px;
            margin-right: 12px;
            font-size: 18px;
            text-align: center;
        }

        .nav-text {
            flex: 1;
        }

        .nav-separator {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, var(--color-border) 50%, transparent 100%);
            margin: 12px 0;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            margin-left: 260px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .content-area.expanded {
            margin-left: 0;
        }

        .hamburger-menu {
            display: none;
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
            width: 32px;
            height: 32px;
        }

        .hamburger-menu:hover {
            background: var(--color-bg-primary);
            border-color: var(--color-border-hover);
            color: var(--color-text-primary);
        }

        .content-area.expanded .hamburger-menu {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hamburger-icon {
            width: 18px;
            height: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .hamburger-icon span {
            display: block;
            height: 2px;
            width: 100%;
            background: currentColor;
            border-radius: 1px;
        }

        /* Navbar */
        .navbar {
            background: var(--color-bg-secondary);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid var(--color-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-navbar {
            width: 36px;
            height: 36px;
        }

        .navbar h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--color-text-primary);
            letter-spacing: -0.5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: var(--radius-lg);
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--color-border);
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--color-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            color: white;
        }

        .user-profile > div {
            display: flex;
            flex-direction: column;
        }

        .user-profile > div > div:first-child {
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text-primary);
        }

        .user-profile > div > div:last-child {
            font-size: 12px;
            color: var(--color-text-secondary);
        }

        .btn-logout {
            padding: 10px 20px;
            background: var(--color-error);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-logout:hover {
            background: #DC2626;
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        /* Container */
        .container {
            padding: 32px 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Breadcrumbs */
        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--color-bg-secondary);
            border-bottom: 1px solid var(--color-border);
            font-size: 14px;
            color: var(--color-text-secondary);
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .breadcrumb-item a {
            color: var(--color-primary);
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .breadcrumb-item a:hover {
            color: var(--color-primary-hover);
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: var(--color-text-primary);
            font-weight: 600;
        }

        .breadcrumb-separator {
            color: var(--color-text-tertiary);
            font-size: 12px;
        }

        .breadcrumb-icon {
            font-size: 16px;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, var(--color-primary) 0%, #1D4ED8 100%);
            padding: 32px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            margin-bottom: 32px;
            color: white;
        }

        .welcome-card h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .welcome-card p {
            font-size: 16px;
            opacity: 0.95;
            margin-bottom: 16px;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: var(--color-bg-secondary);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 28px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
            border-color: var(--color-primary-light);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), #3B82F6);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .new-task-counter {
            position: absolute;
            top: 16px;
            right: 16px;
            background: var(--color-error);
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            box-shadow: var(--shadow-md);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .feature-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--color-text-primary);
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }

        .feature-card p {
            font-size: 14px;
            color: var(--color-text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .btn-feature {
            width: 100%;
            padding: 12px 20px;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-feature:hover {
            background: var(--color-primary-hover);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        /* App Only Card */
        .app-only-card {
            background: var(--color-bg-secondary);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 48px;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: var(--shadow-lg);
        }

        .app-only-card h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--color-text-primary);
            margin-bottom: 16px;
        }

        .app-only-card p {
            font-size: 16px;
            color: var(--color-text-secondary);
            margin-bottom: 32px;
        }

        .app-download-links {
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        /* Mobile Overlay */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .overlay.active {
            display: block;
            opacity: 1;
        }

        .mobile-menu-btn {
            display: none;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
                width: 260px;
            }

            .content-area {
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: block;
                background: var(--color-bg-primary);
                border: 1px solid var(--color-border);
                color: var(--color-text-secondary);
                cursor: pointer;
                padding: 8px 12px;
                border-radius: var(--radius-md);
                font-size: 20px;
            }

            .navbar {
                padding: 12px 16px;
            }

            .container {
                padding: 20px 16px;
            }

            .welcome-card {
                padding: 24px;
            }

            .welcome-card h2 {
                font-size: 22px;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .user-profile > div > div:first-child {
                font-size: 13px;
            }

            .user-profile > div > div:last-child {
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .user-profile {
                padding: 6px 10px;
            }

            .user-avatar,
            .user-avatar-placeholder {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }

            .btn-logout {
                padding: 8px 14px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="main-layout">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="assets/images/atlas-logo.png" alt="ATLAS" class="logo-sidebar">
                    <span class="sidebar-title">ATLAS</span>
                </div>
                <button class="toggle-btn" onclick="toggleSidebar()">
                    <span id="toggle-icon">◄</span>
                </button>
            </div>
            
            <div class="nav-menu">
                <a href="/user_dashboard.php" class="nav-item active" onclick="showDashboard(event)">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                
                <div class="nav-separator"></div>
                
                <?php if (isset($appOnlyMessage)): ?>
                    <div class="nav-item app-only-notice">
                        <span class="nav-icon">📱</span>
                        <span class="nav-text">App Only Access</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($roleMenus as $key => $menu): ?>
                        <a href="#" class="nav-item" onclick="loadPage('<?php echo $menu['file']; ?>', '<?php echo $menu['title']; ?>', event)">
                            <span class="nav-icon"><?php echo $menu['icon']; ?></span>
                            <span class="nav-text"><?php echo $menu['title']; ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </nav>
        
        <!-- Overlay for mobile -->
        <div class="overlay" id="overlay" onclick="closeMobileSidebar()"></div>
        
        <!-- Main Content Area -->
        <div class="content-area" id="contentArea">
            <nav class="navbar">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="hamburger-menu" onclick="openSidebar()">
                        <div class="hamburger-icon">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </button>
                    <button class="mobile-menu-btn" onclick="openMobileSidebar()">☰</button>
                    <div class="navbar-brand">
                        <img src="assets/images/atlas-logo.png" alt="ATLAS" class="logo-navbar">
                        <h1>ATLAS</h1>
                    </div>
                </div>
                <div class="user-info">
                    <div class="user-profile">
                        <?php if ($_SESSION['profile_picture'] && file_exists($_SESSION['profile_picture'])): ?>
                            <img src="<?php echo $_SESSION['profile_picture']; ?>" class="user-avatar" alt="Profile">
                        <?php else: ?>
                            <div class="user-avatar-placeholder">
                                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                            <div style="font-size: 0.8rem; opacity: 0.8;">
                                <?php echo $_SESSION['user_role']; ?> - <?php echo $roles[$_SESSION['user_role']]; ?>
                            </div>
                        </div>
                    </div>
                    <a href="?logout=1" class="btn-logout">Logout</a>
                </div>
            </nav>
            
            <!-- Breadcrumbs -->
            <div class="breadcrumbs" id="breadcrumbs">
                <div class="breadcrumb-item">
                    <span class="breadcrumb-icon">🏠</span>
                    <span class="active">Dashboard</span>
                </div>
            </div>
            
            <div class="container">
                <?php if (isset($appOnlyMessage)): ?>
                    <div class="app-only-card">
                        <h2>Mobile App Access Required</h2>
                        <p><?php echo $appOnlyMessage; ?></p>
                        <div class="app-download-links">
                            <a href="#" class="btn-feature">Download Android App</a>
                            <a href="#" class="btn-feature">Download iOS App</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div id="main-content">
                        <div class="welcome-card">
                            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                            <p>You are logged in as a <strong><?php echo $roles[$_SESSION['user_role']]; ?></strong></p>
                            <span class="role-badge"><?php echo $_SESSION['user_role']; ?></span>
                        </div>
                        
                        <div class="features-grid">
                            <?php foreach ($roleMenus as $key => $menu): ?>
                                <div class="feature-card" id="feature-card-<?php echo $key; ?>">
                                    <?php if ($key === 'job_list'): ?>
                                        <div class="new-task-counter" id="newTaskCounter" style="display: none;">
                                            <span id="newTaskCount">0</span>
                                        </div>
                                    <?php endif; ?>
                                    <h3><?php echo $menu['title']; ?></h3>
                                    <p>Access your <?php echo strtolower($menu['title']); ?> functionality.</p>
                                    <button class="btn-feature" onclick="loadPage('<?php echo $menu['file']; ?>', '<?php echo $menu['title']; ?>')">
                                        <?php echo $menu['icon']; ?> Open <?php echo $menu['title']; ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <script>
        // Pass PHP data to JavaScript
        window.roleMenus = <?php echo json_encode($roleMenus); ?>;
        window.userName = <?php echo json_encode(htmlspecialchars($_SESSION['user_name'])); ?>;
        window.userRole = <?php echo json_encode($_SESSION['user_role']); ?>;
        window.userRoleLabel = <?php echo json_encode($roles[$_SESSION['user_role']]); ?>;
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
