<?php
// Check if user is logged in and is a landlord
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'landlord') {
    header("Location: ../../index.php");
    exit();
}

// Fetch the pending requests for the landlord
$pending_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_rooms tr 
                         JOIN rooms r ON tr.room_id = r.room_id
                         JOIN boarding_houses bh ON r.house_id = bh.house_id
                         WHERE bh.landlord_id = ? AND tr.status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching pending requests: " . $e->getMessage());
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="bi bi-house-door"></i>
            <span class="sidebar-title">My Boarding House</span>
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
            <a class="nav-link <?= $current_page == 'tenants.php' ? 'active' : '' ?>" href="tenants.php">
                <i class="bi bi-people"></i>
                <span class="link-text">Tenant Tracking</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'payments.php' ? 'active' : '' ?>" href="payments.php">
                <i class="bi bi-cash-stack"></i>
                <span class="link-text">Payment Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'maintenance.php' ? 'active' : '' ?>" href="maintenance.php">
                <i class="bi bi-tools"></i>
                <span class="link-text">Maintenance</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                <i class="bi bi-graph-up"></i>
                <span class="link-text">Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'rooms.php' ? 'active' : '' ?>" href="rooms.php">
                <i class="bi bi-house-door"></i>
                <span class="link-text">Room Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'pending_requests.php' ? 'active' : '' ?>" href="pending_requests.php">
                <i class="bi bi-clock-history"></i>
                <span class="link-text">Pending Requests</span>
                <?php if ($pending_count > 0): ?>
                    <span class="badge bg-danger badge-pill"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
    </ul>
</nav>