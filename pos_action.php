<?php
header('Content-Type: application/json');
session_start();
require_once 'db.php';

requireLogin();

$bid = getBusinessId();
$uid = getUserId();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'create_sale') {
    $items = $input['items'] ?? [];
    $customerId = !empty($input['customer_id']) ? intval($input['customer_id']) : 0;
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $paidAmount = floatval($input['paid_amount'] ?? 0);
    $changeAmount = floatval($input['change_amount'] ?? 0);
    $total = floatval($input['total'] ?? 0);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Calculate subtotal
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['price']) * intval($item['qty']);
        }
        
        // Get tax settings
        $settingsQuery = $conn->query("SELECT * FROM business_settings WHERE business_id = $bid");
        $settings = $settingsQuery->fetch_assoc();
        $taxAmount = ($settings['tax_enabled'] ?? 0) ? ($subtotal * floatval($settings['tax_rate']) / 100) : 0;
        
        // Generate invoice
        $invoiceNumber = generateInvoiceNumber();
        
        // ✅ Use direct SQL with escaping (simpler than bind_param for mixed null)
        $custIdSql = $customerId > 0 ? $customerId : 'NULL';
        $invoiceSafe = $conn->real_escape_string($invoiceNumber);
        $paymentSafe = $conn->real_escape_string($paymentMethod);
        
        $sql = "INSERT INTO sales 
            (business_id, customer_id, user_id, invoice_number, subtotal, tax_amount, total_amount, paid_amount, change_amount, payment_method, status, created_at)
            VALUES 
            ($bid, $custIdSql, $uid, '$invoiceSafe', $subtotal, $taxAmount, $total, $paidAmount, $changeAmount, '$paymentSafe', 'completed', NOW())";
        
        if (!$conn->query($sql)) {
            throw new Exception("Failed to create sale: " . $conn->error);
        }
        
        $saleId = $conn->insert_id;
        
        // Add items + reduce stock
        foreach ($items as $item) {
            $productId = intval($item['id']);
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $itemTotal = $price * $qty;
            
            // Get product info
            $prodResult = $conn->query("SELECT name, sku, cost_price FROM products WHERE id = $productId AND business_id = $bid");
            $prod = $prodResult->fetch_assoc();
            
            if (!$prod) continue;
            
            $prodName = $conn->real_escape_string($prod['name']);
            $prodSku = $conn->real_escape_string($prod['sku'] ?? '');
            $prodCost = floatval($prod['cost_price']);
            $profit = ($price - $prodCost) * $qty;
            
            // Insert sale item with direct SQL
            $sqlItem = "INSERT INTO sale_items 
                (sale_id, product_id, product_name, product_sku, quantity, unit_price, cost_price, total, profit)
                VALUES 
                ($saleId, $productId, '$prodName', '$prodSku', $qty, $price, $prodCost, $itemTotal, $profit)";
            
            if (!$conn->query($sqlItem)) {
                throw new Exception("Failed to add item: " . $conn->error);
            }
            
            // Reduce stock
            updateStock($productId, $qty, 'out', "Sale #$invoiceNumber", 'sale', $saleId);
            
            // Update sold count
            $conn->query("UPDATE products SET sold_count = sold_count + $qty WHERE id = $productId");
        }
        
        // Update customer stats
        if ($customerId > 0) {
            $conn->query("
                UPDATE customers 
                SET total_purchases = total_purchases + 1, 
                    total_spent = total_spent + $total,
                    last_purchase_date = NOW()
                WHERE id = $customerId AND business_id = $bid
            ");
            
            // Loyalty points
            $points = intval($total);
            if ($points > 0) {
                $conn->query("UPDATE customers SET loyalty_points = loyalty_points + $points WHERE id = $customerId");
                $conn->query("
                    INSERT INTO loyalty_transactions 
                    (business_id, customer_id, sale_id, points, type, description) 
                    VALUES ($bid, $customerId, $saleId, $points, 'earned', 'Purchase')
                ");
            }
        }
        
        $conn->commit();
        
        // Audit log (suppress errors)
        @auditLog('create_sale', 'sale', $saleId, "Sale: $invoiceNumber");
        
        // Real-time notification (suppress errors)
        @pushRealTime('sales', 'new-sale', [
            'sale_id' => $saleId,
            'invoice' => $invoiceNumber,
            'amount' => $total,
            'cashier' => $_SESSION['user_name'] ?? 'Cashier',
            'items_count' => count($items)
        ]);
        
        echo json_encode([
            'success' => true,
            'sale_id' => $saleId,
            'invoice_number' => $invoiceNumber,
            'total' => $total
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
