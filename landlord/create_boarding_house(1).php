<?php
session_start();
require_once('../includes/db_connect.php');
require_once('../includes/functions.php');

if (!is_logged_in() || $_SESSION['user_type'] != 'landlord') {
    header("Location: ../../index.php");
    exit();
}

// Check if landlord is verified
$user_id = $_SESSION['user_id'];
$user = get_user_info($pdo, $user_id);
$is_verified = $user['is_verified'];

// Redirect to verification if not verified
if (!$is_verified) {
    if (!isset($_SESSION['verification_redirect_time'])) {
        $_SESSION['verification_redirect_time'] = time() + 10;
        $_SESSION['needs_verification'] = true;
    }
    
    if (time() >= $_SESSION['verification_redirect_time']) {
        unset($_SESSION['needs_verification']);
        unset($_SESSION['verification_redirect_time']);
        header("Location: ../verify_landlord.php");
        exit();
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $description = trim($_POST['description']);
    
    // Validate inputs
    if (empty($name) || empty($address)) {
        $error = "Name and address are required";
    } else {
        try {
            // Generate unique join code
            $join_code = generate_unique_join_code($pdo);
            
            // Create boarding house
            $stmt = $pdo->prepare("INSERT INTO boarding_houses 
                                 (landlord_id, name, address, description, join_code, created_at) 
                                 VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_SESSION['user_id'],
                $name,
                $address,
                $description,
                $join_code
            ]);
            
            $house_id = $pdo->lastInsertId();
            $success = "Boarding house created successfully! Share this join code with your tenants: <strong>$join_code</strong>";
            
            // Reset form if needed
            $_POST = array();
        } catch (PDOException $e) {
            $error = "Error creating boarding house: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Boarding House</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <?php if (!$is_verified && isset($_SESSION['needs_verification']) && $_SESSION['needs_verification']): ?>
    <meta http-equiv="refresh" content="10;url=../verify_landlord.php">
    <?php endif; ?>
</head>
<body>
<<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div id="content">
        <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <?php if (!$is_verified && isset($_SESSION['needs_verification']) && $_SESSION['needs_verification']): ?>
        <div class="alert alert-warning animate-pop">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-shield-exclamation fs-4 me-3"></i>
                    <div>
                        <h5 class="mb-1">Verification Required</h5>
                        <p class="mb-0">You need to verify your landlord account to access all features.</p>
                    </div>
                </div>
                <div class="text-end">
                    <p class="mb-1">Redirecting in <span id="countdown" class="fw-bold">10</span> seconds...</p>
                    <a href="../verify_landlord.php" class="btn btn-sm btn-warning">
                        <i class="bi bi-shield-check me-1"></i> Verify Now
                    </a>
                </div>
            </div>
        </div>
        <script>
            // Countdown timer
            let seconds = 10;
            const countdownElement = document.getElementById('countdown');
            
            const countdownInterval = setInterval(() => {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = "../verify_landlord.php";
                }
            }, 1000);
        </script>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">Create New Boarding House</h2>
                <p class="text-muted">Set up a new boarding house property to start managing rooms and tenants.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                                    <div>
                                        <?php echo $success; ?>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?php echo $join_code; ?>')">
                                        <i class="bi bi-clipboard me-1"></i> Copy Join Code
                                    </button>
                                    <a href="rooms.php?house_id=<?php echo $house_id; ?>" class="btn btn-sm btn-primary ms-2">
                                        <i class="bi bi-door-open me-1"></i> Manage Rooms
                                    </a>
                                    <a href="dashboard.php" class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="bi bi-speedometer2 me-1"></i> Return to Dashboard
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" <?php echo $success ? 'style="display:none;"' : ''; ?>>
                            <div class="mb-3">
                                <label for="name" class="form-label">Boarding House Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $_POST['name'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Full Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?php echo $_POST['address'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description (Optional)</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $_POST['description'] ?? ''; ?></textarea>
                                <small class="text-muted">You can describe amenities, house rules, or other details.</small>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-house-add me-1"></i> Create Boarding House
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Tips</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Boarding House Name:</strong> Choose a clear, recognizable name for your property.
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Join Code:</strong> After creation, share the unique join code with tenants so they can register.
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Description:</strong> Help tenants understand your property by including key details.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Join code copied to clipboard: ' + text);
        }, function() {
            alert('Failed to copy join code');
        });
    }

    // Toggle sidebar
    document.querySelector('.sidebar-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('sidebar-collapsed');
        document.querySelector('.container-fluid').classList.toggle('main-content-collapsed');
    });
</script>
</body>
</html>