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
$status = $_GET['status'] ?? 'all';

// Get all houses owned by landlord
$houses = get_landlord_houses($pdo, $user_id);

// Get payments for selected house with tenant and room details
$payments = [];
$house_name = '';
if ($house_id) {
    // Get boarding house name
    $stmt = $pdo->prepare("SELECT name FROM boarding_houses WHERE house_id = ?");
    $stmt->execute([$house_id]);
    $house = $stmt->fetch();
    $house_name = $house['name'] ?? '';
    
    // Get payments with tenant and room info
    $query = "SELECT 
                p.payment_id, p.amount, p.due_date, p.payment_date, p.status, p.payment_method,
                u.user_id as tenant_id, u.first_name, u.last_name, u.phone, u.email,
                r.room_id, r.room_number
              FROM payments p
              JOIN users u ON p.tenant_id = u.user_id
              JOIN rooms r ON p.room_id = r.room_id
              WHERE r.house_id = ?";
    
    $params = [$house_id];
    
    if ($status != 'all') {
        $query .= " AND p.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY p.due_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
}

// Calculate totals
$total_paid = 0;
$total_pending = 0;
$total_overdue = 0;

if ($house_id) {
    $stmt = $pdo->prepare("SELECT 
                          SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as paid,
                          SUM(CASE WHEN p.status = 'pending' AND p.due_date >= CURDATE() THEN p.amount ELSE 0 END) as pending,
                          SUM(CASE WHEN p.status = 'pending' AND p.due_date < CURDATE() THEN p.amount ELSE 0 END) as overdue
                          FROM payments p
                          JOIN rooms r ON p.room_id = r.room_id
                          WHERE r.house_id = ?");
    $stmt->execute([$house_id]);
    $totals = $stmt->fetch();
    
    $total_paid = $totals['paid'] ?? 0;
    $total_pending = $totals['pending'] ?? 0;
    $total_overdue = $totals['overdue'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management</title>
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
                    <h2>Payment Management</h2>
                    <?php if ($house_name): ?>
                        <h4 class="text-muted"><?php echo htmlspecialchars($house_name); ?></h4>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
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
                                        <?php echo htmlspecialchars($house['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Payments</option>
                                <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="overdue" <?php echo $status == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($house_id): ?>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title">Total Paid</h6>
                                <h3>₱<?php echo number_format($total_paid, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title">Pending Payments</h6>
                                <h3>₱<?php echo number_format($total_pending, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6 class="card-title">Overdue Payments</h6>
                                <h3>₱<?php echo number_format($total_overdue, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Records</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="alert alert-info">No payments found for this boarding house.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tenant ID</th>
                                            <th>Tenant Name</th>
                                            <th>Contact</th>
                                            <th>Room ID</th>
                                            <th>Room No.</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Payment Date</th>
                                            <th>Status</th>
                                            <th>Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['tenant_id']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($payment['phone']); ?><br>
                                                    <?php echo htmlspecialchars($payment['email']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($payment['room_id']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['room_number']); ?></td>
                                                <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($payment['due_date'])); ?></td>
                                                <td>
                                                    <?php echo $payment['payment_date'] ? date('M j, Y', strtotime($payment['payment_date'])) : '--'; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    if ($payment['status'] == 'paid') $status_class = 'bg-success';
                                                    elseif ($payment['status'] == 'overdue') $status_class = 'bg-danger';
                                                    else $status_class = 'bg-warning text-dark';
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($payment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="payment_receipt.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-receipt"></i> View
                                                    </a>
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