<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Get all announcements
$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Announcements - Boarding House Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
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
                <h2 class="my-0">Announcements</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                    <i class="bi bi-plus"></i> New Announcement
                </button>
            </div>
            
            <div class="row">
                <?php foreach ($announcements as $announcement): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= htmlspecialchars($announcement['title']) ?></h5>
                            <small class="text-muted"><?= date('M d, Y', strtotime($announcement['created_at'])) ?></small>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                            
                            <?php if ($announcement['target'] != 'all'): ?>
                            <span class="badge bg-info">
                                For: <?= ucfirst($announcement['target']) ?>s
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($announcement['is_pinned']): ?>
                            <span class="badge bg-warning ms-2">
                                <i class="bi bi-pin"></i> Pinned
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-sm btn-outline-warning edit-announcement" 
                                    data-id="<?= $announcement['announcement_id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger ms-2 delete-announcement" 
                                    data-id="<?= $announcement['announcement_id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="announcementForm" method="POST" action="process_announcement.php">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea class="form-control" name="content" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Audience</label>
                        <select class="form-select" name="target">
                            <option value="all">All Users</option>
                            <option value="tenant">Tenants Only</option>
                            <option value="landlord">Landlords Only</option>
                            <option value="admin">Admins Only</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_pinned" id="isPinned">
                        <label class="form-check-label" for="isPinned">Pin this announcement</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Publish Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>    
</body>
</html>