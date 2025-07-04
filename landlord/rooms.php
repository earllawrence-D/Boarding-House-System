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
$house_id = $_GET['house_id'] ?? null;
$room_id = $_GET['room_id'] ?? null;

// Get all houses owned by landlord
$houses = get_landlord_houses($pdo, $user_id);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_house'])) {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $join_code = generate_join_code();
        
        try {
            $stmt = $pdo->prepare("INSERT INTO boarding_houses (landlord_id, name, address, join_code) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $address, $join_code]);
            
            $_SESSION['success'] = "Boarding house added successfully! Join code: $join_code";
            header("Location: rooms.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding boarding house: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_room'])) {
        $room_number = trim($_POST['room_number']);
        $capacity = (int)$_POST['capacity'];
        $price = (float)$_POST['price'];
        $description = trim($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO rooms (house_id, room_number, capacity, price, description) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$house_id, $room_number, $capacity, $price, $description]);
            
            $_SESSION['success'] = "Room added successfully!";
            header("Location: rooms.php?house_id=$house_id");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding room: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_room'])) {
        $room_number = trim($_POST['room_number']);
        $capacity = (int)$_POST['capacity'];
        $price = (float)$_POST['price'];
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE rooms SET 
                                  room_number = ?,
                                  capacity = ?,
                                  price = ?,
                                  description = ?,
                                  status = ?
                                  WHERE room_id = ?");
            $stmt->execute([$room_number, $capacity, $price, $description, $status, $room_id]);
            
            $_SESSION['success'] = "Room updated successfully!";
            header("Location: rooms.php?house_id=$house_id");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating room: " . $e->getMessage();
        }
    }
}

// Get rooms for selected house
$rooms = [];
if ($house_id) {
    $stmt = $pdo->prepare("SELECT r.*, 
                          COUNT(tr.tenant_id) as current_occupancy
                          FROM rooms r
                          LEFT JOIN tenant_rooms tr ON r.room_id = tr.room_id AND tr.status = 'active'
                          WHERE r.house_id = ?
                          GROUP BY r.room_id
                          ORDER BY r.room_number");
    $stmt->execute([$house_id]);
    $rooms = $stmt->fetchAll();
}

// Get room details for editing
$room_details = null;
if ($room_id) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $room_details = $stmt->fetch();
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
    <title>Room Management</title>
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
                        <h2>Room Management</h2>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($action == 'add_house'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Add New Boarding House</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="name" class="form-label">House Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address *</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="rooms.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" name="add_house" class="btn btn-primary">Add House</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($action == 'add' && $house_id): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Add New Room</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="room_number" class="form-label">Room Number *</label>
                                        <input type="text" class="form-control" id="room_number" name="room_number" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="capacity" class="form-label">Capacity *</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity" min="1" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="price" class="form-label">Monthly Rent (₱) *</label>
                                        <input type="number" step="0.01" class="form-control" id="price" name="price" min="0" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="available">Available</option>
                                            <option value="maintenance">Under Maintenance</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="rooms.php?house_id=<?php echo $house_id; ?>" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($action == 'edit' && $room_id && $room_details): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Edit Room</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="room_number" class="form-label">Room Number *</label>
                                        <input type="text" class="form-control" id="room_number" name="room_number" value="<?php echo htmlspecialchars($room_details['room_number']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="capacity" class="form-label">Capacity *</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity" min="1" value="<?php echo $room_details['capacity']; ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="price" class="form-label">Monthly Rent (₱) *</label>
                                        <input type="number" step="0.01" class="form-control" id="price" name="price" min="0" value="<?php echo $room_details['price']; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="available" <?php echo $room_details['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="occupied" <?php echo $room_details['status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                            <option value="maintenance" <?php echo $room_details['status'] == 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($room_details['description']); ?></textarea>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="rooms.php?house_id=<?php echo $house_id; ?>" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" name="update_room" class="btn btn-primary">Update Room</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
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
                                                <?php echo htmlspecialchars($house['name']); ?> (Code: <?php echo $house['join_code']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <a href="rooms.php?action=add_house" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-plus"></i> Add New House
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($house_id): ?>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Rooms</h5>
                                <a href="rooms.php?action=add&house_id=<?php echo $house_id; ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus"></i> Add Room
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($rooms)): ?>
                                    <div class="alert alert-info">No rooms found for this boarding house.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Room No.</th>
                                                    <th>Capacity</th>
                                                    <th>Occupancy</th>
                                                    <th>Rent (₱)</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rooms as $room): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                                        <td><?php echo $room['capacity']; ?></td>
                                                        <td><?php echo $room['current_occupancy'] . '/' . $room['capacity']; ?></td>
                                                        <td>₱<?php echo number_format($room['price'], 2); ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            if ($room['status'] == 'available') $status_class = 'bg-success';
                                                            elseif ($room['status'] == 'occupied') $status_class = 'bg-primary';
                                                            else $status_class = 'bg-warning text-dark';
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($room['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="rooms.php?action=edit&house_id=<?php echo $house_id; ?>&room_id=<?php echo $room['room_id']; ?>" class="btn btn-outline-primary">
                                                                    <i class="bi bi-pencil"></i> Edit
                                                                </a>
                                                                <a href="room_details.php?id=<?php echo $room['room_id']; ?>" class="btn btn-outline-info">
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>