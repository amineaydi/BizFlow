<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

requireLogin();

$bid = getBusinessId();
$uid = getUserId();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'create_sale') {
    $items = $input['items'] ?? [];
    $customerId = intval($input['customer_id'] ?? 0) ?: null;
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
        // Calculate
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['price']) * intval($item['qty']);
        }
        
        $settings = $conn->query("SELECT * FROM business_settings WHERE business_id = $bid")->fetch_assoc();
        $taxAmount = ($settings['tax_enabled'] ?? 0) ? ($subtotal * floatval($settings['tax_rate']) / 100) : 0;
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber();
        
        // Create sale
        $stmt = $conn->prepare("
            INSERT INTO sales (business_id, customer_id, user_id, invoice_number, subtotal, tax_amount, total_amount, paid_amount, change_amount, payment_method, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->bind_param("iiisddddds", $bid, $customerId, $uid, $invoiceNumber, $subtotal, $taxAmount, $total, $paidAmount, $changeAmount, $paymentMethod);
        $stmt->execute();
        $saleId = $conn->insert_id;
        
        // Add items + reduce stock
        foreach ($items as $item) {
            $productId = intval($item['id']);
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $itemTotal = $price * $qty;
            
            // Get product info
            $prod = $conn->query("SELECT name, sku, cost_price FROM products WHERE id = $productId AND business_id = $bid")->fetch_assoc();
            $profit = ($price - $prod['cost_price']) * $qty;
            
            // Add to sale_items
            $stmt = $conn->prepare("
                INSERT INTO sale_items (sale_id, product_id, product_name, product_sku, quantity, unit_price, cost_price, total, profit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissiddd", $saleId, $productId, $prod['name'], $prod['sku'], $qty, $price, $prod['cost_price'], $itemTotal, $profit);
            $stmt->execute();
            
            // Reduce stock
            updateStock($productId, $qty, 'out', "Sale #$invoiceNumber", 'sale', $saleId);
            
            // Update sold count
            $conn->query("UPDATE products SET sold_count = sold_count + $qty WHERE id = $productId");
        }
        
        // Update customer stats
        if ($customerId) {
            $conn->query("
                UPDATE customers 
                SET total_purchases = total_purchases + 1, 
                    total_spent = total_spent + $total,
                    last_purchase_date = NOW()
                WHERE id = $customerId AND business_id = $bid
            ");
            
            // Add loyalty points (1 point per currency unit)
            $points = intval($total);
            if ($points > 0) {
                $conn->query("UPDATE customers SET loyalty_points = loyalty_points + $points WHERE id = $customerId");
                $conn->query("
                    INSERT INTO loyalty_transactions (business_id, customer_id, sale_id, points, type, description) 
                    VALUES ($bid, $customerId, $saleId, $points, 'earned', 'Purchase')
                ");
            }
        }
        
        $conn->commit();
        
        // Audit log
        auditLog('create_sale', 'sale', $saleId, "Sale: $total");
        
        // Real-time notification
        pushRealTime('sales', 'new-sale', [
            'sale_id' => $saleId,
            'invoice' => $invoiceNumber,
            'amount' => $total,
            'cashier' => $_SESSION['user_name'],
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
        echo json_encode(['success' => false, 'message' => 'Failed: ' . $e->getMessage()]);
    }
    
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
