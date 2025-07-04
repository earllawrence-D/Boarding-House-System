<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!is_logged_in() || $_SESSION['user_type'] != 'landlord') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if already verified
$stmt = $pdo->prepare("SELECT is_verified FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['is_verified']) {
    header("Location: landlord/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $permit_number = trim($_POST['permit_number']);
    
    // Handle file uploads
    $permit_image = upload_file('permit_image', 'landlord_docs');
    $id_image = upload_file('id_image', 'landlord_docs');
    
    if ($permit_image && $id_image) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert verification documents
            $stmt = $pdo->prepare("INSERT INTO landlord_verification (user_id, permit_number, permit_image, id_image, verified_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $permit_number, $permit_image, $id_image]);
            
            // Mark user as verified
            $stmt = $pdo->prepare("UPDATE users SET is_verified = TRUE WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            
            // Clear any verification flags
            unset($_SESSION['needs_verification']);
            unset($_SESSION['verification_redirect_time']);
            
            // Redirect to index.php which will now recognize the user as verified
            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "An error occurred: " . $e->getMessage();
        }
    } else {
        $error = "Please upload both documents.";
    }
}

function upload_file($field_name, $target_dir) {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] != UPLOAD_ERR_OK) {
        return false;
    }
    
    $target_dir = "../uploads/$target_dir/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($file_ext, $allowed_ext)) {
        return false;
    }
    
    $file_name = uniqid() . '.' . $file_ext;
    $target_file = $target_dir . $file_name;
    
    if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $target_file)) {
        return $file_name;
    }
    
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <h2 class="text-center mb-4">Landlord Verification</h2>
            <p class="text-center mb-4">Please provide your boarding house permit and valid ID for verification.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="permit_number" class="form-label">Boarding House Permit Number *</label>
                    <input type="text" class="form-control" id="permit_number" name="permit_number" required>
                </div>
                
                <div class="mb-3">
                    <label for="permit_image" class="form-label">Upload Permit Document *</label>
                    <input type="file" class="form-control" id="permit_image" name="permit_image" accept="image/*,.pdf" required>
                    <div class="form-text">Upload a clear photo or scan of your boarding house permit (JPG, PNG, or PDF)</div>
                </div>
                
                <div class="mb-3">
                    <label for="id_image" class="form-label">Upload Valid ID *</label>
                    <input type="file" class="form-control" id="id_image" name="id_image" accept="image/*,.pdf" required>
                    <div class="form-text">Upload a clear photo or scan of your government-issued ID (JPG, PNG, or PDF)</div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Submit for Verification</button>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>