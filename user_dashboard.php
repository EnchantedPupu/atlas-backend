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
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/forms-display.css">
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
                
                <div class="nav-separator"></div>
                
                <a href="#" class="nav-item" onclick="showComingSoon('My Profile', event)">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">My Profile</span>
                </a>
                
                <a href="#" class="nav-item" onclick="showComingSoon('Settings', event)">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Settings</span>
                </a>
                
                <div class="nav-separator"></div>
                
                <a href="#" class="nav-item" onclick="showComingSoon('Help & Support', event)">
                    <span class="nav-icon">❓</span>
                    <span class="nav-text">Help & Support</span>
                </a>
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
