<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'landlord') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? null;
$request_id = $_GET['id'] ?? null;

// Handle status filter
$status_filter = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'pending', 'in_progress', 'completed'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $request_id) {
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['status'] ?? null;
        $assigned_to = $_POST['assigned_to'] ?? null;
        $completion_note = $_POST['completion_note'] ?? null;
        
        try {
            // Prepare the base update query
            $update_fields = [
                'status' => $new_status,
                'assigned_to' => $assigned_to ?: null
            ];
            
            // Set completed_at if status is being changed to completed
            if ($new_status == 'completed') {
                $update_fields['completed_at'] = date('Y-m-d H:i:s');
            } elseif ($new_status != 'completed') {
                $update_fields['completed_at'] = null;
            }
            
            // Build the update query dynamically
            $set_clause = [];
            $params = [];
            foreach ($update_fields as $field => $value) {
                $set_clause[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $request_id;
            
            $sql = "UPDATE maintenance_requests SET " . implode(', ', $set_clause) . " WHERE request_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['success'] = "Request status updated successfully!";
            header("Location: maintenance.php?action=view&id=$request_id&status=$status_filter");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating request: " . $e->getMessage();
        }
    }
}

// Get all maintenance requests with filtering
$sql = "SELECT 
            mr.request_id,
            mr.room_id,
            mr.reported_by,
            mr.assigned_to,
            mr.title,
            mr.description,
            mr.status,
            mr.priority,
            mr.created_at,
            mr.completed_at,
            r.room_number,
            u.first_name as reporter_first, 
            u.last_name as reporter_last,
            a.first_name as assigned_first, 
            a.last_name as assigned_last
        FROM maintenance_requests mr
        JOIN rooms r ON mr.room_id = r.room_id
        JOIN users u ON mr.reported_by = u.user_id
        LEFT JOIN users a ON mr.assigned_to = a.user_id
        WHERE r.property_id IN (SELECT property_id FROM properties WHERE landlord_id = ?)";

if ($status_filter != 'all') {
    $sql .= " AND mr.status = ?";
    $params = [$user_id, $status_filter];
} else {
    $params = [$user_id];
}

$sql .= " ORDER BY 
        CASE WHEN mr.status = 'pending' THEN 1
             WHEN mr.status = 'in_progress' THEN 2
             ELSE 3 END,
        CASE WHEN mr.priority = 'high' THEN 1
             WHEN mr.priority = 'medium' THEN 2
             ELSE 3 END,
        mr.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Get request details for viewing
$request_details = null;
if ($request_id) {
    $stmt = $pdo->prepare("SELECT 
                            mr.*,
                            r.room_number,
                            u.first_name as reporter_first, 
                            u.last_name as reporter_last,
                            a.first_name as assigned_first, 
                            a.last_name as assigned_last
                          FROM maintenance_requests mr
                          JOIN rooms r ON mr.room_id = r.room_id
                          JOIN users u ON mr.reported_by = u.user_id
                          LEFT JOIN users a ON mr.assigned_to = a.user_id
                          WHERE mr.request_id = ? 
                          AND r.property_id IN (SELECT property_id FROM properties WHERE landlord_id = ?)");
    $stmt->execute([$request_id, $user_id]);
    $request_details = $stmt->fetch();
}

// Get staff that can be assigned (property managers, maintenance staff)
$assignable_staff = [];
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name 
                      FROM users 
                      WHERE user_type IN ('property_manager', 'maintenance') 
                      AND property_id IN (SELECT property_id FROM properties WHERE landlord_id = ?)");
$stmt->execute([$user_id]);
$assignable_staff = $stmt->fetchAll();

// Display success/error messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests - Landlord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        .priority-high {
            background-color: #dc3545;
            color: white;
        }
        .priority-medium {
            background-color: #ffc107;
            color: #212529;
        }
        .priority-low {
            background-color: #17a2b8;
            color: white;
        }
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .status-in_progress {
            background-color: #007bff;
            color: white;
        }
        .status-completed {
            background-color: #28a745;
            color: white;
        }
        .completion-details {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div id="content">
            <?php include 'includes/navbar.php'; ?>

            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2>Maintenance Requests</h2>
                        <p class="text-muted">View and manage tenant maintenance requests</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($action == 'view' && $request_details): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Request Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h4><?php echo htmlspecialchars($request_details['title']); ?></h4>
                                <div class="d-flex justify-content-between text-muted mb-3">
                                    <div>
                                        <span class="me-3">
                                            <i class="bi bi-door-closed"></i> Room <?php echo htmlspecialchars($request_details['room_number']); ?>
                                        </span>
                                        <span>
                                            <i class="bi bi-person"></i> Reported by <?php echo htmlspecialchars($request_details['reporter_first'] . ' ' . $request_details['reporter_last']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <i class="bi bi-calendar"></i> <?php echo date('M j, Y h:i A', strtotime($request_details['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <span class="badge status-badge priority-<?php echo $request_details['priority']; ?> me-2">
                                        <?php echo ucfirst($request_details['priority']); ?> Priority
                                    </span>
                                    <span class="badge status-badge status-<?php echo $request_details['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $request_details['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5>Description</h5>
                                <div class="card card-body bg-light">
                                    <?php echo nl2br(htmlspecialchars($request_details['description'])); ?>
                                </div>
                            </div>
                            
                            <?php if ($request_details['status'] == 'completed' && $request_details['completed_at']): ?>
                                <div class="completion-details">
                                    <h5><i class="bi bi-check-circle"></i> Completion Details</h5>
                                    <p class="mb-1"><strong>Completed on:</strong> <?php echo date('M j, Y h:i A', strtotime($request_details['completed_at'])); ?></p>
                                    <?php if ($request_details['assigned_first']): ?>
                                        <p><strong>Completed by:</strong> <?php echo htmlspecialchars($request_details['assigned_first'] . ' ' . $request_details['assigned_last']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" class="mt-4">
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <h5>Update Status</h5>
                                        <div class="mb-3">
                                            <select class="form-select" name="status" required>
                                                <option value="pending" <?php echo $request_details['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in_progress" <?php echo $request_details['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="completed" <?php echo $request_details['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h5>Assign To</h5>
                                        <div class="mb-3">
                                            <select class="form-select" name="assigned_to">
                                                <option value="">-- Unassigned --</option>
                                                <?php foreach ($assignable_staff as $staff): ?>
                                                    <option value="<?php echo $staff['user_id']; ?>" 
                                                        <?php echo $request_details['assigned_to'] == $staff['user_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="maintenance.php?status=<?php echo $status_filter; ?>" class="btn btn-secondary me-md-2">
                                        <i class="bi bi-arrow-left"></i> Back to List
                                    </a>
                                    <button type="submit" name="update_status" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Update Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Requests</h5>
                            <div class="d-flex align-items-center">
                                <form method="GET" class="me-2">
                                    <div class="input-group">
                                        <span class="input-group-text">Filter:</span>
                                        <select name="status" class="form-select" onchange="this.form.submit()">
                                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Requests</option>
                                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($requests)): ?>
                                <div class="alert alert-info">No maintenance requests found matching your criteria.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Title</th>
                                                <th>Room</th>
                                                <th>Tenant</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Assigned To</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($request['title']); ?></strong>
                                                        <?php if ($request['status'] == 'pending' && strtotime($request['created_at']) < strtotime('-3 days')): ?>
                                                            <span class="badge bg-danger ms-2">Overdue</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($request['room_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['reporter_first'] . ' ' . $request['reporter_last']); ?></td>
                                                    <td>
                                                        <span class="badge status-badge priority-<?php echo $request['priority']; ?>">
                                                            <?php echo ucfirst($request['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge status-badge status-<?php echo $request['status']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                                        <?php if ($request['status'] == 'completed'): ?>
                                                            <br><small class="text-muted">Completed: <?php echo date('M j', strtotime($request['completed_at'])); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($request['assigned_first']): ?>
                                                            <?php echo htmlspecialchars($request['assigned_first'] . ' ' . $request['assigned_last']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Unassigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="maintenance.php?action=view&id=<?php echo $request['request_id']; ?>&status=<?php echo $status_filter; ?>" 
                                                               class="btn btn-outline-primary" title="View Details">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </div>
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