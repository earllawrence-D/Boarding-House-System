<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'tenant') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$house = get_tenant_house($pdo, $user_id);
$room = get_tenant_room($pdo, $user_id);

if (!$house || !$room) {
    header("Location: ../../join_house.php");
    exit();
}

// Get all announcements for this house
$stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name 
                      FROM announcements a
                      JOIN users u ON a.posted_by = u.user_id
                      WHERE a.house_id = ?
                      ORDER BY a.posted_at DESC");
$stmt->execute([$house['house_id']]);
$announcements = $stmt->fetchAll();

// Get all tenants in the same house
$stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name, u.phone, u.email, r.room_number
                      FROM users u
                      JOIN tenant_rooms tr ON u.user_id = tr.tenant_id
                      JOIN rooms r ON tr.room_id = r.room_id
                      WHERE r.house_id = ? AND tr.status = 'active' AND u.user_id != ?
                      ORDER BY r.room_number, u.first_name");
$stmt->execute([$house['house_id'], $user_id]);
$tenants = $stmt->fetchAll();

// Display success/error messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community</title>
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
                        <h2>Community</h2>
                        <p class="text-muted"><?php echo htmlspecialchars($house['name']); ?></p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Announcements</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <div class="alert alert-info">No announcements yet.</div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                                    <small><?php echo date('M j, Y', strtotime($announcement['posted_at'])); ?></small>
                                                </div>
                                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                                <small>Posted by <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">House Members</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($tenants)): ?>
                                    <div class="alert alert-info">No other tenants in this boarding house.</div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($tenants as $tenant): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <div class="avatar-sm bg-light rounded-circle text-center pt-2">
                                                            <i class="bi bi-person fs-4"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></h6>
                                                        <small class="text-muted">Room <?php echo htmlspecialchars($tenant['room_number']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">House Rules</h5>
                            </div>
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li>Quiet hours are from 10 PM to 7 AM</li>
                                    <li>No overnight guests without prior approval</li>
                                    <li>Keep common areas clean</li>
                                    <li>No smoking inside the house</li>
                                    <li>Report any maintenance issues promptly</li>
                                    <li>Respect other tenants' privacy and property</li>
                                    <li>Pay rent on time (due on the 1st of each month)</li>
                                </ol>
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