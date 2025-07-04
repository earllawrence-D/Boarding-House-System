<?php
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect_based_on_user_type() {
    if (isset($_SESSION['user_type'])) {
        if ($_SESSION['user_type'] == 'landlord') {
            // Check if verified
            global $pdo;
            $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user['is_verified']) {
                header("Location: landlord/dashboard.php");
            } else {
                header("Location: verify_landlord.php");
            }
        } elseif ($_SESSION['user_type'] == 'tenant') {
            if (isset($_SESSION['needs_house'])) {
                header("Location: join_house.php");
            } else {
                header("Location: tenant/dashboard.php");
            }
        }
    }
    exit();
}

function get_user_info($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function get_landlord_houses($pdo, $landlord_id) {
    $stmt = $pdo->prepare("SELECT * FROM boarding_houses WHERE landlord_id = ?");
    $stmt->execute([$landlord_id]);
    return $stmt->fetchAll();
}

function get_tenant_house($pdo, $tenant_id) {
    $stmt = $pdo->prepare("SELECT bh.* FROM boarding_houses bh
                          JOIN rooms r ON bh.house_id = r.house_id
                          JOIN tenant_rooms tr ON r.room_id = tr.room_id
                          WHERE tr.tenant_id = ? AND tr.status = 'active'");
    $stmt->execute([$tenant_id]);
    return $stmt->fetch();
}

function get_tenant_room($pdo, $tenant_id) {
    $stmt = $pdo->prepare("SELECT r.* FROM rooms r
                          JOIN tenant_rooms tr ON r.room_id = tr.room_id
                          WHERE tr.tenant_id = ? AND tr.status = 'active'");
    $stmt->execute([$tenant_id]);
    return $stmt->fetch();
}

function generate_join_code() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function generate_unique_join_code($pdo) {
    do {
        $code = generate_join_code(); // This calls your existing function above
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM boarding_houses WHERE join_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() > 0);
    
    return $code;
}

function format_date($date) {
    return date('M j, Y', strtotime($date));
}

function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

// Add these new functions for managing join requests
function get_pending_join_requests($pdo, $landlord_id) {
    $stmt = $pdo->prepare("SELECT tr.*, u.first_name, u.last_name, u.email, bh.name as house_name
                          FROM tenant_rooms tr
                          JOIN users u ON tr.tenant_id = u.user_id
                          JOIN boarding_houses bh ON tr.house_id = bh.house_id
                          WHERE bh.landlord_id = ? AND tr.status = 'pending'");
    $stmt->execute([$landlord_id]);
    return $stmt->fetchAll();
}

function approve_tenant_request($pdo, $request_id, $room_id) {
    // Get tenant_room row
    $stmt = $pdo->prepare("SELECT tenant_id FROM tenant_rooms WHERE id = ?");
    $stmt->execute([$request_id]);
    $tenant = $stmt->fetch();

    if ($tenant) {
        // Update tenant_rooms status and assign the room
        $stmt = $pdo->prepare("UPDATE tenant_rooms SET status = 'active', room_id = ? WHERE id = ?");
        $stmt->execute([$room_id, $request_id]);

        // Mark the room as no longer available
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?");
        $stmt->execute([$room_id]);
    }
}

function reject_tenant_request($pdo, $request_id) {
    $stmt = $pdo->prepare("DELETE FROM tenant_rooms WHERE id = ?");
    return $stmt->execute([$request_id]);
}
?>