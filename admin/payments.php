<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Get all payments with tenant and house info
$stmt = $pdo->query("
    SELECT p.*, 
           t.first_name as tenant_first, t.last_name as tenant_last,
           bh.name as house_name,
           l.first_name as landlord_first, l.last_name as landlord_last
    FROM payments p
    JOIN users t ON p.tenant_id = t.user_id
    JOIN tenant_rooms tr ON p.tenant_id = tr.tenant_id
    JOIN boarding_houses bh ON tr.house_id = bh.house_id
    JOIN users l ON bh.landlord_id = l.user_id
    ORDER BY p.payment_date DESC
");
$payments = $stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payment Records - Boarding House Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>                                                                                                                                                                                                                                                                                                                                                                                                 <?php include 'includes/navbar.php'; ?>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
    
        <button id="mobileSidebarToggle" class="btn btn-primary d-lg-none mb-3 ms-3">
            <i class="bi bi-list"></i>
        </button> 
    <div id="content">
        <div class="container-fluid px-4">
            <div class="row mb-4">
                <div class="col-12">
                <h2 class="my-0">Payment Records</h2>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="paymentFilters">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <input type="text" class="form-control daterange" name="daterange">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All</option>
                                    <option value="paid">Paid</option>
                                    <option value="pending">Pending</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">House</label>
                                <select class="form-select" name="house_id">
                                    <option value="">All Houses</option>
                                    <?php 
                                    $houses = $pdo->query("SELECT house_id, name FROM boarding_houses")->fetchAll();
                                    foreach ($houses as $house): ?>
                                        <option value="<?= $house['house_id'] ?>"><?= htmlspecialchars($house['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tenant</th>
                                    <th>House</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>For Month</th>
                                    <th>Status</th>
                                    <th>Landlord</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= $payment['payment_id'] ?></td>
                                    <td><?= htmlspecialchars($payment['tenant_first'] . ' ' . $payment['tenant_last']) ?></td>
                                    <td><?= htmlspecialchars($payment['house_name']) ?></td>
                                    <td>₱<?= number_format($payment['amount'], 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                    <td><?= date('F Y', strtotime($payment['for_month'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $payment['status'] == 'paid' ? 'success' : 
                                            ($payment['status'] == 'pending' ? 'warning' : 'danger')
                                        ?>">
                                            <?= ucfirst($payment['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($payment['landlord_first'] . ' ' . $payment['landlord_last']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-receipt" 
                                            data-id="<?= $payment['payment_id'] ?>">
                                            <i class="bi bi-receipt"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning edit-payment" 
                                            data-id="<?= $payment['payment_id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
    <!-- Modal content here -->
</div>

<!-- View Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <!-- Modal content here -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>    
</body>
</html>