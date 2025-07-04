<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'tenant') {
    header("Location: ../../index.php");
    exit();
}

$tenant_id = $_SESSION['user_id'];
$status = $_GET['status'] ?? 'all';
$payment_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

// Get tenant's current room assignment
$stmt = $pdo->prepare("SELECT 
                      r.room_id, r.room_number, r.price AS rent_amount,
                      bh.house_id, bh.name AS house_name
                      FROM tenant_rooms tr
                      JOIN rooms r ON tr.room_id = r.room_id
                      JOIN boarding_houses bh ON r.house_id = bh.house_id
                      WHERE tr.tenant_id = ? AND tr.status = 'active'");
                      
$stmt->execute([$tenant_id]);
$current_room = $stmt->fetch();

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['make_payment'])) {
    try {
        // Validate required fields
        if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
            throw new Exception("Invalid payment amount");
        }

        if (empty($_POST['payment_method'])) {
            throw new Exception("Payment method is required");
        }

        $amount = (float)$_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $reference_number = trim($_POST['reference_number'] ?? '');
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        $proof_image = handle_file_upload();

        // Verify payment belongs to tenant if editing existing payment
        if ($payment_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND tenant_id = ?");
            $stmt->execute([$payment_id, $tenant_id]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                throw new Exception("Invalid payment record");
            }
        }

        $pdo->beginTransaction();

        if ($payment_id > 0) {
            // Update existing payment record
            $stmt = $pdo->prepare("UPDATE payments SET 
                                amount = ?,
                                payment_method = ?,
                                reference_number = ?,
                                proof_image = COALESCE(?, proof_image),
                                status = 'paid',
                                payment_date = NOW()
                                WHERE payment_id = ?");
            $stmt->execute([$amount, $payment_method, $reference_number, $proof_image, $payment_id]);
        } else {
            // Create new payment record
            $due_date = date('Y-m-d', strtotime('+1 month'));
            $stmt = $pdo->prepare("INSERT INTO payments 
                                (tenant_id, room_id, amount, payment_method, 
                                reference_number, proof_image, status, 
                                due_date, payment_date)
                                VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, NOW())");
            $stmt->execute([$tenant_id, $current_room['room_id'], $amount, $payment_method, 
                          $reference_number, $proof_image, $due_date]);
            $payment_id = $pdo->lastInsertId();
        }

        // Record transaction
        $stmt = $pdo->prepare("INSERT INTO transactions 
                             (tenant_id, room_id, payment_id, amount, transaction_date, description)
                             VALUES (?, ?, ?, ?, NOW(), ?)");
        $description = "Rent payment";
        $stmt->execute([$tenant_id, $current_room['room_id'], $payment_id, $amount, $description]);

        $pdo->commit();
        $_SESSION['success'] = "Payment successfully processed!";
        header("Location: tenant_payments.php");
        exit();

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: tenant_payments.php");
    exit();
}

function handle_file_upload() {
    if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] != UPLOAD_ERR_OK) {
        return null;
    }

    // Validate file
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($_FILES['proof_image']['size'] > $max_size) {
        throw new Exception("File size exceeds 5MB limit");
    }

    $target_dir = "../../uploads/payment_proofs/";
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }

    $file_ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($file_ext, $allowed_ext)) {
        throw new Exception("Invalid file type. Only JPG, PNG, and PDF are allowed.");
    }

    $file_name = uniqid() . '.' . $file_ext;
    $target_file = $target_dir . $file_name;

    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $target_file)) {
        throw new Exception("Failed to upload file");
    }

    return $file_name;
}

// Get payments for the tenant
$payments = [];
if ($current_room) {
    $query = "SELECT 
                p.payment_id, p.amount, p.due_date, p.payment_date, p.status, p.payment_method,
                p.reference_number, p.proof_image,
                r.room_id, r.room_number
              FROM payments p
              JOIN rooms r ON p.room_id = r.room_id
              WHERE p.tenant_id = ?";
    
    $params = [$tenant_id];
    
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

if ($current_room) {
    $stmt = $pdo->prepare("SELECT 
                          SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid,
                          SUM(CASE WHEN status = 'pending' AND due_date >= CURDATE() THEN amount ELSE 0 END) as pending,
                          SUM(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN amount ELSE 0 END) as overdue
                          FROM payments
                          WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $totals = $stmt->fetch();
    
    $total_paid = $totals['paid'] ?? 0;
    $total_pending = $totals['pending'] ?? 0;
    $total_overdue = $totals['overdue'] ?? 0;
}

// Get next payment due
$next_payment = null;
if ($current_room) {
    $stmt = $pdo->prepare("SELECT * FROM payments 
                          WHERE tenant_id = ? AND room_id = ? AND status IN ('pending', 'overdue')
                          ORDER BY due_date ASC LIMIT 1");
    $stmt->execute([$tenant_id, $current_room['room_id']]);
    $next_payment = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include 'includes/tenant_sidebar.php'; ?>
    
    <div id="content">
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2>My Payments</h2>
                    <?php if (isset($current_room['house_name'])): ?>
                        <h4 class="text-muted">
                            <?php echo htmlspecialchars($current_room['house_name']); ?> - 
                            Room <?php echo htmlspecialchars($current_room['room_number']); ?>
                        </h4>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if ($action == 'pay'): ?>
                <!-- Payment Form -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-credit-card"></i> 
                                    <?= $payment_id ? 'Make Payment' : 'New Payment' ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Payment For</label>
                                            <input type="text" class="form-control" 
                                                value="<?= $payment_id ? 'Rent Payment' : 'Rent' ?>" 
                                                readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="amount" class="form-label">Amount (₱) *</label>
                                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                                                value="<?= $payment_id ? $payments[array_search($payment_id, array_column($payments, 'payment_id'))]['amount'] : $current_room['rent_amount'] ?>" 
                                                required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="payment_method" class="form-label">Payment Method *</label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">-- Select Method --</option>
                                                <option value="Cash">Cash</option>
                                                <option value="GCash">GCash</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="PayPal">PayPal</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="reference_number" class="form-label">Reference Number</label>
                                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                                placeholder="e.g., transaction ID">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="proof_image" class="form-label">Payment Proof (Optional)</label>
                                        <input type="file" class="form-control" id="proof_image" name="proof_image" accept="image/*,.pdf">
                                        <div class="form-text">Upload receipt (JPG, PNG, or PDF, max 5MB)</div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="tenant_payments.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" name="make_payment" class="btn btn-primary">Submit Payment</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Main Payment Page -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Status</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Payments</option>
                                    <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="overdue" <?php echo $status == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-8 text-end">
                                <a href="tenant_payments.php?action=pay" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> New Payment
                                </a>
                                <?php if ($next_payment): ?>
                                    <a href="tenant_payments.php?action=pay&id=<?= $next_payment['payment_id'] ?>" class="btn btn-success ms-2">
                                        <i class="bi bi-credit-card"></i> Pay Next Due
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($current_room): ?>
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
                            <h5 class="mb-0">My Payment Records</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($payments)): ?>
                                <div class="alert alert-info">No payments found.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Room No.</th>
                                                <th>Amount</th>
                                                <th>Due Date</th>
                                                <th>Payment Date</th>
                                                <th>Status</th>
                                                <th>Payment Method</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
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
                                                        <?php echo $payment['payment_method'] ? ucfirst($payment['payment_method']) : '--'; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($payment['status'] == 'pending' || $payment['status'] == 'overdue'): ?>
                                                            <a href="tenant_payments.php?action=pay&id=<?= $payment['payment_id'] ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-credit-card"></i> Pay
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($payment['proof_image']): ?>
                                                            <a href="../../uploads/payment_proofs/<?= $payment['proof_image'] ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                                <i class="bi bi-receipt"></i> Receipt
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">You are not currently assigned to any room.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const paymentForm = document.querySelector('form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                const amount = document.getElementById('amount');
                const method = document.getElementById('payment_method');
                
                if (!amount.value || isNaN(amount.value) || amount.value <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid payment amount');
                    amount.focus();
                    return false;
                }
                
                if (!method.value) {
                    e.preventDefault();
                    alert('Please select a payment method');
                    method.focus();
                    return false;
                }
                
                return true;
            });
        }
    });
</script>
</body>
</html>