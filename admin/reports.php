<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Authentication check
if (!is_logged_in() || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Get report type or default to summary
$report_type = isset($_GET['report']) ? $_GET['report'] : 'summary';

// Initialize data arrays
$stats = $gender_data = $origin_data = $house_population = $landlord_income = [];

// Always get basic stats
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN user_type = 'landlord' THEN 1 ELSE 0 END) as landlord_count,
    SUM(CASE WHEN user_type = 'tenant' THEN 1 ELSE 0 END) as tenant_count
    FROM users");
$stats = $stmt->fetch();

// Get data based on report type
switch ($report_type) {
    case 'demographics':
        $stmt = $pdo->query("SELECT ti.gender, COUNT(*) as count 
            FROM tenant_info ti
            JOIN users u ON ti.user_id = u.user_id
            WHERE u.user_type = 'tenant'
            GROUP BY ti.gender");
        $gender_data = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT CONCAT(ti.city, ', ', ti.province) as origin, COUNT(*) as count
            FROM tenant_info ti
            JOIN users u ON ti.user_id = u.user_id
            WHERE u.user_type = 'tenant'
            GROUP BY origin
            ORDER BY count DESC
            LIMIT 5");
        $origin_data = $stmt->fetchAll();
        break;
        
    case 'housing':
        $stmt = $pdo->query("SELECT 
            bh.house_id, bh.name as house_name, 
            COUNT(tr.tenant_id) as tenant_count,
            l.first_name as landlord_first, l.last_name as landlord_last
            FROM boarding_houses bh
            LEFT JOIN tenant_rooms tr ON bh.house_id = tr.house_id AND tr.status = 'active'
            JOIN users l ON bh.landlord_id = l.user_id
            GROUP BY bh.house_id
            ORDER BY tenant_count DESC");
        $house_population = $stmt->fetchAll();
        break;
        
    case 'financial':
        $stmt = $pdo->query("SELECT 
            u.user_id, u.first_name, u.last_name,
            COUNT(DISTINCT bh.house_id) as house_count,
            COUNT(DISTINCT tr.tenant_id) as tenant_count,
            SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as total_income
            FROM users u
            LEFT JOIN boarding_houses bh ON u.user_id = bh.landlord_id
            LEFT JOIN tenant_rooms tr ON bh.house_id = tr.house_id AND tr.status = 'active'
            LEFT JOIN payments p ON tr.tenant_id = p.tenant_id AND p.status = 'paid'
            WHERE u.user_type = 'landlord'
            GROUP BY u.user_id
            ORDER BY total_income DESC");
        $landlord_income = $stmt->fetchAll();
        $total_income = array_sum(array_column($landlord_income, 'total_income'));
        break;
        
    case 'summary':
    default:
        // Get all data for summary report
        $stmt = $pdo->query("SELECT ti.gender, COUNT(*) as count 
            FROM tenant_info ti
            JOIN users u ON ti.user_id = u.user_id
            WHERE u.user_type = 'tenant'
            GROUP BY ti.gender");
        $gender_data = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT CONCAT(ti.city, ', ', ti.province) as origin, COUNT(*) as count
            FROM tenant_info ti
            JOIN users u ON ti.user_id = u.user_id
            WHERE u.user_type = 'tenant'
            GROUP BY origin
            ORDER BY count DESC
            LIMIT 5");
        $origin_data = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT 
            bh.house_id, bh.name as house_name, 
            COUNT(tr.tenant_id) as tenant_count,
            l.first_name as landlord_first, l.last_name as landlord_last
            FROM boarding_houses bh
            LEFT JOIN tenant_rooms tr ON bh.house_id = tr.house_id AND tr.status = 'active'
            JOIN users l ON bh.landlord_id = l.user_id
            GROUP BY bh.house_id
            ORDER BY tenant_count DESC");
        $house_population = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT 
            u.user_id, u.first_name, u.last_name,
            COUNT(DISTINCT bh.house_id) as house_count,
            COUNT(DISTINCT tr.tenant_id) as tenant_count,
            SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as total_income
            FROM users u
            LEFT JOIN boarding_houses bh ON u.user_id = bh.landlord_id
            LEFT JOIN tenant_rooms tr ON bh.house_id = tr.house_id AND tr.status = 'active'
            LEFT JOIN payments p ON tr.tenant_id = p.tenant_id AND p.status = 'paid'
            WHERE u.user_type = 'landlord'
            GROUP BY u.user_id
            ORDER BY total_income DESC");
        $landlord_income = $stmt->fetchAll();
        $total_income = array_sum(array_column($landlord_income, 'total_income'));
        break;
}

// Get current page for active sidebar state
$current_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Reports - Boarding House Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .report-section {
            display: none;
        }
        .report-section.active {
            display: block;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .export-btn {
            margin-left: 10px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
        
        <div class="container-fluid px-4">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Administrative Reports</h2>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                            <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')"><i class="bi bi-file-earmark-pdf"></i> PDF</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportReport('excel')"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportReport('print')"><i class="bi bi-printer"></i> Print</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'summary' ? 'active' : '' ?>" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                        <i class="bi bi-speedometer2"></i> Summary
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'demographics' ? 'active' : '' ?>" id="demographics-tab" data-bs-toggle="tab" data-bs-target="#demographics" type="button" role="tab">
                        <i class="bi bi-people"></i> Demographics
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'housing' ? 'active' : '' ?>" id="housing-tab" data-bs-toggle="tab" data-bs-target="#housing" type="button" role="tab">
                        <i class="bi bi-house"></i> Housing
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'financial' ? 'active' : '' ?>" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button" role="tab">
                        <i class="bi bi-cash-stack"></i> Financial
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="reportTabsContent">
                <!-- Summary Report -->
                <div class="tab-pane fade <?= $report_type == 'summary' ? 'show active' : '' ?>" id="summary" role="tabpanel">
                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-2">Total Users</h6>
                                            <h3><?= $stats['total_users'] ?></h3>
                                        </div>
                                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-people text-primary fs-4"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted">Admins</span>
                                            <span><?= $stats['admin_count'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted">Landlords</span>
                                            <span><?= $stats['landlord_count'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted">Tenants</span>
                                            <span><?= $stats['tenant_count'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-2">Boarding Houses</h6>
                                            <h3><?= count($house_population) ?></h3>
                                        </div>
                                        <div class="bg-success bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-house text-success fs-4"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted">Active Tenants</span>
                                            <span><?= $stats['tenant_count'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-2">Total Income</h6>
                                            <h3>₱<?= number_format($total_income, 2) ?></h3>
                                        </div>
                                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-cash-stack text-warning fs-4"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted">From Landlords</span>
                                            <span><?= count($landlord_income) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-2">System Status</h6>
                                            <h3 class="text-success">Active</h3>
                                        </div>
                                        <div class="bg-info bg-opacity-10 p-3 rounded">
                                            <i class="bi bi-heart-pulse text-info fs-4"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted">Last Updated</span>
                                            <span><?= date('M j, H:i') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Tenant Gender Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="genderChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Top Tenant Origins</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="originChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">House Population</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="houseChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Landlord Income</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="incomeChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Demographics Report -->
                <div class="tab-pane fade <?= $report_type == 'demographics' ? 'show active' : '' ?>" id="demographics" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Tenant Gender Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="genderChartDemo"></canvas>
                                    </div>
                                    <div class="mt-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Gender</th>
                                                    <th>Count</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_gender = array_sum(array_column($gender_data, 'count'));
                                                foreach ($gender_data as $gender): 
                                                    $percentage = round(($gender['count'] / $total_gender) * 100, 1);
                                                ?>
                                                    <tr>
                                                        <td><?= ucfirst($gender['gender']) ?></td>
                                                        <td><?= $gender['count'] ?></td>
                                                        <td>
                                                            <div class="progress">
                                                                <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%" 
                                                                    aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                                    <?= $percentage ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Top Tenant Origins</h5>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary active" onclick="changeChartType('originChartDemo', 'bar')">Bar</button>
                                        <button class="btn btn-outline-secondary" onclick="changeChartType('originChartDemo', 'pie')">Pie</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="originChartDemo"></canvas>
                                    </div>
                                    <div class="mt-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Place of Origin</th>
                                                    <th>Count</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($origin_data as $origin): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($origin['origin']) ?></td>
                                                        <td><?= $origin['count'] ?></td>
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

                <!-- Housing Report -->
                <div class="tab-pane fade <?= $report_type == 'housing' ? 'show active' : '' ?>" id="housing" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">House Population Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container mb-4">
                                <canvas id="houseChartHousing"></canvas>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>House Name</th>
                                            <th>Landlord</th>
                                            <th>Tenants</th>
                                            <th>Occupancy Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($house_population as $house): 
                                            $occupancy_rate = ($house['tenant_count'] / 20) * 100; // Assuming capacity of 20 per house
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($house['house_name']) ?></td>
                                                <td><?= htmlspecialchars($house['landlord_first'] . ' ' . $house['landlord_last']) ?></td>
                                                <td><?= $house['tenant_count'] ?></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar" role="progressbar" 
                                                            style="width: <?= $occupancy_rate ?>%" 
                                                            aria-valuenow="<?= $occupancy_rate ?>" 
                                                            aria-valuemin="0" 
                                                            aria-valuemax="100">
                                                            <?= number_format($occupancy_rate, 1) ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Report -->
                <div class="tab-pane fade <?= $report_type == 'financial' ? 'show active' : '' ?>" id="financial" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Landlord Income Report</h5>
                            <div>
                                <span class="badge bg-primary">Total: ₱<?= number_format($total_income, 2) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container mb-4">
                                <canvas id="incomeChartFinancial"></canvas>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Landlord</th>
                                            <th>Houses</th>
                                            <th>Tenants</th>
                                            <th>Total Income</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($landlord_income as $landlord): 
                                            $percentage = $total_income > 0 ? round(($landlord['total_income'] / $total_income) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($landlord['first_name'] . ' ' . $landlord['last_name']) ?></td>
                                                <td><?= $landlord['house_count'] ?></td>
                                                <td><?= $landlord['tenant_count'] ?></td>
                                                <td>₱<?= number_format($landlord['total_income'], 2) ?></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar" role="progressbar" 
                                                            style="width: <?= $percentage ?>%" 
                                                            aria-valuenow="<?= $percentage ?>" 
                                                            aria-valuemin="0" 
                                                            aria-valuemax="100">
                                                            <?= $percentage ?>%
                                                        </div>
                                                    </div>
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
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Initialize all charts
    document.addEventListener("DOMContentLoaded", function() {
        // Gender Chart (Summary)
        new Chart(document.getElementById("genderChart").getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($gender_data, 'gender')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($gender_data, 'count')) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Gender Chart (Demographics)
        new Chart(document.getElementById("genderChartDemo").getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($gender_data, 'gender')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($gender_data, 'count')) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Origin Chart (Summary)
        new Chart(document.getElementById("originChart").getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($origin_data, 'origin')) ?>,
                datasets: [{
                    label: 'Number of Tenants',
                    data: <?= json_encode(array_column($origin_data, 'count')) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Origin Chart (Demographics)
        window.originChartDemo = new Chart(document.getElementById("originChartDemo").getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($origin_data, 'origin')) ?>,
                datasets: [{
                    label: 'Number of Tenants',
                    data: <?= json_encode(array_column($origin_data, 'count')) ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // House Chart (Summary)
        new Chart(document.getElementById("houseChart").getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($house_population, 'house_name')) ?>,
                datasets: [{
                    label: 'Number of Tenants',
                    data: <?= json_encode(array_column($house_population, 'tenant_count')) ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // House Chart (Housing)
        new Chart(document.getElementById("houseChartHousing").getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: <?= json_encode(array_column($house_population, 'house_name')) ?>,
                datasets: [{
                    label: 'Number of Tenants',
                    data: <?= json_encode(array_column($house_population, 'tenant_count')) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Income Chart (Summary)
        new Chart(document.getElementById("incomeChart").getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_map(function($l) { 
                    return $l['first_name'] . ' ' . $l['last_name']; 
                }, $landlord_income)) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($landlord_income, 'total_income')) ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.raw.toLocaleString('en-PH');
                            }
                        }
                    }
                }
            }
        });

        // Income Chart (Financial)
        new Chart(document.getElementById("incomeChartFinancial").getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($l) { 
                    return $l['first_name'] . ' ' . $l['last_name']; 
                }, $landlord_income)) ?>,
                datasets: [{
                    label: 'Income (₱)',
                    data: <?= json_encode(array_column($landlord_income, 'total_income')) ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.raw.toLocaleString('en-PH');
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
    });

    // Change chart type
    function changeChartType(chartId, type) {
        if (chartId === 'originChartDemo') {
            window.originChartDemo.destroy();
            window.originChartDemo = new Chart(document.getElementById(chartId).getContext('2d'), {
                type: type,
                data: {
                    labels: <?= json_encode(array_column($origin_data, 'origin')) ?>,
                    datasets: [{
                        label: 'Number of Tenants',
                        data: <?= json_encode(array_column($origin_data, 'count')) ?>,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: type === 'pie' ? 'right' : 'top',
                        }
                    }
                }
            });
        }
    }

    // Export functionality
    function exportReport(format) {
        // Get current active tab
        const activeTab = document.querySelector('#reportTabs .tab-pane.active');
        const reportTitle = activeTab.querySelector('.card-header h5').textContent;
        
        switch(format) {
            case 'pdf':
                alert(`Exporting ${reportTitle} as PDF`);
                // In a real implementation, you would make an AJAX call to a PDF generation script
                break;
            case 'excel':
                alert(`Exporting ${reportTitle} as Excel`);
                // In a real implementation, you would make an AJAX call to an Excel generation script
                break;
            case 'print':
                window.print();
                break;
        }
    }

    // Activate the appropriate tab based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const reportParam = urlParams.get('report');
    if (reportParam) {
        const tab = document.querySelector(`#${reportParam}-tab`);
        if (tab) {
            const tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    }
</script>
</body>
</html>