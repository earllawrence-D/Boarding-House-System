<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] !== 'landlord') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$pending_requests = get_pending_join_requests($pdo, $user_id);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $request_id = (int) $_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve' && isset($_POST['room_id'])) {
        $room_id = (int) $_POST['room_id'];

        // Approve the request
        $stmt = $pdo->prepare("UPDATE tenant_rooms SET status = 'active', room_id = ? WHERE id = ?");
        $stmt->execute([$room_id, $request_id]);

        // Update room status
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?");
        $stmt->execute([$room_id]);

        $success = "Tenant approved and room assigned.";
    } elseif ($action === 'reject') {
        // Reject request
        $stmt = $pdo->prepare("DELETE FROM tenant_rooms WHERE id = ?");
        $stmt->execute([$request_id]);

        $success = "Tenant request rejected.";
    }

    // Refresh pending requests
    $pending_requests = get_pending_join_requests($pdo, $user_id);
}

// Get all available rooms per house
$available_rooms = [];
$stmt = $pdo->prepare("SELECT room_id, room_number, house_id FROM rooms WHERE status = 'available'");
$stmt->execute();
foreach ($stmt->fetchAll() as $room) {
    $available_rooms[$room['house_id']][] = $room;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Join Requests</title>
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
                    <h2>Pending Join Requests</h2>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <?php if (empty($pending_requests)): ?>
                        <div class="alert alert-info">No pending join requests.</div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Email</th>
                                                <th>Boarding House</th>
                                                <th>Request Date</th>
                                                <th>Assign Room</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($pending_requests as $request): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></td>
                                                <td><?= htmlspecialchars($request['email']) ?></td>
                                                <td><?= htmlspecialchars($request['house_name']) ?></td>
                                                <td><?= date('M j, Y', strtotime($request['joined_at'])) ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <select name="room_id" class="form-select" required>
                                                            <option value="">Select Room</option>
                                                            <?php foreach ($available_rooms[$request['house_id']] ?? [] as $room): ?>
                                                                <option value="<?= $room['room_id'] ?>">
                                                                    <?= htmlspecialchars($room['room_number']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="POST">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>
    <script>
</body>
</html>