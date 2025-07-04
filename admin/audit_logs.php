<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Get audit logs with user info
$stmt = $pdo->query("
    SELECT al.*, u.first_name, u.last_name, u.user_type
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 200
");
$logs = $stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Audit Logs - Boarding House Management</title>
    <!-- Include CSS and JS files -->
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="content">
        <?php include 'includes/navbar.php'; ?>
        
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="my-0">Audit Logs</h2>
                <div class="btn-group">
                    <button class="btn btn-outline-primary" id="exportLogsBtn">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-danger" id="clearLogsBtn">
                        <i class="bi bi-trash"></i> Clear Logs
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="logFilters">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <input type="text" class="form-control daterange" name="daterange">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action Type</label>
                                <select class="form-select" name="action_type">
                                    <option value="">All Actions</option>
                                    <option value="login">Login</option>
                                    <option value="logout">Logout</option>
                                    <option value="create">Create</option>
                                    <option value="update">Update</option>
                                    <option value="delete">Delete</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">User Type</label>
                                <select class="form-select" name="user_type">
                                    <option value="">All Users</option>
                                    <option value="admin">Admin</option>
                                    <option value="landlord">Landlord</option>
                                    <option value="tenant">Tenant</option>
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
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php if ($log['user_id']): ?>
                                            <?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?>
                                            <small class="d-block text-muted">
                                                <?= ucfirst($log['user_type']) ?>
                                            </small>
                                        <?php else: ?>
                                            System
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $log['action_type'] == 'login' ? 'success' : 
                                            ($log['action_type'] == 'logout' ? 'info' : 
                                            ($log['action_type'] == 'create' ? 'primary' : 
                                            ($log['action_type'] == 'update' ? 'warning' : 'danger')))
                                        ?>">
                                            <?= ucfirst($log['action_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['action']) ?></td>
                                    <td><?= htmlspecialchars($log['description']) ?></td>
                                    <td><?= $log['ip_address'] ?></td>
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

<!-- Clear Logs Confirmation Modal -->
<div class="modal fade" id="confirmClearModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear all audit logs? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmClearBtn">Clear All Logs</button>
            </div>
        </div>
    </div>
</div>

<script>
// Clear logs confirmation
document.getElementById('clearLogsBtn').addEventListener('click', function() {
    var modal = new bootstrap.Modal(document.getElementById('confirmClearModal'));
    modal.show();
});

document.getElementById('confirmClearBtn').addEventListener('click', function() {
    fetch('clear_logs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
});
</script>
</body>
</html>