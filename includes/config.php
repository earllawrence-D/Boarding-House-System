<?php
// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Enable if using HTTPS
ini_set('session.use_strict_mode', 1);

// Timezone
date_default_timezone_set('Asia/Manila');

// Upload directories
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('LANDLORD_DOCS_DIR', UPLOAD_DIR . 'landlord_docs/');
define('PAYMENT_PROOFS_DIR', UPLOAD_DIR . 'payment_proofs/');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!file_exists(LANDLORD_DOCS_DIR)) mkdir(LANDLORD_DOCS_DIR, 0755, true);
if (!file_exists(PAYMENT_PROOFS_DIR)) mkdir(PAYMENT_PROOFS_DIR, 0755, true);

// Other configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
?>