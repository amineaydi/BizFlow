<?php
// ========================================
// 💼 BIZFLOW - Database Connection
// Multi-tenant + Real-time ready
// ========================================

// Set timezone
date_default_timezone_set('Africa/Tunis');

// Error reporting (DEV mode - disable for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ========================================
// 🔌 DATABASE CREDENTIALS
// ========================================
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'bizflow';
$DB_PORT = getenv('DB_PORT') ?: 3306;

// ========================================
// 🔗 MYSQLI CONNECTION
// ========================================
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// UTF-8 + Timezone
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+01:00'");

// ========================================
// 🔗 PDO CONNECTION (for complex queries)
// ========================================
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+01:00'");
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// ========================================
// 🏪 MULTI-TENANT HELPER FUNCTIONS
// ========================================

/**
 * Get current business ID from session
 * Used in EVERY query to enforce privacy walls
 */
function getBusinessId() {
    if (isset($_SESSION['business_id'])) {
        return intval($_SESSION['business_id']);
    }
    return 0;
}

/**
 * Get current user ID
 */
function getUserId() {
    return intval($_SESSION['user_id'] ?? 0);
}

/**
 * Get current user role
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? 'guest';
}

/**
 * Check if user is super admin (platform owner = YOU)
 */
function isSuperAdmin() {
    return isset($_SESSION['super_admin_id']);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['super_admin_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole($allowedRoles) {
    requireLogin();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($_SESSION['user_role'] ?? '', $allowedRoles)) {
        header("Location: login.php?error=permission");
        exit;
    }
}

/**
 * Require super admin
 */
function requireSuperAdmin() {
    if (!isset($_SESSION['super_admin_id'])) {
        header("Location: super_login.php");
        exit;
    }
}

/**
 * Get business info
 */
function getBusinessInfo() {
    global $conn;
    $bid = getBusinessId();
    if ($bid <= 0) return null;
    
    $stmt = $conn->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Log activity for audit trail
 */
function auditLog($action, $entityType = null, $entityId = null, $description = '', $oldValues = null, $newValues = null) {
    global $conn;
    
    $bid = getBusinessId();
    $uid = getUserId();
    
    if ($bid <= 0) return;
    
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (business_id, user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $oldJson = $oldValues ? json_encode($oldValues) : null;
    $newJson = $newValues ? json_encode($newValues) : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    
    $stmt->bind_param("iisssssssss", 
        $bid, $uid, $action, $entityType, $entityId, 
        $description, $oldJson, $newJson, $ip, $ua
    );
    $stmt->execute();
}

/**
 * Send real-time event via Pusher
 */
function pushRealTime($channel, $event, $data) {
    $key = getenv('PUSHER_KEY') ?: '692a2afe9b9c204f0136';
    $secret = getenv('PUSHER_SECRET') ?: 'b1352a2ee0523940bfd4';
    $app_id = getenv('PUSHER_APP_ID') ?: '2163092';
    $cluster = getenv('PUSHER_CLUSTER') ?: 'eu';
    
    // Add business context to channel
    $bid = getBusinessId();
    if ($bid > 0 && strpos($channel, 'business-') !== 0) {
        $channel = "business-{$bid}-{$channel}";
    }
    
    $body = json_encode([
        'name' => $event,
        'data' => json_encode($data),
        'channels' => [$channel]
    ]);
    
    $path = "/apps/{$app_id}/events";
    $md5_body = md5($body);
    $timestamp = time();
    $auth_string = "POST\n{$path}\nauth_key={$key}&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$md5_body}";
    $auth_signature = hash_hmac('sha256', $auth_string, $secret, false);
    $query = http_build_query([
        'auth_key' => $key,
        'auth_timestamp' => $timestamp,
        'auth_version' => '1.0',
        'body_md5' => $md5_body,
        'auth_signature' => $auth_signature
    ]);
    
    $url = "https://api-{$cluster}.pusher.com{$path}?{$query}";
    
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        @curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * Get current theme for business
 */
function getTheme() {
    global $conn;
    $bid = getBusinessId();
    if ($bid <= 0) return null;
    
    $stmt = $conn->prepare("SELECT * FROM business_themes WHERE business_id = ?");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    $theme = $stmt->get_result()->fetch_assoc();
    
    // Create default if not exists
    if (!$theme) {
        $conn->query("INSERT INTO business_themes (business_id) VALUES ($bid)");
        $stmt->execute();
        $theme = $stmt->get_result()->fetch_assoc();
    }
    
    return $theme;
}

/**
 * Format money based on business currency
 */
function formatMoney($amount, $showSymbol = true) {
    $business = getBusinessInfo();
    $symbol = $business['currency_symbol'] ?? 'DT';
    $formatted = number_format(floatval($amount), 2);
    return $showSymbol ? "$formatted $symbol" : $formatted;
}

/**
 * Generate unique invoice number
 */
function generateInvoiceNumber() {
    global $conn;
    $bid = getBusinessId();
    
    // Get settings
    $stmt = $conn->prepare("SELECT invoice_prefix, next_invoice_number FROM business_settings WHERE business_id = ?");
    $stmt->bind_param("i", $bid);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    
    $prefix = $settings['invoice_prefix'] ?? 'INV';
    $number = $settings['next_invoice_number'] ?? 1;
    
    // Increment for next time
    $conn->query("UPDATE business_settings SET next_invoice_number = next_invoice_number + 1 WHERE business_id = $bid");
    
    return $prefix . '-' . date('Ymd') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
}

/**
 * Check stock availability
 */
function checkStock($productId, $quantity) {
    global $conn;
    $bid = getBusinessId();
    
    $stmt = $conn->prepare("
        SELECT stock_quantity, name 
        FROM products 
        WHERE id = ? AND business_id = ?
    ");
    $stmt->bind_param("ii", $productId, $bid);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if (!$product) return ['ok' => false, 'message' => 'Product not found'];
    
    if ($product['stock_quantity'] < $quantity) {
        return [
            'ok' => false, 
            'message' => "Insufficient stock for {$product['name']} (only {$product['stock_quantity']} left)"
        ];
    }
    
    return ['ok' => true];
}

/**
 * Update stock with movement log
 */
function updateStock($productId, $quantity, $type, $reason = '', $referenceType = null, $referenceId = null) {
    global $conn;
    $bid = getBusinessId();
    $uid = getUserId();
    
    // Get current stock
    $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ? AND business_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $productId, $bid);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc()['stock_quantity'];
    
    // Calculate new stock
    $change = ($type === 'in') ? $quantity : -$quantity;
    $newStock = $current + $change;
    
    if ($newStock < 0) {
        return ['ok' => false, 'message' => 'Insufficient stock'];
    }
    
    // Update product stock
    $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE id = ? AND business_id = ?");
    $stmt->bind_param("iii", $newStock, $productId, $bid);
    $stmt->execute();
    
    // Log movement
    $stmt = $conn->prepare("
        INSERT INTO stock_movements 
        (business_id, product_id, user_id, type, quantity, quantity_before, quantity_after, reason, reference_type, reference_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiisiiissi", 
        $bid, $productId, $uid, $type, 
        $quantity, $current, $newStock, 
        $reason, $referenceType, $referenceId
    );
    $stmt->execute();
    
    // 🔔 Real-time notification
    pushRealTime('stock', 'stock-updated', [
        'product_id' => $productId,
        'old_stock' => $current,
        'new_stock' => $newStock,
        'change' => $change
    ]);
    
    return ['ok' => true, 'new_stock' => $newStock];
}
?>
