<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] !== 'tenant') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $join_code = trim($_POST['join_code'] ?? '');

    if (empty($join_code)) {
        $error = "Please enter a join code.";
    } else {
        // Find the boarding house by join code
        $stmt = $pdo->prepare("SELECT house_id, name FROM boarding_houses WHERE join_code = ?");
        $stmt->execute([$join_code]);
        $house = $stmt->fetch();

        if ($house) {
            $house_id = $house['house_id'];

            // Check if tenant already has an active room
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_rooms WHERE tenant_id = ? AND status = 'active'");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "You already have an active room. Leave it before joining another house.";
            } else {
                // Check if tenant already requested or joined this house
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_rooms WHERE tenant_id = ? AND house_id = ? AND status IN ('active', 'pending')");
                $stmt->execute([$user_id, $house_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "You have already joined or requested to join this house.";
                } else {
                    // Find any available room
                    $stmt = $pdo->prepare("SELECT room_id FROM rooms WHERE house_id = ? AND status = 'available' LIMIT 1");
                    $stmt->execute([$house_id]);
                    $room = $stmt->fetch();

                    if ($room) {
                        // Insert pending request
                        $stmt = $pdo->prepare("INSERT INTO tenant_rooms (tenant_id, house_id, room_id, status, joined_at)
                                               VALUES (?, ?, ?, 'pending', NOW())");
                        $stmt->execute([$user_id, $house_id, $room['room_id']]);

                        $success = "Successfully joined <strong>{$house['name']}</strong>!<br>Your request is pending landlord approval.";
                    } else {
                        $error = "No available rooms in this house. Please contact your landlord.";
                    }
                }
            }
        } else {
            $error = "Invalid join code. Please check with your landlord.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join a Boarding House</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-home"></i> Join a Boarding House</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                        <div class="text-center mt-3">
                            <a href="../../dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-4">
                            <i class="fas fa-key fa-3x text-primary mb-3"></i>
                            <h5>Enter Join Code</h5>
                            <p class="text-muted">Get this code from your boarding house landlord.</p>
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-home"></i></span>
                                    <input type="text" class="form-control form-control-lg text-center" 
                                           name="join_code" placeholder="ABCD12" maxlength="6" 
                                           style="letter-spacing: 2px;" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Join House
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted">Don't have a code? Contact your landlord.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
