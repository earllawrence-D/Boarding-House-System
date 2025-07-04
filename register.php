<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect_based_on_user_type();
}

$error = '';
$success = '';
$show_user_type_selection = true;
$user_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['user_type'])) {
        // First step: user selected their type
        $user_type = $_POST['user_type'];
        $show_user_type_selection = false;
    } else {
        // Second step: form submission
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $user_type = $_POST['final_user_type'];
        
        // Check if username exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists.";
        } else {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $is_verified = $user_type == 'tenant' ? 1 : 0;
                
                try {
                    $pdo->beginTransaction();
                    
                    // Insert user
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, phone, user_type, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $email, $first_name, $last_name, $phone, $user_type, $is_verified]);
                    $user_id = $pdo->lastInsertId();
                    
                    if ($user_type == 'landlord') {
                        // Set session variables for immediate login
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['user_type'] = $user_type;
                        $_SESSION['is_verified'] = $is_verified;
                        $_SESSION['needs_verification'] = true; // Flag that verification is needed
                        $_SESSION['verification_redirect_time'] = time() + 30; // 30 seconds from now
                        
                        $pdo->commit();
                        
                        // Redirect to landlord dashboard
                        header("Location: landlord/dashboard.php");
                        exit();
                    
                    } elseif ($user_type == 'tenant') {
                        // Insert tenant info
                        $parent_contact = trim($_POST['parent_contact']);
                        $emergency_contact = trim($_POST['emergency_contact']);
                        
                        $stmt = $pdo->prepare("INSERT INTO tenant_info (user_id, parent_contact, emergency_contact) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $parent_contact, $emergency_contact]);
                        
                        $pdo->commit();
                        
                        $success = "Account created successfully! You can now login.";
                        $show_user_type_selection = true;
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "An error occurred: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Boarding House Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <h2 class="text-center mb-4">Create an Account</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php else: ?>
            
                <?php if ($show_user_type_selection): ?>
                    <form method="POST">
                        <div class="mb-4 text-center">
                            <p>Are you a landlord or a tenant?</p>
                            <div class="d-flex justify-content-center gap-3">
                                <button type="submit" name="user_type" value="landlord" class="btn btn-outline-primary btn-lg">Landlord</button>
                                <button type="submit" name="user_type" value="tenant" class="btn btn-outline-success btn-lg">Tenant</button>
                            </div>
                        </div>
                        <div class="text-center">
                            <a href="index.php" class="btn btn-link">Back to Login</a>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="final_user_type" value="<?php echo $user_type; ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        
                        <?php if ($user_type == 'tenant'): ?>
                            <div class="mb-3">
                                <label for="parent_contact" class="form-label">Parent's Contact Number *</label>
                                <input type="tel" class="form-control" id="parent_contact" name="parent_contact" required>
                            </div>
                            <div class="mb-3">
                                <label for="emergency_contact" class="form-label">Emergency Contact Number *</label>
                                <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" required>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                        <div class="mt-3 text-center">
                            <a href="register.php" class="btn btn-link">Back</a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>