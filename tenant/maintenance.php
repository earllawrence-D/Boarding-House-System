<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'tenant') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? null;
$request_id = $_GET['id'] ?? null;

// Get tenant's current room
$room = get_tenant_room($pdo, $user_id);
if (!$room) {
    header("Location: ../../join_house.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_request'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $priority = $_POST['priority'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO maintenance_requests 
                                  (room_id, reported_by, title, description, priority) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$room['room_id'], $user_id, $title, $description, $priority]);
            
            $_SESSION['success'] = "Maintenance request submitted successfully!";
            header("Location: maintenance.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error submitting request: " . $e->getMessage();
        }
    }
}

// Get all maintenance requests for this tenant
$stmt = $pdo->prepare("SELECT mr.*, r.room_number, 
                      a.first_name as assigned_first, a.last_name as assigned_last
                      FROM maintenance_requests mr
                      JOIN rooms r ON mr.room_id = r.room_id
                      LEFT JOIN users a ON mr.assigned_to = a.user_id
                      WHERE mr.reported_by = ?
                      ORDER BY mr.created_at DESC");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll();

// Get request details for viewing
$request_details = null;
if ($request_id) {
    $stmt = $pdo->prepare("SELECT mr.*, r.room_number, 
                          a.first_name as assigned_first, a.last_name as assigned_last
                          FROM maintenance_requests mr
                          JOIN rooms r ON mr.room_id = r.room_id
                          LEFT JOIN users a ON mr.assigned_to = a.user_id
                          WHERE mr.request_id = ? AND mr.reported_by = ?");
    $stmt->execute([$request_id, $user_id]);
    $request_details = $stmt->fetch();
}

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
    <title>Maintenance Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                        <p class="text-muted">Room <?php echo htmlspecialchars($room['room_number']); ?></p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($action == 'create'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Create Maintenance Request</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority *</label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="maintenance.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" name="create_request" class="btn btn-primary">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($action == 'view' && $request_details): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Request Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h5><?php echo htmlspecialchars($request_details['title']); ?></h5>
                                <div class="d-flex justify-content-between text-muted mb-2">
                                    <small>Room <?php echo htmlspecialchars($request_details['room_number']); ?></small>
                                    <small><?php echo date('M j, Y h:i A', strtotime($request_details['created_at'])); ?></small>
                                </div>
                                <div class="mb-3">
                                    <?php 
                                    $priority_class = '';
                                    if ($request_details['priority'] == 'high') $priority_class = 'bg-danger';
                                    elseif ($request_details['priority'] == 'medium') $priority_class = 'bg-warning text-dark';
                                    else $priority_class = 'bg-info';
                                    ?>
                                    <span class="badge <?php echo $priority_class; ?> me-2">
                                        <?php echo ucfirst($request_details['priority']); ?>
                                    </span>
                                    <?php 
                                    $status_class = '';
                                    if ($request_details['status'] == 'pending') $status_class = 'bg-warning text-dark';
                                    elseif ($request_details['status'] == 'in_progress') $status_class = 'bg-primary';
                                    else $status_class = 'bg-success';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $request_details['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mb-4">
                                <h6>Description</h6>
                                <p><?php echo nl2br(htmlspecialchars($request_details['description'])); ?></p>
                            </div>
                            <div class="mb-4">
                                <h6>Assigned To</h6>
                                <p>
                                    <?php if ($request_details['assigned_first']): ?>
                                        <?php echo htmlspecialchars($request_details['assigned_first'] . ' ' . $request_details['assigned_last']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned yet</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($request_details['status'] == 'completed' && $request_details['completed_at']): ?>
                                <div class="mb-4">
                                    <h6>Completed On</h6>
                                    <p><?php echo date('M j, Y h:i A', strtotime($request_details['completed_at'])); ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="maintenance.php" class="btn btn-primary">Back to List</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">My Requests</h5>
                            <a href="maintenance.php?action=create" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus"></i> New Request
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($requests)): ?>
                                <div class="alert alert-info">You haven't submitted any maintenance requests yet.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['title']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $priority_class = '';
                                                        if ($request['priority'] == 'high') $priority_class = 'bg-danger';
                                                        elseif ($request['priority'] == 'medium') $priority_class = 'bg-warning text-dark';
                                                        else $priority_class = 'bg-info';
                                                        ?>
                                                        <span class="badge <?php echo $priority_class; ?>">
                                                            <?php echo ucfirst($request['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $status_class = '';
                                                        if ($request['status'] == 'pending') $status_class = 'bg-warning text-dark';
                                                        elseif ($request['status'] == 'in_progress') $status_class = 'bg-primary';
                                                        else $status_class = 'bg-success';
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="maintenance.php?action=view&id=<?php echo $request['request_id']; ?>" class="btn btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View
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