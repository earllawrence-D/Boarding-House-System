<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect_based_on_user_type();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // For landlords, check if verified
        if ($user['user_type'] == 'landlord' && !$user['is_verified']) {
            $error = "Your landlord account is not yet verified. Please complete verification first.";
        } else {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Modified check for tenant house status
            if ($user['user_type'] == 'tenant') {
                // Check if tenant has joined a house
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_rooms WHERE tenant_id = ? AND status = 'active'");
                $stmt->execute([$user['user_id']]);
                
                if ($stmt->fetchColumn() == 0) {
                    $_SESSION['needs_house'] = true;
                }
            }
            
            redirect_based_on_user_type();
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Boarding House - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="form-header">
                <div class="logo-text">
                    <span class="my">My</span>
                    <span class="boarding">Boarding</span>
                    <span class="house">House</span>
                </div>
                <h2>Welcome Back!</h2>
            </div>
            
            <p class="subtitle">Sign in to manage your boarding house</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="form-divider">or</div>
            
            <div class="text-center">
                <p>Don't have an account? <a href="register.php" class="form-link">Sign up now</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>