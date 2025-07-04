<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');


if (!is_logged_in() || $_SESSION['user_type'] != 'landlord') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$house_id = isset($_GET['house_id']) ? (int)$_GET['house_id'] : null;

// Get all houses owned by landlord
$houses = get_landlord_houses($pdo, $user_id);

// Get tenants for selected house
$tenants = [];
if ($house_id) {
    $stmt = $pdo->prepare("SELECT u.*, tr.move_in_date, tr.move_out_date, tr.status as tenant_status, 
                          r.room_number, r.price
                          FROM users u
                          JOIN tenant_rooms tr ON u.user_id = tr.tenant_id
                          JOIN rooms r ON tr.room_id = r.room_id
                          WHERE r.house_id = ?");
    $stmt->execute([$house_id]);
    $tenants = $stmt->fetchAll();
}

// Handle tenant actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['remove_tenant'])) {
        $tenant_id = (int)$_POST['tenant_id'];
        $room_id = (int)$_POST['room_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Update tenant_rooms status
            $stmt = $pdo->prepare("UPDATE tenant_rooms SET status = 'past', move_out_date = CURDATE() 
                                  WHERE tenant_id = ? AND room_id = ?");
            $stmt->execute([$tenant_id, $room_id]);
            
            // Update room occupancy
            $stmt = $pdo->prepare("UPDATE rooms SET current_occupancy = current_occupancy - 1, 
                                  status = IF(current_occupancy - 1 <= 0, 'available', 'occupied') 
                                  WHERE room_id = ?");
            $stmt->execute([$room_id]);
            
            $pdo->commit();
            $_SESSION['success'] = "Tenant removed successfully";
            header("Location: tenants.php?house_id=$house_id");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error removing tenant: " . $e->getMessage();
        }
    }
}

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
    <title>Tenant Tracking</title>
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
                        <h2>Tenant Tracking</h2>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Select Boarding House</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <select class="form-select" name="house_id" onchange="this.form.submit()">
                                    <option value="">-- Select Boarding House --</option>
                                    <?php foreach ($houses as $house): ?>
                                        <option value="<?php echo $house['house_id']; ?>" <?php echo $house_id == $house['house_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($house['name']); ?> (Code: <?php echo $house['join_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <a href="rooms.php?action=add_house" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-plus"></i> Add New House
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($house_id): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Current Tenants</h5>
                            <a href="tenants.php?action=add&house_id=<?php echo $house_id; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-person-plus"></i> Add Tenant
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tenants)): ?>
                                <div class="alert alert-info">No tenants found for this boarding house.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Contact</th>
                                                <th>Room</th>
                                                <th>Rent</th>
                                                <th>Move In Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tenants as $tenant): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($tenant['phone']); ?><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($tenant['email']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($tenant['room_number']); ?></td>
                                                    <td>₱<?php echo number_format($tenant['price'], 2); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($tenant['move_in_date'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $tenant['tenant_status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo ucfirst($tenant['tenant_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="tenant_profile.php?id=<?php echo $tenant['user_id']; ?>" class="btn btn-outline-info" title="View Profile">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if ($tenant['tenant_status'] == 'active'): ?>
                                                                <form method="POST" style="display:inline;">
                                                                    <input type="hidden" name="tenant_id" value="<?php echo $tenant['user_id']; ?>">
                                                                    <input type="hidden" name="room_id" value="<?php echo $tenant['room_id']; ?>">
                                                                    <button type="submit" name="remove_tenant" class="btn btn-outline-danger" title="Remove Tenant" onclick="return confirm('Are you sure you want to remove this tenant?');">
                                                                        <i class="bi bi-person-x"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>