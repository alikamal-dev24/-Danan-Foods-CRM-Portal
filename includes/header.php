<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Gate: Redirect users back to index.php if they have no active login session
if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

$current_role = $_SESSION['role'] ?? 'Distributor';
$username     = $_SESSION['username'] ?? 'User';

// Helper flag for Admin / Manager checks
$is_admin_or_manager = ($current_role === 'Admin' || $current_role === 'Manager');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danan Foods CRM</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="portal-overlay" id="portalOverlay" onclick="toggleMobileMenu()"></div>

<header class="portal-topbar">
    <button class="menu-trigger-btn" onclick="toggleMobileMenu()" aria-label="Toggle Navigation Menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="4" y1="12" x2="20" y2="12"></line>
            <line x1="4" y1="6" x2="20" y2="6"></line>
            <line x1="4" y1="18" x2="20" y2="18"></line>
        </svg>
    </button>
    <div class="topbar-brand">🍇 Danan Foods</div>
    <div style="width: 24px;" class="desktop-hidden"></div>
</header>

<aside class="sidebar" id="appSidebar">
    <div class="sidebar__header">
        <div class="sidebar__brand">🍇 Danan Foods</div>
        <button class="menu-close-btn" onclick="toggleMobileMenu()">✕</button>
    </div>
    
    <nav class="sidebar__nav">
        <a href="dashboard.php" class="sidebar__link">
            <span class="sidebar__icon">📊</span> 
            <span class="sidebar__text">Main Dashboard</span>
        </a>
        
        <?php if ($current_role === 'Sales'): ?>
            <a href="shipment-requests.php" class="sidebar__link">
                <span class="sidebar__icon">📦</span> 
                <span class="sidebar__text">Order New Shipment</span>
            </a>
        <?php endif; ?>

        <?php if ($is_admin_or_manager): ?>
            <a href="shipment-requests.php" class="sidebar__link">
                <span class="sidebar__icon">📥</span> 
                <span class="sidebar__text">Shipment Approvals Center</span>
            </a>
        <?php endif; ?>

        <a href="shipments.php" class="sidebar__link">
            <span class="sidebar__icon">🚚</span> 
            <span class="sidebar__text">Shipments Tracker</span>
        </a>
        
        <?php if ($is_admin_or_manager): ?>
            <a href="payments.php" class="sidebar__link">
                <span class="sidebar__icon">💵</span> 
                <span class="sidebar__text">Payments</span>
            </a>
        <?php endif; ?>

        <!-- Interactions Link Visible to Everyone (Filtered per role in interactions.php) -->
        <a href="interactions.php" class="sidebar__link">
            <span class="sidebar__icon">💬</span> 
            <span class="sidebar__text">Interaction Logs</span>
        </a>

        <!-- Operations Section Hidden from Customer/Sales Roles -->
        <?php if ($current_role === 'Admin' || $current_role === 'Manager' || $current_role === 'Modifier'): ?>
            <div class="sidebar__section-title" style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; padding: 1.25rem 1rem 0.25rem 1rem; font-weight: 700; letter-spacing: 0.05em;">
                Operations
            </div>
            
            <a href="inventory.php" class="sidebar__link">
                <span class="sidebar__icon">🏪</span> 
                <span class="sidebar__text">Stock Warehouse</span>
            </a>
            <a href="leads.php" class="sidebar__link">
                <span class="sidebar__icon">🎯</span> 
                <span class="sidebar__text">Sales Pipeline</span>
            </a>
        <?php endif; ?>

        <!-- Control Panel for Admins & Managers -->
        <?php if ($is_admin_or_manager): ?>
            <div class="sidebar__section-title" style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; padding: 1.25rem 1rem 0.25rem 1rem; font-weight: 700; letter-spacing: 0.05em;">
                Control Panel
            </div>

            <a href="customers.php" class="sidebar__link">
                <span class="sidebar__icon">👥</span> 
                <span class="sidebar__text">Customer Profiles</span>
            </a>
            <a href="roles.php" class="sidebar__link">
                <span class="sidebar__icon">🔐</span> 
                <span class="sidebar__text">Access Permissions</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar__user-footer" style="padding: 1.25rem 1rem; border-top: 1px solid rgba(255,255,255,0.08); margin-top: auto;">
        <a href="profile.php" style="color: #ffffff; text-decoration: none; display: block; margin-bottom: 0.5rem; font-weight: 600; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='#fff'">
            👤 <?php echo htmlspecialchars($username); ?> 
            <span style="font-size: 0.75rem; color: #94a3b8; display: block; font-weight: 400; margin-top: 2px;">
                (<?php echo htmlspecialchars($current_role); ?>)
            </span>
        </a>
        
        <a href="logout.php" class="logout-link" style="color: #f87171; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; margin-top: 0.25rem; transition: color 0.2s;" onmouseover="this.style.color='#f87171'" onmouseout="this.style.color='#f87171'">
            🚪 Exit Portal
        </a>
    </div>
</aside>

<main class="main-content">