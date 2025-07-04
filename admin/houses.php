<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Get all boarding houses with landlord info
$stmt = $pdo->query("
    SELECT 
        bh.house_id, bh.name, bh.address, bh.created_at, bh.is_active, bh.max_tenants,
        u.first_name, u.last_name,
        COUNT(tr.tenant_id) AS tenant_count
    FROM boarding_houses bh
    JOIN users u ON bh.landlord_id = u.user_id
    LEFT JOIN tenant_rooms tr ON bh.house_id = tr.house_id AND tr.status = 'active'
    GROUP BY bh.house_id
    ORDER BY bh.created_at DESC
");


$houses = $stmt->fetchAll();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>    
    <meta charset="UTF-8">
    <title>Boarding Houses - Boarding House Management</title>
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
                <h2 class="my-0">Boarding Houses</h2>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="housesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>House Name</th>
                                    <th>Address</th>
                                    <th>Landlord</th>
                                    <th>Tenants</th>
                                    <th>Status</th>
                                    <th>Added</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($houses as $house): 
                                    $isActive = $house['is_active'] ?? 0;
                                    $max = $house['max_tenants'] ?? 0;
                                ?>
                                <tr>
                                    <td><?= $house['house_id'] ?></td>
                                    <td><?= htmlspecialchars($house['name']) ?></td>
                                    <td><?= htmlspecialchars($house['address']) ?></td>
                                    <td><?= htmlspecialchars($house['first_name'] . ' ' . $house['last_name']) ?></td>
                                    <td><?= $house['tenant_count'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $isActive ? 'success' : 'secondary' ?>">
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($house['created_at'])) ?></td>
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
</body>
</html>