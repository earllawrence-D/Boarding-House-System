<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'tenant') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// New check for active room assignment
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_rooms WHERE tenant_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
    
if ($stmt->fetchColumn() == 0) {
    $_SESSION['needs_house'] = true;
    header("Location: ../../join_house.php");
    exit();
}

// Continue with existing code for users who have active room assignments
$user = get_user_info($pdo, $user_id);
$house = get_tenant_house($pdo, $user_id);
$room = get_tenant_room($pdo, $user_id);

// Get payment stats
$stmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN status IN ('pending', 'overdue') THEN amount ELSE 0 END) as total_pending,
    COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
    FROM payments WHERE tenant_id = ? AND room_id = ?");
$stmt->execute([$user_id, $room['room_id']]);
$payment_stats = $stmt->fetch();

// Get upcoming payment
$stmt = $pdo->prepare("SELECT * FROM payments 
                      WHERE tenant_id = ? AND room_id = ? AND status IN ('pending', 'overdue')
                      ORDER BY due_date ASC LIMIT 1");
$stmt->execute([$user_id, $room['room_id']]);
$next_payment = $stmt->fetch();

// Get recent announcements
$stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name 
                      FROM announcements a
                      JOIN users u ON a.posted_by = u.user_id
                      WHERE a.house_id = ? 
                      ORDER BY a.posted_at DESC
                      LIMIT 3");
$stmt->execute([$house['house_id']]);
$announcements = $stmt->fetchAll();

// Get maintenance requests
$stmt = $pdo->prepare("SELECT * FROM maintenance_requests 
                      WHERE reported_by = ? 
                      ORDER BY created_at DESC
                      LIMIT 3");
$stmt->execute([$user_id]);
$maintenance_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
            
            
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2>Dashboard Overview</h2>
                        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Current Room</h6>
                                        <h3><?php echo htmlspecialchars($room['room_number']); ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-door-open text-primary fs-4"></i>
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
                                        <h6 class="text-muted mb-2">Total Paid</h6>
                                        <h3>₱<?php echo number_format($payment_stats['total_paid'] ?? 0, 2); ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-cash-coin text-success fs-4"></i>
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
                                        <h3>₱<?php echo number_format($payment_stats['total_pending'] ?? 0, 2); ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
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
                                        <h6 class="text-muted mb-2">Overdue</h6>
                                        <h3><?php echo $payment_stats['overdue_count'] ?? 0; ?></h3>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i class="bi bi-clock-history text-danger fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Upcoming Payment Details -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Upcoming Payment</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($next_payment): ?>
                                    <div class="alert alert-<?php echo $next_payment['status'] == 'overdue' ? 'danger' : 'warning'; ?>">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6><?php echo ucfirst($next_payment['status']); ?> Payment</h6>
                                                <p class="mb-1">Due: <?php echo date('M j, Y', strtotime($next_payment['due_date'])); ?></p>
                                            </div>
                                            <h4>₱<?php echo number_format($next_payment['amount'], 2); ?></h4>
                                        </div>
                                    </div>
                                    <a href="payments.php?action=pay&id=<?php echo $next_payment['payment_id']; ?>" class="btn btn-primary w-100">
                                        <i class="bi bi-credit-card me-2"></i> Pay Now
                                    </a>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-check-circle text-success fs-1 mb-3"></i>
                                        <p class="text-muted">No upcoming payments</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Announcements -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Announcements</h5>
                                <a href="community.php" class="btn btn-sm btn-outline-primary" style="padding-left: 0.5rem; padding-right: 0.5rem;">
                                    View All
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($announcements)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <li class="list-group-item border-0 px-0 py-2">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                    <small><?php echo date('M j', strtotime($announcement['posted_at'])); ?></small>
                                                </div>
                                                <p class="mb-1 text-muted"><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 100))); ?>...</p>
                                                <small class="text-muted">Posted by <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-megaphone text-muted fs-1 mb-3"></i>
                                        <p class="text-muted">No recent announcements</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Maintenance Requests -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">My Maintenance Requests</h5>
                                <a href="maintenance.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($maintenance_requests)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($maintenance_requests as $request): ?>
                                            <li class="list-group-item border-0 px-0 py-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['title']); ?></h6>
                                                        <small class="text-muted"><?php echo date('M j', strtotime($request['created_at'])); ?></small>
                                                    </div>
                                                    <?php 
                                                    $status_class = '';
                                                    if ($request['status'] == 'pending') $status_class = 'bg-warning text-dark';
                                                    elseif ($request['status'] == 'in_progress') $status_class = 'bg-info text-white';
                                                    elseif ($request['status'] == 'completed') $status_class = 'bg-success text-white';
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-tools text-muted fs-1 mb-3"></i>
                                        <p class="text-muted">No maintenance requests</p>
                                        <a href="maintenance.php?action=create" class="btn btn-sm btn-primary">Create Request</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                 <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="payments.php" class="btn btn-outline-primary">
                                        <i class="bi bi-cash-coin me-2"></i> Make a Payment
                                    </a>
                                    <a href="maintenance.php?action=create" class="btn btn-outline-success">
                                        <i class="bi bi-tools me-2"></i> Request Maintenance
                                    </a>
                                    <a href="profile.php" class="btn btn-outline-info">
                                        <i class="bi bi-pencil me-2"></i> Update Profile
                                    </a>
                                    <a href="community.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-chat-left-text me-2"></i> View Community
                                    </a>
                                    <a href="documents.php" class="btn btn-outline-dark">
                                        <i class="bi bi-file-earmark me-2"></i> My Documents
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>