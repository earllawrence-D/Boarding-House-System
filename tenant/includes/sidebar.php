<?php
// Check if user is logged in and is a tenant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'tenant') {
    header("Location: ../../index.php");
    exit();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="bi bi-house-door"></i>
            <span class="sidebar-title">Tenant Portal</span>
        </div>
        <button type="button" id="sidebarToggle" class="sidebar-toggle">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <ul class="sidebar-nav">
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span class="link-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>" href="profile.php">
                <i class="bi bi-person"></i>
                <span class="link-text">My Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'payments.php' ? 'active' : '' ?>" href="payments.php">
                <i class="bi bi-cash-stack"></i>
                <span class="link-text">Payments</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'maintenance.php' ? 'active' : '' ?>" href="maintenance.php">
                <i class="bi bi-tools"></i>
                <span class="link-text">Maintenance Requests</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'community.php' ? 'active' : '' ?>" href="community.php">
                <i class="bi bi-people"></i>
                <span class="link-text">Community</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'documents.php' ? 'active' : '' ?>" href="documents.php">
                <i class="bi bi-file-earmark"></i>
                <span class="link-text">My Documents</span>
            </a>
        </li>
    </ul>
</nav>