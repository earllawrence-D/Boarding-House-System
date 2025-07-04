<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'tenant') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = get_user_info($pdo, $user_id);
$tenant_info = get_tenant_info($pdo, $user_id);
$room = get_tenant_room($pdo, $user_id);

if (!$room) {
    header("Location: ../../join_house.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $parent_contact = trim($_POST['parent_contact']);
    $emergency_contact = trim($_POST['emergency_contact']);
    
    try {
        $pdo->beginTransaction();
        
        // Update users table
        $stmt = $pdo->prepare("UPDATE users SET 
                              first_name = ?,
                              last_name = ?,
                              phone = ?
                              WHERE user_id = ?");
        $stmt->execute([$first_name, $last_name, $phone, $user_id]);
        
        // Update tenant_info table
        $stmt = $pdo->prepare("UPDATE tenant_info SET 
                              parent_contact = ?,
                              emergency_contact = ?
                              WHERE user_id = ?");
        $stmt->execute([$parent_contact, $emergency_contact, $user_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
    }
}

// Display success/error messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

function get_tenant_info($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM tenant_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
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
                        <h2>My Profile</h2>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    </div>
                                    
                                    <h5 class="mt-4 mb-3">Emergency Contacts</h5>
                                    <div class="mb-3">
                                        <label for="parent_contact" class="form-label">Parent's Contact Number *</label>
                                        <input type="tel" class="form-control" id="parent_contact" name="parent_contact" value="<?php echo htmlspecialchars($tenant_info['parent_contact']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="emergency_contact" class="form-label">Emergency Contact Number *</label>
                                        <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($tenant_info['emergency_contact']); ?>" required>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Boarding Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Boarding House</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($room['house_name']); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Room Number</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($room['room_number']); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Monthly Rent</label>
                                    <input type="text" class="form-control" value="₱<?php echo number_format($room['price'], 2); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Move-In Date</label>
                                    <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($room['move_in_date'])); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($room['status']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="update_password.php">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="progress mt-2" style="height: 5px;">
                                            <div id="password-strength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small id="password-strength-text" class="text-muted"></small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
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