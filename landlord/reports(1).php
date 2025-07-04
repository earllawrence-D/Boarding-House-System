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
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// Get all houses owned by landlord
$houses = get_landlord_houses($pdo, $user_id);

// Get financial summary for selected house
$financial_summary = [];
$occupancy_stats = [];
$payment_trends = [];

if ($house_id) {
    // Financial summary
    $stmt = $pdo->prepare("SELECT 
                          SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_income,
                          COUNT(CASE WHEN status = 'paid' THEN 1 END) as payments_received,
                          COUNT(CASE WHEN status IN ('pending', 'overdue') THEN 1 END) as payments_pending,
                          COUNT(CASE WHEN status = 'overdue' THEN 1 END) as payments_overdue
                          FROM payments p
                          JOIN rooms r ON p.room_id = r.room_id
                          WHERE r.house_id = ?");
    $stmt->execute([$house_id]);
    $financial_summary = $stmt->fetch();
    
    // Occupancy stats
    $stmt = $pdo->prepare("SELECT 
                          COUNT(*) as total_rooms,
                          SUM(capacity) as total_capacity,
                          SUM(current_occupancy) as current_occupancy,
                          SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
                          SUM(CASE WHEN status = 'occupied' THEN 1 ELSE
                                                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms
                          FROM rooms
                          WHERE house_id = ?");
    $stmt->execute([$house_id]);
    $occupancy_stats = $stmt->fetch();
    
    // Payment trends (monthly)
    $stmt = $pdo->prepare("SELECT 
                          MONTH(payment_date) as month,
                          SUM(amount) as total_paid,
                          COUNT(*) as payment_count
                          FROM payments p
                          JOIN rooms r ON p.room_id = r.room_id
                          WHERE r.house_id = ? AND YEAR(payment_date) = ? AND status = 'paid'
                          GROUP BY MONTH(payment_date)
                          ORDER BY MONTH(payment_date)");
    $stmt->execute([$house_id, $year]);
    $payment_trends = $stmt->fetchAll();
    
    // Maintenance costs
    $stmt = $pdo->prepare("SELECT 
                          COUNT(*) as total_requests,
                          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
                          SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority,
                          SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_priority,
                          SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_priority
                          FROM maintenance_requests mr
                          JOIN rooms r ON mr.room_id = r.room_id
                          WHERE r.house_id = ?");
    $stmt->execute([$house_id]);
    $maintenance_stats = $stmt->fetch();
}

// Get years with payments for dropdown
$stmt = $pdo->prepare("SELECT DISTINCT YEAR(payment_date) as year 
                      FROM payments p
                      JOIN rooms r ON p.room_id = r.room_id
                      WHERE r.house_id = ? AND payment_date IS NOT NULL
                      ORDER BY year DESC");
$stmt->execute([$house_id]);
$available_years = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div id="content">
            <?php include 'includes/navbar.php'; ?>

            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2>Reports & Analytics</h2>
                    </div>
                </div>

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
                            <?php if ($house_id): ?>
                            <div class="col-md-2">
                                <select class="form-select" name="year" onchange="this.form.submit()">
                                    <?php foreach ($available_years as $y): ?>
                                        <option value="<?php echo $y['year']; ?>" <?php echo $year == $y['year'] ? 'selected' : ''; ?>>
                                            <?php echo $y['year']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="month" onchange="this.form.submit()">
                                    <?php 
                                    $months = [
                                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                    ];
                                    foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $month == $num ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if ($house_id): ?>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Financial Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <div class="card bg-success text-white">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Total Income</h6>
                                                    <h3>₱<?php echo number_format($financial_summary['total_income'] ?? 0, 2); ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Payments Received</h6>
                                                    <h3><?php echo $financial_summary['payments_received'] ?? 0; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-warning text-dark">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Pending Payments</h6>
                                                    <h3><?php echo $financial_summary['payments_pending'] ?? 0; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-danger text-white">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Overdue Payments</h6>
                                                    <h3><?php echo $financial_summary['payments_overdue'] ?? 0; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Occupancy Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <div class="card bg-info text-white">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Total Rooms</h6>
                                                    <h3><?php echo $occupancy_stats['total_rooms'] ?? 0; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <div class="card bg-secondary text-white">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Total Capacity</h6>
                                                    <h3><?php echo $occupancy_stats['total_capacity'] ?? 0; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-success text-white">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Current Occupancy</h6>
                                                    <h3><?php echo $occupancy_stats['current_occupancy'] ?? 0; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light text-dark">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title">Available Rooms</h6>
                                                    <h3><?php echo $occupancy_stats['available_rooms'] ?? 0; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Payment Trends (<?php echo $year; ?>)</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="paymentTrendsChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Maintenance Requests</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="maintenanceChart" height="300"></canvas>
                                    <div class="mt-3">
                                        <p class="mb-1"><span class="badge bg-danger me-2"></span> High Priority: <?php echo $maintenance_stats['high_priority'] ?? 0; ?></p>
                                        <p class="mb-1"><span class="badge bg-warning text-dark me-2"></span> Medium Priority: <?php echo $maintenance_stats['medium_priority'] ?? 0; ?></p>
                                        <p class="mb-1"><span class="badge bg-info me-2"></span> Low Priority: <?php echo $maintenance_stats['low_priority'] ?? 0; ?></p>
                                        <p class="mb-0"><span class="badge bg-success me-2"></span> Completed: <?php echo $maintenance_stats['completed_requests'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Export Reports</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="export_payments.php?house_id=<?php echo $house_id; ?>&type=csv" class="btn btn-outline-primary">
                                    <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Payments (CSV)
                                </a>
                                <a href="export_payments.php?house_id=<?php echo $house_id; ?>&type=pdf" class="btn btn-outline-danger">
                                    <i class="bi bi-file-earmark-pdf me-2"></i> Export Payments (PDF)
                                </a>
                                <a href="export_tenants.php?house_id=<?php echo $house_id; ?>&type=csv" class="btn btn-outline-primary">
                                    <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Tenants (CSV)
                                </a>
                                <a href="export_maintenance.php?house_id=<?php echo $house_id; ?>&type=csv" class="btn btn-outline-primary">
                                    <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Maintenance (CSV)
                                </a>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Payment Trends Chart
                        const paymentTrendsCtx = document.getElementById('paymentTrendsChart').getContext('2d');
                        const paymentTrendsChart = new Chart(paymentTrendsCtx, {
                            type: 'line',
                            data: {
                                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                                datasets: [{
                                    label: 'Total Payments (₱)',
                                    data: [
                                        <?php 
                                        $monthly_totals = array_fill(0, 12, 0);
                                        foreach ($payment_trends as $trend) {
                                            $monthly_totals[$trend['month'] - 1] = $trend['total_paid'];
                                        }
                                        echo implode(', ', $monthly_totals);
                                        ?>
                                    ],
                                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 2,
                                    tension: 0.3
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return '₱' + context.raw.toLocaleString('en-PH', {minimumFractionDigits: 2});
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return '₱' + value.toLocaleString('en-PH');
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        // Maintenance Requests Chart
                        const maintenanceCtx = document.getElementById('maintenanceChart').getContext('2d');
                        const maintenanceChart = new Chart(maintenanceCtx, {
                            type: 'doughnut',
                            data: {
                                labels: ['High Priority', 'Medium Priority', 'Low Priority'],
                                datasets: [{
                                    data: [
                                        <?php echo $maintenance_stats['high_priority'] ?? 0; ?>,
                                        <?php echo $maintenance_stats['medium_priority'] ?? 0; ?>,
                                        <?php echo $maintenance_stats['low_priority'] ?? 0; ?>
                                    ],
                                    backgroundColor: [
                                        'rgba(220, 53, 69, 0.7)',
                                        'rgba(255, 193, 7, 0.7)',
                                        'rgba(23, 162, 184, 0.7)'
                                    ],
                                    borderColor: [
                                        'rgba(220, 53, 69, 1)',
                                        'rgba(255, 193, 7, 1)',
                                        'rgba(23, 162, 184, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                    }
                                }
                            }
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>