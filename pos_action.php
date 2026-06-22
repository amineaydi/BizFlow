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
    $customerId = !empty($input['customer_id']) ? intval($input['customer_id']) : null;
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
        
        // ✅ Create sale (10 params, 10 type chars)
        $stmt = $conn->prepare("
            INSERT INTO sales 
            (business_id, customer_id, user_id, invoice_number, subtotal, tax_amount, total_amount, paid_amount, change_amount, payment_method, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
        ");
        
        // 10 params: i,i,i,s,d,d,d,d,d,s
        $stmt->bind_param("iiisddddds",
            $bid,            // i
            $customerId,     // i (can be null)
            $uid,            // i
            $invoiceNumber,  // s
            $subtotal,       // d
            $taxAmount,      // d
            $total,          // d
            $paidAmount,     // d
            $changeAmount,   // d
            $paymentMethod   // s
        );
        $stmt->execute();
        $saleId = $conn->insert_id;
        
        // Add items + reduce stock
        foreach ($items as $item) {
            $productId = intval($item['id']);
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $itemTotal = $price * $qty;
            
            // Get product info
            $prodQuery = $conn->prepare("SELECT name, sku, cost_price FROM products WHERE id = ? AND business_id = ?");
            $prodQuery->bind_param("ii", $productId, $bid);
            $prodQuery->execute();
            $prod = $prodQuery->get_result()->fetch_assoc();
            
            if (!$prod) continue;
            
            $prodName = $prod['name'];
            $prodSku = $prod['sku'] ?? '';
            $prodCost = floatval($prod['cost_price']);
            $profit = ($price - $prodCost) * $qty;
            
            // ✅ Insert sale item (9 params, 9 type chars)
            $stmt = $conn->prepare("
                INSERT INTO sale_items 
                (sale_id, product_id, product_name, product_sku, quantity, unit_price, cost_price, total, profit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // 9 params: i,i,s,s,i,d,d,d,d
            $stmt->bind_param("iissidddd",
                $saleId,
                $productId,
                $prodName,
                $prodSku,
                $qty,
                $price,
                $prodCost,
                $itemTotal,
                $profit
            );
            $stmt->execute();
            
            // Reduce stock
            updateStock($productId, $qty, 'out', "Sale #$invoiceNumber", 'sale', $saleId);
            
            // Update sold count
            $conn->query("UPDATE products SET sold_count = sold_count + $qty WHERE id = $productId");
        }
        
        // Update customer stats
        if ($customerId) {
            $stmt = $conn->prepare("
                UPDATE customers 
                SET total_purchases = total_purchases + 1, 
                    total_spent = total_spent + ?,
                    last_purchase_date = NOW()
                WHERE id = ? AND business_id = ?
            ");
            $stmt->bind_param("dii", $total, $customerId, $bid);
            $stmt->execute();
            
            // Loyalty points (1 per currency unit)
            $points = intval($total);
            if ($points > 0) {
                $stmt = $conn->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?");
                $stmt->bind_param("ii", $points, $customerId);
                $stmt->execute();
                
                $stmt = $conn->prepare("
                    INSERT INTO loyalty_transactions 
                    (business_id, customer_id, sale_id, points, type, description) 
                    VALUES (?, ?, ?, ?, 'earned', 'Purchase')
                ");
                $stmt->bind_param("iiii", $bid, $customerId, $saleId, $points);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        
        // Audit log
        @auditLog('create_sale', 'sale', $saleId, "Sale: $invoiceNumber - $total");
        
        // Real-time notification
        @pushRealTime('sales', 'new-sale', [
            'sale_id' => $saleId,
            'invoice' => $invoiceNumber,
            'amount' => $total,
            'cashier' => $_SESSION['user_name'],
            'items_count' => count($items),
            'currency' => 'DT'
        ]);
        
        echo json_encode([
            'success' => true,
            'sale_id' => $saleId,
            'invoice_number' => $invoiceNumber,
            'total' => $total
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Sale error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Failed: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
