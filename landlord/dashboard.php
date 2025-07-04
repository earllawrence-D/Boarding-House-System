<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'landlord') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];    
$user = get_user_info($pdo, $user_id);

// Check if landlord is verified
$stmt = $pdo->prepare("SELECT is_verified FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$verification = $stmt->fetch();
$is_verified = $verification['is_verified'];

// Redirect to verification if not verified
if (!$is_verified) {
    if (!isset($_SESSION['verification_redirect_time'])) {
        $_SESSION['verification_redirect_time'] = time() + 10;
        $_SESSION['needs_verification'] = true;
    }
    
    if (time() >= $_SESSION['verification_redirect_time']) {
        unset($_SESSION['needs_verification']);
        unset($_SESSION['verification_redirect_time']);
        header("Location: ../verify_landlord.php");
        exit();
    }
}

$houses = get_landlord_houses($pdo, $user_id);

// Get stats
$total_houses = count($houses);
$total_tenants = 0;
$total_income = 0;
$pending_payments = 0;

foreach ($houses as $house) {
    // Count tenants
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_rooms tr
                          JOIN rooms r ON tr.room_id = r.room_id
                          WHERE r.house_id = ? AND tr.status = 'active'");
    $stmt->execute([$house['house_id']]);
    $total_tenants += $stmt->fetchColumn();
    
    // Sum income
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments p
                          JOIN rooms r ON p.room_id = r.room_id
                          WHERE r.house_id = ? AND p.status = 'paid'");
    $stmt->execute([$house['house_id']]);
    $total_income += $stmt->fetchColumn();
    
    // Count pending payments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p
                          JOIN rooms r ON p.room_id = r.room_id
                          WHERE r.house_id = ? AND p.status IN ('pending', 'overdue')");
    $stmt->execute([$house['house_id']]);
    $pending_payments += $stmt->fetchColumn();
}

// Get recent activity
$stmt = $pdo->prepare("SELECT mr.*, r.room_number, u.first_name, u.last_name 
                      FROM maintenance_requests mr
                      JOIN rooms r ON mr.room_id = r.room_id
                      JOIN users u ON mr.reported_by = u.user_id
                      JOIN boarding_houses bh ON r.house_id = bh.house_id
                      WHERE bh.landlord_id = ?
                      ORDER BY mr.created_at DESC
                      LIMIT 5");
$stmt->execute([$user_id]);
$recent_activity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <?php if (!$is_verified && isset($_SESSION['needs_verification']) && $_SESSION['needs_verification']): ?>
    <meta http-equiv="refresh" content="10;url=../verify_landlord.php">
    <?php endif; ?>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
            
            <?php if (!$is_verified && isset($_SESSION['needs_verification']) && $_SESSION['needs_verification']): ?>
            <div class="container-fluid mt-3">
                <div class="alert alert-warning alert-dismissible fade show">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="bi bi-shield-exclamation"></i> Verification Required</h5>
                            <p class="mb-0">You need to verify your landlord account to access all features.</p>
                        </div>
                        <div class="text-end">
                            <p class="mb-1">Redirecting in <span id="countdown">10</span> seconds...</p>
                            <a href="../verify_landlord.php" class="btn btn-sm btn-warning">Verify Now</a>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                // Countdown timer
                let seconds = 10;
                const countdownElement = document.getElementById('countdown');
                
                const countdownInterval = setInterval(() => {
                    seconds--;
                    countdownElement.textContent = seconds;
                    
                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = "../verify_landlord.php";
                    }
                }, 1000);
            </script>
            <?php endif; ?>

            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2>Dashboard Overview</h2>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Boarding Houses</h6>
                                        <h3><?php echo $total_houses; ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-house text-primary fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Tenants</h6>
                                        <h3><?php echo $total_tenants; ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-people text-success fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Income</h6>
                                        <h3>₱<?php echo number_format($total_income, 2); ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-cash-stack text-warning fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Pending Payments</h6>
                                        <h3><?php echo $pending_payments; ?></h3>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Maintenance Requests</h5>
                                <a href="maintenance.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activity)): ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted">No recent maintenance requests</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Room</th>
                                                    <th>Tenant</th>
                                                    <th>Issue</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_activity as $activity): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($activity['room_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            if ($activity['status'] == 'pending') $status_class = 'bg-warning text-dark';
                                                            elseif ($activity['status'] == 'in_progress') $status_class = 'bg-info text-white';
                                                            elseif ($activity['status'] == 'completed') $status_class = 'bg-success text-white';
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="create_boarding_house.php" class="btn btn-primary text-start">
                                        <i class="bi bi-house me-2"></i> Create New Boarding House
                                    </a>
                                    <a href="rooms.php?action=add" class="btn btn-outline-primary text-start">
                                        <i class="bi bi-plus-circle me-2"></i> Add New Room
                                    </a>
                                    <a href="tenants.php?action=add" class="btn btn-outline-success text-start">
                                        <i class="bi bi-person-plus me-2"></i> Add New Tenant
                                    </a>
                                    <a href="payments.php?action=record" class="btn btn-outline-info text-start">
                                        <i class="bi bi-cash-coin me-2"></i> Record Payment
                                    </a>
                                    <a href="maintenance.php?action=create" class="btn btn-outline-warning text-start">
                                        <i class="bi bi-tools me-2"></i> Create Maintenance Request
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Your Boarding Houses</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($houses)): ?>
                                    <div class="alert alert-info">
                                        You haven't created any boarding houses yet.
                                        <a href="create_boarding_house.php" class="alert-link">Create your first one</a>
                                    </div>
                                <?php elseif (!$is_verified): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-shield-lock"></i> Verify your account to see join codes for your boarding houses.
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($houses as $house): ?>
                                            <li class="list-group-item">
                                                <?php echo htmlspecialchars($house['name']); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($houses as $house): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><?php echo htmlspecialchars($house['name']); ?></span>
                                                    <div>
                                                        <span class="badge bg-primary"><?php echo $house['join_code']; ?></span>
                                                        <button class="btn btn-sm btn-outline-secondary ms-1" 
                                                                onclick="copyJoinCode('<?php echo $house['join_code']; ?>')" 
                                                                title="Copy join code">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> Share these codes with tenants to join your boarding houses.
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>
    <script>
        function copyJoinCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                alert('Join code copied to clipboard: ' + code);
            }, function() {
                alert('Failed to copy join code');
            });
        }
    </script>
</body>
</html>