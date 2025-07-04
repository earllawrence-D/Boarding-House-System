<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Group users by type
$userGroups = [
    'landlord' => [],
    'tenant' => [],
];

foreach ($users as $user) {
    if (isset($userGroups[$user['user_type']])) {
        $userGroups[$user['user_type']][] = $user;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - Boarding House Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div id="content">
            <?php include 'includes/navbar.php'; ?>
            <button id="mobileSidebarToggle" class="btn btn-primary d-lg-none">
                <i class="bi bi-list"></i>
            </button>
        
    <div id="content">
        <div class="container-fluid px-4">
            <div class="row mb-4">
                <div class="col-12">
            <h2 class="mb-4">User Management</h2>

<div class="d-flex justify-content-center flex-wrap gap-4">
    <?php foreach ($userGroups as $type => $group): ?>
        <div class="col-lg-4 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-<?= $type == 'landlord' ? 'warning' : 'primary' ?> text-white">
                    <?= ucfirst($type) ?>s
                </div>
                <div class="card-body overflow-auto" style="max-height: 70vh;">
                    <?php if (count($group) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['user_id']) ?></td>
                                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No <?= $type ?>s found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
