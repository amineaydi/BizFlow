<?php
// db.php — BizFlow Database Connection
date_default_timezone_set('Africa/Tunis');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'bizflow';
$DB_PORT = getenv('DB_PORT') ?: 3306;

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+01:00'");

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
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
// 🏪 HELPER FUNCTIONS
// ========================================

function getBusinessId() {
    return intval($_SESSION['business_id'] ?? 0);
}

function getUserId() {
    return intval($_SESSION['user_id'] ?? 0);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? 'guest';
}

function isSuperAdmin() {
    return isset($_SESSION['super_admin_id']);
}

function requireLogin() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['super_admin_id'])) {
        header("Location: login.php");
        exit;
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!is_array($allowedRoles)) $allowedRoles = [$allowedRoles];
    if (!in_array($_SESSION['user_role'] ?? '', $allowedRoles)) {
        header("Location: login.php?error=permission");
        exit;
    }
}

function requireSuperAdmin() {
    if (!isset($_SESSION['super_admin_id'])) {
        header("Location: super_login.php");
        exit;
    }
}

function getBusinessInfo() {
    global $conn;
    $bid = getBusinessId();
    if ($bid <= 0) return null;
    
    $result = $conn->query("SELECT * FROM businesses WHERE id = $bid");
    return $result->fetch_assoc();
}

/**
 * Audit log
 */
function auditLog($action, $entityType = null, $entityId = null, $description = '', $oldValues = null, $newValues = null) {
    global $conn;
    
    $bid = getBusinessId();
    $uid = getUserId();
    
    if ($bid <= 0) return;
    
    try {
        $actionSafe = $conn->real_escape_string($action);
        $entityTypeSafe = $entityType ? "'" . $conn->real_escape_string($entityType) . "'" : 'NULL';
        $entityIdSafe = $entityId ? intval($entityId) : 'NULL';
        $descSafe = $conn->real_escape_string($description);
        $oldSafe = $oldValues ? "'" . $conn->real_escape_string(json_encode($oldValues)) . "'" : 'NULL';
        $newSafe = $newValues ? "'" . $conn->real_escape_string(json_encode($newValues)) . "'" : 'NULL';
        $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = $conn->real_escape_string(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
        
        @$conn->query("
            INSERT INTO audit_logs 
            (business_id, user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent, created_at)
            VALUES 
            ($bid, $uid, '$actionSafe', $entityTypeSafe, $entityIdSafe, '$descSafe', $oldSafe, $newSafe, '$ip', '$ua', NOW())
        ");
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Pusher real-time
 */
function pushRealTime($channel, $event, $data) {
    $key = getenv('PUSHER_KEY') ?: '692a2afe9b9c204f0136';
    $secret = getenv('PUSHER_SECRET') ?: 'b1352a2ee0523940bfd4';
    $app_id = getenv('PUSHER_APP_ID') ?: '2163092';
    $cluster = getenv('PUSHER_CLUSTER') ?: 'eu';
    
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
 * Get current theme
 */
function getTheme() {
    global $conn;
    $bid = getBusinessId();
    if ($bid <= 0) return null;
    
    $result = $conn->query("SELECT * FROM business_themes WHERE business_id = $bid");
    $theme = $result->fetch_assoc();
    
    if (!$theme) {
        $conn->query("INSERT INTO business_themes (business_id) VALUES ($bid)");
        $result = $conn->query("SELECT * FROM business_themes WHERE business_id = $bid");
        $theme = $result->fetch_assoc();
    }
    
    return $theme;
}

/**
 * Format money
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
    
    $result = $conn->query("SELECT invoice_prefix, next_invoice_number FROM business_settings WHERE business_id = $bid");
    $settings = $result->fetch_assoc();
    
    if (!$settings) {
        // Create default settings
        $conn->query("INSERT INTO business_settings (business_id) VALUES ($bid)");
        $settings = ['invoice_prefix' => 'INV', 'next_invoice_number' => 1];
    }
    
    $prefix = $settings['invoice_prefix'] ?? 'INV';
    $number = intval($settings['next_invoice_number'] ?? 1);
    
    // Increment for next time
    $conn->query("UPDATE business_settings SET next_invoice_number = next_invoice_number + 1 WHERE business_id = $bid");
    
    return $prefix . '-' . date('Ymd') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
}

/**
 * Update product stock + log movement
 */
function updateStock($productId, $quantity, $type, $reason = '', $referenceType = null, $referenceId = null) {
    global $conn;
    
    $bid = getBusinessId();
    $uid = getUserId();
    $productId = intval($productId);
    $quantity = intval($quantity);
    
    if ($productId <= 0 || $quantity <= 0) {
        return ['ok' => false, 'message' => 'Invalid input'];
    }
    
    // Get current stock
    $result = $conn->query("SELECT stock_quantity FROM products WHERE id = $productId AND business_id = $bid");
    $product = $result->fetch_assoc();
    
    if (!$product) {
        return ['ok' => false, 'message' => 'Product not found'];
    }
    
    $current = intval($product['stock_quantity']);
    $change = ($type === 'in') ? $quantity : -$quantity;
    $newStock = $current + $change;
    
    if ($newStock < 0) {
        return ['ok' => false, 'message' => 'Insufficient stock'];
    }
    
    // Update product stock
    $conn->query("UPDATE products SET stock_quantity = $newStock WHERE id = $productId AND business_id = $bid");
    
    // Log stock movement
    $reasonSafe = $conn->real_escape_string($reason);
    $refTypeSafe = $referenceType ? "'" . $conn->real_escape_string($referenceType) . "'" : 'NULL';
    $refIdSafe = $referenceId ? intval($referenceId) : 'NULL';
    $typeSafe = $conn->real_escape_string($type);
    
    @$conn->query("
        INSERT INTO stock_movements 
        (business_id, product_id, user_id, type, quantity, quantity_before, quantity_after, reason, reference_type, reference_id, created_at)
        VALUES 
        ($bid, $productId, $uid, '$typeSafe', $quantity, $current, $newStock, '$reasonSafe', $refTypeSafe, $refIdSafe, NOW())
    ");
    
    return ['ok' => true, 'new_stock' => $newStock];
}

/**
 * Check stock
 */
function checkStock($productId, $quantity) {
    global $conn;
    $bid = getBusinessId();
    
    $result = $conn->query("SELECT stock_quantity, name FROM products WHERE id = " . intval($productId) . " AND business_id = $bid");
    $product = $result->fetch_assoc();
    
    if (!$product) return ['ok' => false, 'message' => 'Product not found'];
    
    if ($product['stock_quantity'] < $quantity) {
        return [
            'ok' => false, 
            'message' => "Insufficient stock for {$product['name']} (only {$product['stock_quantity']} left)"
        ];
    }
    
    return ['ok' => true];
}
?>
