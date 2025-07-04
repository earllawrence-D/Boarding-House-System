<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Get current settings
$settings = $pdo->query("SELECT * FROM system_settings")->fetch();

// Handle system deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_system'])) {
    // Verify password for critical action
    if (empty($_POST['confirm_password'])) {
        $_SESSION['error'] = "Please enter your password to confirm deletion";
    } else {
        // Verify admin password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($_POST['confirm_password'], $user['password'])) {
            // Password verified - proceed with deletion
            
            // 1. First, backup the database (you should implement this)
            // backup_database();
            
            // 2. Then delete all data (this is just an example - customize for your needs)
            $tables = ['boarding_houses', 'payments', 'tenant_rooms', 'users', 'system_settings'];
            
            try {
                $pdo->beginTransaction();
                
                // Disable foreign key checks temporarily
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                foreach ($tables as $table) {
                    $pdo->exec("TRUNCATE TABLE $table");
                }
                
                // Re-enable foreign key checks
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                $pdo->commit();
                
                // Logout and redirect
                session_destroy();
                header("Location: ../../index.php?system_reset=1");
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "System deletion failed: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Incorrect password";
        }
    }
}

// Handle regular settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['delete_system'])) {
    // Process form submission and update settings
    // ...
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>System Settings - Boarding House Management</title>
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
        
        <div class="container-fluid px-4">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <h2 class="my-4">System Settings</h2>
            
            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" id="settingsForm">
                        <!-- Your existing settings form fields -->
                        <!-- ... -->
                    </form>
                </div>
            </div>
            
            <!-- Dangerous Actions Card -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle-fill"></i> Dangerous Actions
                </div>
                <div class="card-body">
                    <h5 class="card-title text-danger">Delete All System Data</h5>
                    <p class="card-text">
                        This will permanently delete ALL data in the system including:
                    </p>
                    <ul>
                        <li>All boarding house records</li>
                        <li>All tenant information</li>
                        <li>All payment history</li>
                        <li>All user accounts</li>
                    </ul>
                    <p class="text-danger fw-bold">This action cannot be undone!</p>
                    
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash-fill"></i> Delete All Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm System Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You are about to <strong>permanently delete all data</strong> in the system.</p>
                <p>To confirm this action, please:</p>
                <ol>
                    <li>Type <strong>DELETE ALL</strong> in the box below</li>
                    <li>Enter your account password</li>
                </ol>
                
                <form method="POST" id="deleteForm">
                    <div class="mb-3">
                        <label for="confirmText" class="form-label">Type DELETE ALL</label>
                        <input type="text" class="form-control" id="confirmText" name="confirm_text" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Your Password</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteForm" name="delete_system" class="btn btn-danger">
                    <i class="bi bi-trash-fill"></i> Permanently Delete All Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Required scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Additional validation for delete form
document.getElementById('deleteForm').addEventListener('submit', function(e) {
    const confirmText = document.getElementById('confirmText').value;
    if (confirmText !== 'DELETE ALL') {
        e.preventDefault();
        alert('You must type "DELETE ALL" exactly to confirm deletion');
    }
});

</script>
</body>
</html>