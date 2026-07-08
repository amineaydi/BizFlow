<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'db.php';
<?php
session_start();
require_once 'db.php';

requireAdminLogin();
$bid = getBusinessId();
$uid = getUserId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================
// 🔒 CHECK PERMISSIONS
// ============================================================
$productActions = ['add_product', 'edit_product', 'delete_product', 'adjust_stock'];
if (in_array($action, $productActions)) {
    if (!canManageProducts()) {
        redirectBack('products', '', '❌ Only workers can manage products. Ask your worker to do this.');
        exit;
    }
}

$staffActions = ['add_user', 'edit_user', 'delete_user', 'reset_pin'];
if (in_array($action, $staffActions)) {
    if (!canManageStaff()) {
        redirectBack('staff', '', '❌ Only owner can manage staff');
        exit;
    }
}

// ============================================================
// Continue with normal action handling
// ============================================================
requireLogin();

$bid = getBusinessId();
$uid = getUserId();
$action = $_POST['action'] ?? '';

// Helper to redirect with message
function redirectBack($tab, $msg = '', $err = '') {
    $url = "admin.php?tab=$tab";
    if ($msg) $url .= "&msg=" . urlencode($msg);
    if ($err) $url .= "&err=" . urlencode($err);
    header("Location: $url");
    exit;
}

// ========================================
// 📦 PRODUCTS
// ========================================
if ($action === 'add_product') {
    $name = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $costPrice = floatval($_POST['cost_price'] ?? 0);
    $sellingPrice = floatval($_POST['selling_price'] ?? 0);
    $stock = intval($_POST['stock_quantity'] ?? 0);
    $lowThreshold = intval($_POST['low_stock_threshold'] ?? 5);
    $unit = $_POST['unit'] ?? 'piece';
    $imageUrl = trim($_POST['image_url'] ?? '');
    
    if (!$name || $sellingPrice <= 0) {
        redirectBack('products', '', 'Name and selling price are required');
    }
    
    // Safe escape
    $nameSafe = $conn->real_escape_string($name);
    $skuSafe = $conn->real_escape_string($sku);
    $barcodeSafe = $conn->real_escape_string($barcode);
    $descSafe = $conn->real_escape_string($description);
    $unitSafe = $conn->real_escape_string($unit);
    $imageSafe = $conn->real_escape_string($imageUrl);
    $catIdSql = $categoryId ? $categoryId : 'NULL';
    
    $sql = "INSERT INTO products 
        (business_id, category_id, name, description, sku, barcode, image_url, cost_price, selling_price, stock_quantity, low_stock_threshold, unit, is_active, created_at)
        VALUES 
        ($bid, $catIdSql, '$nameSafe', '$descSafe', '$skuSafe', '$barcodeSafe', '$imageSafe', $costPrice, $sellingPrice, $stock, $lowThreshold, '$unitSafe', 1, NOW())";
    
    if ($conn->query($sql)) {
        $productId = $conn->insert_id;
        
        // Log stock movement if initial stock
        if ($stock > 0) {
            @$conn->query("INSERT INTO stock_movements 
                (business_id, product_id, user_id, type, quantity, quantity_before, quantity_after, reason, created_at)
                VALUES 
                ($bid, $productId, $uid, 'in', $stock, 0, $stock, 'Initial stock', NOW())");
        }
        
        @auditLog('add_product', 'product', $productId, "Added: $name");
        redirectBack('products', "Product '$name' added!");
    } else {
        redirectBack('products', '', 'Failed: ' . $conn->error);
    }
}

if ($action === 'edit_product') {
    $id = intval($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $sku = trim($_POST['sku'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $costPrice = floatval($_POST['cost_price'] ?? 0);
    $sellingPrice = floatval($_POST['selling_price'] ?? 0);
    $lowThreshold = intval($_POST['low_stock_threshold'] ?? 5);
    $unit = $_POST['unit'] ?? 'piece';
    $imageUrl = trim($_POST['image_url'] ?? '');
    
    $nameSafe = $conn->real_escape_string($name);
    $skuSafe = $conn->real_escape_string($sku);
    $descSafe = $conn->real_escape_string($description);
    $unitSafe = $conn->real_escape_string($unit);
    $imageSafe = $conn->real_escape_string($imageUrl);
    $catIdSql = $categoryId ? $categoryId : 'NULL';
    
    $sql = "UPDATE products 
        SET category_id=$catIdSql, name='$nameSafe', description='$descSafe', sku='$skuSafe', 
            image_url='$imageSafe', cost_price=$costPrice, selling_price=$sellingPrice, 
            low_stock_threshold=$lowThreshold, unit='$unitSafe'
        WHERE id=$id AND business_id=$bid";
    
    if ($conn->query($sql)) {
        @auditLog('edit_product', 'product', $id, "Updated: $name");
        redirectBack('products', 'Product updated!');
    } else {
        redirectBack('products', '', 'Failed: ' . $conn->error);
    }
}

if ($action === 'delete_product') {
    $id = intval($_POST['product_id'] ?? 0);
    
    if ($conn->query("UPDATE products SET is_active = 0 WHERE id = $id AND business_id = $bid")) {
        @auditLog('delete_product', 'product', $id, "Deactivated");
        redirectBack('products', 'Product removed!');
    } else {
        redirectBack('products', '', 'Failed to delete');
    }
}

if ($action === 'adjust_stock') {
    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $type = $_POST['type'] ?? 'in';
    $reason = trim($_POST['reason'] ?? 'Manual adjustment');
    
    $result = updateStock($productId, $quantity, $type, $reason, 'manual', 0);
    
    if ($result['ok']) {
        redirectBack('products', "Stock updated! New: " . $result['new_stock']);
    } else {
        redirectBack('products', '', $result['message']);
    }
}

// ========================================
// 📂 CATEGORIES
// ========================================
if ($action === 'add_category') {
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? '📦');
    $color = $_POST['color'] ?? '#3b82f6';
    
    if (!$name) redirectBack('categories', '', 'Name required');
    
    $nameSafe = $conn->real_escape_string($name);
    $iconSafe = $conn->real_escape_string($icon);
    $colorSafe = $conn->real_escape_string($color);
    
    if ($conn->query("INSERT INTO categories (business_id, name, icon, color) VALUES ($bid, '$nameSafe', '$iconSafe', '$colorSafe')")) {
        redirectBack('categories', "Category '$name' added!");
    } else {
        redirectBack('categories', '', 'Failed');
    }
}

if ($action === 'delete_category') {
    $id = intval($_POST['category_id'] ?? 0);
    $conn->query("DELETE FROM categories WHERE id = $id AND business_id = $bid");
    redirectBack('categories', 'Category deleted!');
}

// ========================================
// 👥 CUSTOMERS
// ========================================
if ($action === 'add_customer') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$name) redirectBack('customers', '', 'Name required');
    
    $nameSafe = $conn->real_escape_string($name);
    $phoneSafe = $conn->real_escape_string($phone);
    $emailSafe = $conn->real_escape_string($email);
    $addressSafe = $conn->real_escape_string($address);
    $notesSafe = $conn->real_escape_string($notes);
    
    if ($conn->query("INSERT INTO customers 
        (business_id, name, phone, email, address, notes, is_active, created_at)
        VALUES 
        ($bid, '$nameSafe', '$phoneSafe', '$emailSafe', '$addressSafe', '$notesSafe', 1, NOW())")) {
        redirectBack('customers', "Customer '$name' added!");
    } else {
        redirectBack('customers', '', 'Failed: ' . $conn->error);
    }
}

if ($action === 'delete_customer') {
    $id = intval($_POST['customer_id'] ?? 0);
    $conn->query("UPDATE customers SET is_active = 0 WHERE id = $id AND business_id = $bid");
    redirectBack('customers', 'Customer removed!');
}

// ========================================
// 🏢 SUPPLIERS
// ========================================
if ($action === 'add_supplier') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (!$name) redirectBack('suppliers', '', 'Name required');
    
    $nameSafe = $conn->real_escape_string($name);
    $contactSafe = $conn->real_escape_string($contact);
    $phoneSafe = $conn->real_escape_string($phone);
    $emailSafe = $conn->real_escape_string($email);
    $addressSafe = $conn->real_escape_string($address);
    
    if ($conn->query("INSERT INTO suppliers 
        (business_id, name, contact_person, phone, email, address, is_active, created_at)
        VALUES 
        ($bid, '$nameSafe', '$contactSafe', '$phoneSafe', '$emailSafe', '$addressSafe', 1, NOW())")) {
        redirectBack('suppliers', "Supplier '$name' added!");
    } else {
        redirectBack('suppliers', '', 'Failed');
    }
}

if ($action === 'delete_supplier') {
    $id = intval($_POST['supplier_id'] ?? 0);
    $conn->query("UPDATE suppliers SET is_active = 0 WHERE id = $id AND business_id = $bid");
    redirectBack('suppliers', 'Supplier removed!');
}

// ========================================
// 💸 EXPENSES
// ========================================
if ($action === 'add_expense') {
    $title = trim($_POST['title'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $amount = floatval($_POST['amount'] ?? 0);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $vendor = trim($_POST['vendor'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$title || $amount <= 0) {
        redirectBack('expenses', '', 'Title and amount required');
    }
    
    $titleSafe = $conn->real_escape_string($title);
    $dateSafe = $conn->real_escape_string($expenseDate);
    $methodSafe = $conn->real_escape_string($paymentMethod);
    $vendorSafe = $conn->real_escape_string($vendor);
    $notesSafe = $conn->real_escape_string($notes);
    $catIdSql = $categoryId ? $categoryId : 'NULL';
    
    if ($conn->query("INSERT INTO expenses 
        (business_id, category_id, user_id, title, amount, expense_date, payment_method, vendor, notes, created_at)
        VALUES 
        ($bid, $catIdSql, $uid, '$titleSafe', $amount, '$dateSafe', '$methodSafe', '$vendorSafe', '$notesSafe', NOW())")) {
        redirectBack('expenses', "Expense recorded: $amount DT");
    } else {
        redirectBack('expenses', '', 'Failed');
    }
}

if ($action === 'delete_expense') {
    $id = intval($_POST['expense_id'] ?? 0);
    $conn->query("DELETE FROM expenses WHERE id = $id AND business_id = $bid");
    redirectBack('expenses', 'Expense deleted!');
}

// ========================================
// 👨‍💼 STAFF
// ========================================
if ($action === 'add_staff') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'cashier';
    $pin = trim($_POST['pin'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!$fullName) redirectBack('staff', '', 'Name required');
    
    // Generate username
    $username = $email ? explode('@', $email)[0] : strtolower(str_replace(' ', '.', $fullName));
    
    // Check uniqueness
    $emailCheck = $conn->real_escape_string($email);
    $pinCheck = $conn->real_escape_string($pin);
    
    if ($email) {
        $check = $conn->query("SELECT id FROM users WHERE email = '$emailCheck'");
        if ($check->num_rows > 0) {
            redirectBack('staff', '', 'Email already exists');
        }
    }
    
    if ($pin) {
        $check = $conn->query("SELECT id FROM users WHERE pin = '$pinCheck'");
        if ($check->num_rows > 0) {
            redirectBack('staff', '', 'PIN already exists');
        }
    }
    
    // Generate PIN if not provided
    if (!$pin) {
        do {
            $pin = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $check = $conn->query("SELECT id FROM users WHERE pin = '$pin'");
        } while ($check->num_rows > 0);
    }
    
    // Hash password
    $hashedPass = password_hash($password ?: 'changeme123', PASSWORD_BCRYPT);
    
    // Safe escape
    $usernameSafe = $conn->real_escape_string($username);
    $hashedSafe = $conn->real_escape_string($hashedPass);
    $fullNameSafe = $conn->real_escape_string($fullName);
    $roleSafe = $conn->real_escape_string($role);
    $emailSafe = $conn->real_escape_string($email);
    $phoneSafe = $conn->real_escape_string($phone);
    $pinSafe = $conn->real_escape_string($pin);
    
    if ($conn->query("INSERT INTO users 
        (business_id, username, password, full_name, role, email, phone, pin, is_active, created_at)
        VALUES 
        ($bid, '$usernameSafe', '$hashedSafe', '$fullNameSafe', '$roleSafe', '$emailSafe', '$phoneSafe', '$pinSafe', 1, NOW())")) {
        redirectBack('staff', "Staff added! PIN: $pin");
    } else {
        redirectBack('staff', '', 'Failed: ' . $conn->error);
    }
}

if ($action === 'delete_staff') {
    $id = intval($_POST['user_id'] ?? 0);
    
    if ($id === $uid) {
        redirectBack('staff', '', "Can't delete yourself!");
    }
    
    $conn->query("UPDATE users SET is_active = 0 WHERE id = $id AND business_id = $bid");
    redirectBack('staff', 'Staff removed!');
}

if ($action === 'reset_pin') {
    $id = intval($_POST['user_id'] ?? 0);
    
    do {
        $newPin = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM users WHERE pin = '$newPin'");
    } while ($check->num_rows > 0);
    
    $conn->query("UPDATE users SET pin = '$newPin' WHERE id = $id AND business_id = $bid");
    redirectBack('staff', "New PIN: $newPin");
}

// ========================================
// ⚙️ SETTINGS
// ========================================
if ($action === 'save_settings') {
    // Business info
    $bizName = trim($_POST['business_name'] ?? '');
    $bizPhone = trim($_POST['business_phone'] ?? '');
    $bizEmail = trim($_POST['business_email'] ?? '');
    $bizAddress = trim($_POST['business_address'] ?? '');
    $currency = trim($_POST['currency'] ?? 'DT');
    
    if ($bizName) {
        $bizNameSafe = $conn->real_escape_string($bizName);
        $bizPhoneSafe = $conn->real_escape_string($bizPhone);
        $bizEmailSafe = $conn->real_escape_string($bizEmail);
        $bizAddressSafe = $conn->real_escape_string($bizAddress);
        $currencySafe = $conn->real_escape_string($currency);
        
        $conn->query("UPDATE businesses 
            SET name='$bizNameSafe', phone='$bizPhoneSafe', email='$bizEmailSafe', 
                address='$bizAddressSafe', currency='$currencySafe', currency_symbol='$currencySafe'
            WHERE id=$bid");
        
        $_SESSION['business_name'] = $bizName;
    }
    
    // Settings
    $taxRate = floatval($_POST['tax_rate'] ?? 0);
    $taxEnabled = isset($_POST['tax_enabled']) ? 1 : 0;
    $invoicePrefix = trim($_POST['invoice_prefix'] ?? 'INV');
    $receiptHeader = trim($_POST['receipt_header'] ?? '');
    $receiptFooter = trim($_POST['receipt_footer'] ?? '');
    
    $invoicePrefixSafe = $conn->real_escape_string($invoicePrefix);
    $receiptHeaderSafe = $conn->real_escape_string($receiptHeader);
    $receiptFooterSafe = $conn->real_escape_string($receiptFooter);
    
    $conn->query("UPDATE business_settings 
        SET tax_rate=$taxRate, tax_enabled=$taxEnabled, invoice_prefix='$invoicePrefixSafe',
            receipt_header='$receiptHeaderSafe', receipt_footer='$receiptFooterSafe'
        WHERE business_id=$bid");
    
    redirectBack('settings', 'Settings saved!');
}

if ($action === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    
    if (strlen($newPass) < 6) {
        redirectBack('settings', '', 'Password must be 6+ characters');
    }
    
    if ($newPass !== $confirmPass) {
        redirectBack('settings', '', "Passwords don't match");
    }
    
    // Verify current
    $result = $conn->query("SELECT password FROM users WHERE id = $uid");
    $user = $result->fetch_assoc();
    
    $isValid = password_verify($currentPass, $user['password']) || hash_equals($user['password'], $currentPass);
    
    if (!$isValid) {
        redirectBack('settings', '', 'Current password is wrong');
    }
    
    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
    $hashedSafe = $conn->real_escape_string($hashed);
    $conn->query("UPDATE users SET password = '$hashedSafe' WHERE id = $uid");
    
    redirectBack('settings', 'Password changed successfully!');
}
// ============================================================

    exit;
// Default - go back to admin
header("Location: admin.php");
exit;
