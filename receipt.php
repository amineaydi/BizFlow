<?php
session_start();
require_once 'db.php';
require_once 'lang.php';

// Get sale ID from URL
$saleId = intval($_GET['id'] ?? 0);
if ($saleId <= 0) die('Invalid sale');

$bid = getBusinessId();
if (!$bid) die('Not authorized');

// Get sale details
$stmt = $conn->prepare("
    SELECT s.*, 
           u.full_name as cashier_name,
           c.name as customer_name,
           c.phone as customer_phone
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ? AND s.business_id = ?
");
$stmt->bind_param("ii", $saleId, $bid);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) die('Sale not found');

// Get sale items
$stmt = $conn->prepare("
    SELECT si.*, p.name as product_name, p.unit
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$items = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}

// Get business info
$business = getBusinessInfo();

// Get business settings (for tax etc)
$stmt = $conn->prepare("SELECT * FROM business_settings WHERE business_id = ?");
$stmt->bind_param("i", $bid);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();

$currency = $business['currency_symbol'] ?? 'DT';
$taxRate = floatval($settings['tax_rate'] ?? 0);
$taxEnabled = intval($settings['tax_enabled'] ?? 0);

// Calculate values
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += floatval($item['unit_price']) * intval($item['quantity']);
}
$taxAmount = $taxEnabled ? ($subtotal * $taxRate / 100) : 0;
$total = floatval($sale['total_amount']);
$paid = floatval($sale['paid_amount'] ?? $total);
$change = floatval($sale['change_amount'] ?? 0);

$autoPrint = isset($_GET['auto']) && $_GET['auto'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?= htmlspecialchars($sale['invoice_number'] ?? $sale['id']) ?></title>
<style>
/* ===== Screen Preview ===== */
body {
    background: #f0f0f0;
    font-family: 'Courier New', monospace;
    padding: 20px;
    margin: 0;
}

.receipt-container {
    max-width: 320px;
    margin: 0 auto;
    background: white;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.receipt {
    font-size: 12px;
    color: #000;
    line-height: 1.5;
}

.center { text-align: center; }
.right  { text-align: right; }
.bold   { font-weight: bold; }
.large  { font-size: 16px; }
.small  { font-size: 10px; }

.divider {
    border-top: 1px dashed #000;
    margin: 8px 0;
}

.solid-line {
    border-top: 2px solid #000;
    margin: 8px 0;
}

.logo-emoji {
    font-size: 40px;
    line-height: 1;
    margin-bottom: 5px;
}

.business-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
}

.info-line {
    display: flex;
    justify-content: space-between;
    margin: 3px 0;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin: 8px 0;
}

.items-table th {
    text-align: left;
    padding: 4px 2px;
    border-bottom: 1px dashed #000;
    font-size: 11px;
}

.items-table td {
    padding: 4px 2px;
    font-size: 11px;
}

.item-name {
    width: 50%;
}

.item-qty { width: 15%; text-align: center; }
.item-price { width: 15%; text-align: right; }
.item-total { width: 20%; text-align: right; font-weight: bold; }

.totals-section {
    margin-top: 10px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    margin: 3px 0;
    font-size: 12px;
}

.total-line.grand {
    font-size: 16px;
    font-weight: bold;
    border-top: 2px solid #000;
    padding-top: 5px;
    margin-top: 5px;
}

.footer {
    text-align: center;
    margin-top: 15px;
    font-size: 11px;
}

.footer .thank-you {
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 5px;
}

.powered-by {
    margin-top: 15px;
    font-size: 9px;
    color: #666;
    text-align: center;
}

/* ===== Print Buttons ===== */
.controls {
    max-width: 320px;
    margin: 20px auto;
    display: flex;
    gap: 10px;
}

.btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    font-family: system-ui, -apple-system, sans-serif;
}

.btn-print {
    background: #10b981;
    color: white;
}

.btn-bluetooth {
    background: #3b82f6;
    color: white;
}

.btn-back {
    background: #6b7280;
    color: white;
}

/* ===== PRINT STYLES ===== */
@media print {
    body {
        background: white;
        padding: 0;
        margin: 0;
    }
    
    .controls, .no-print {
        display: none !important;
    }
    
    .receipt-container {
        max-width: 100%;
        box-shadow: none;
        padding: 0;
    }
    
    .receipt {
        font-size: 11px;
    }
    
    /* Thermal 80mm */
    @page {
        margin: 5mm;
        size: 80mm auto;
    }
}
</style>
</head>
<body>

<div class="controls no-print">
    <button class="btn btn-print" onclick="window.print()">🖨️ Print</button>
    <button class="btn btn-bluetooth" onclick="printBluetooth()">📶 Bluetooth</button>
    <button class="btn btn-back" onclick="window.close()">✖ Close</button>
</div>

<div class="receipt-container">
    <div class="receipt" id="receiptContent">
        
        <!-- HEADER -->
        <div class="center">
            <div class="logo-emoji"><?= htmlspecialchars($business['logo_emoji'] ?? '🏪') ?></div>
            <div class="business-name"><?= htmlspecialchars($business['name']) ?></div>
            
            <?php if (!empty($business['address'])): ?>
            <div class="small"><?= htmlspecialchars($business['address']) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($business['city'])): ?>
            <div class="small"><?= htmlspecialchars($business['city']) ?><?= !empty($business['country']) ? ', ' . htmlspecialchars($business['country']) : '' ?></div>
            <?php endif; ?>
            
            <?php if (!empty($business['phone'])): ?>
            <div class="small">📞 <?= htmlspecialchars($business['phone']) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($business['email'])): ?>
            <div class="small">✉️ <?= htmlspecialchars($business['email']) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($business['tax_number'])): ?>
            <div class="small bold">MF: <?= htmlspecialchars($business['tax_number']) ?></div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <!-- INVOICE INFO -->
        <div class="info-line">
            <span class="bold">Invoice #:</span>
            <span><?= htmlspecialchars($sale['invoice_number'] ?? 'INV-' . str_pad($sale['id'], 6, '0', STR_PAD_LEFT)) ?></span>
        </div>
        <div class="info-line">
            <span class="bold">Date:</span>
            <span><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span>
        </div>
        <div class="info-line">
            <span class="bold">Cashier:</span>
            <span><?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></span>
        </div>
        
        <?php if (!empty($sale['customer_name'])): ?>
        <div class="info-line">
            <span class="bold">Customer:</span>
            <span><?= htmlspecialchars($sale['customer_name']) ?></span>
        </div>
        <?php endif; ?>

        <div class="divider"></div>

        <!-- ITEMS -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="item-name">Item</th>
                    <th class="item-qty">Qty</th>
                    <th class="item-price">Price</th>
                    <th class="item-total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $itemTotal = floatval($item['unit_price']) * intval($item['quantity']);
                ?>
                <tr>
                    <td class="item-name"><?= htmlspecialchars($item['product_name'] ?? 'Item') ?></td>
                    <td class="item-qty"><?= intval($item['quantity']) ?></td>
                    <td class="item-price"><?= number_format($item['unit_price'], 2) ?></td>
                    <td class="item-total"><?= number_format($itemTotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="solid-line"></div>

        <!-- TOTALS -->
        <div class="totals-section">
            <div class="total-line">
                <span>Subtotal:</span>
                <span><?= number_format($subtotal, 2) ?> <?= $currency ?></span>
            </div>
            
            <?php if ($taxEnabled && $taxRate > 0): ?>
            <div class="total-line">
                <span>Tax (<?= $taxRate ?>%):</span>
                <span><?= number_format($taxAmount, 2) ?> <?= $currency ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-line grand">
                <span>TOTAL:</span>
                <span><?= number_format($total, 2) ?> <?= $currency ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- PAYMENT INFO -->
        <div class="info-line">
            <span class="bold">Payment:</span>
            <span><?= strtoupper(htmlspecialchars($sale['payment_method'] ?? 'CASH')) ?></span>
        </div>
        
        <?php if ($sale['payment_method'] === 'cash' && $paid > 0): ?>
        <div class="info-line">
            <span>Received:</span>
            <span><?= number_format($paid, 2) ?> <?= $currency ?></span>
        </div>
        
        <?php if ($change > 0): ?>
        <div class="info-line bold">
            <span>Change:</span>
            <span><?= number_format($change, 2) ?> <?= $currency ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="divider"></div>

        <!-- THANK YOU MESSAGE -->
        <div class="footer">
            <div class="thank-you">✨ Thank You! ✨</div>
            <div>شكراً لزيارتكم</div>
            <div>Merci de votre visite</div>
            
            <?php if (!empty($settings['receipt_footer'])): ?>
            <div style="margin-top:10px;"><?= nl2br(htmlspecialchars($settings['receipt_footer'])) ?></div>
            <?php endif; ?>
        </div>

        <div class="powered-by">
            Powered by BizFlow
        </div>
    </div>
</div>

<script>
// Auto-print if requested
<?php if ($autoPrint): ?>
setTimeout(function() {
    window.print();
}, 500);
<?php endif; ?>

// Auto-close after print (optional)
window.addEventListener('afterprint', function() {
    // Uncomment if you want auto-close after printing
    // setTimeout(function() { window.close(); }, 1000);
});

// ============================================================
// 📶 BLUETOOTH PRINTER (Web Bluetooth API)
// ============================================================
async function printBluetooth() {
    if (!navigator.bluetooth) {
        alert('❌ Bluetooth not supported on this device.\nUse Chrome on Android or Desktop.');
        return;
    }
    
    try {
        console.log('🔍 Requesting Bluetooth device...');
        
        // Request device (thermal printers often use these service UUIDs)
        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: [
                '000018f0-0000-1000-8000-00805f9b34fb', // Common thermal printer service
                '0000fee7-0000-1000-8000-00805f9b34fb',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                '000018f0-0000-1000-8000-00805f9b34fb'
            ]
        });
        
        console.log('📱 Connecting to:', device.name);
        const server = await device.gatt.connect();
        
        // Try common services
        let characteristic = null;
        const services = await server.getPrimaryServices();
        
        for (const service of services) {
            const chars = await service.getCharacteristics();
            for (const c of chars) {
                if (c.properties.write || c.properties.writeWithoutResponse) {
                    characteristic = c;
                    break;
                }
            }
            if (characteristic) break;
        }
        
        if (!characteristic) {
            throw new Error('No writable characteristic found');
        }
        
        // Build ESC/POS commands
        const encoder = new TextEncoder();
        const commands = buildEscPosReceipt();
        
        // Send in chunks (max 512 bytes typically)
        const chunkSize = 200;
        for (let i = 0; i < commands.length; i += chunkSize) {
            const chunk = commands.slice(i, i + chunkSize);
            await characteristic.writeValue(chunk);
            await new Promise(r => setTimeout(r, 50));
        }
        
        alert('✅ Sent to printer!');
        
    } catch (error) {
        console.error('Bluetooth error:', error);
        alert('❌ Bluetooth error: ' + error.message);
    }
}

/**
 * Build ESC/POS commands for thermal printer
 */
function buildEscPosReceipt() {
    const ESC = 0x1B;
    const GS  = 0x1D;
    const LF  = 0x0A;
    
    let bytes = [];
    
    // Initialize
    bytes.push(ESC, 0x40);
    
    // Get receipt content as text
    const receiptText = getReceiptAsText();
    
    // Center alignment
    bytes.push(ESC, 0x61, 1);
    
    // Big text for business name
    bytes.push(ESC, 0x21, 0x30); // Double size
    bytes = bytes.concat(strToBytes(<?= json_encode($business['name']) ?> + '\n'));
    
    // Normal size
    bytes.push(ESC, 0x21, 0x00);
    
    // Left alignment
    bytes.push(ESC, 0x61, 0);
    
    // Add all content
    bytes = bytes.concat(strToBytes(receiptText));
    
    // Feed and cut
    bytes.push(LF, LF, LF, LF);
    bytes.push(GS, 0x56, 0x00); // Full cut
    
    return new Uint8Array(bytes);
}

function strToBytes(str) {
    const bytes = [];
    for (let i = 0; i < str.length; i++) {
        bytes.push(str.charCodeAt(i) & 0xFF);
    }
    return bytes;
}

function getReceiptAsText() {
    let text = '';
    text += '================================\n';
    text += '<?= addslashes($business['name']) ?>\n';
    <?php if (!empty($business['address'])): ?>
    text += '<?= addslashes($business['address']) ?>\n';
    <?php endif; ?>
    <?php if (!empty($business['phone'])): ?>
    text += 'Tel: <?= addslashes($business['phone']) ?>\n';
    <?php endif; ?>
    text += '================================\n';
    text += 'Invoice: <?= addslashes($sale['invoice_number'] ?? $sale['id']) ?>\n';
    text += 'Date: <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?>\n';
    text += 'Cashier: <?= addslashes($sale['cashier_name'] ?? 'N/A') ?>\n';
    text += '--------------------------------\n';
    
    <?php foreach ($items as $item): 
        $itemTotal = floatval($item['unit_price']) * intval($item['quantity']);
    ?>
    text += '<?= addslashes(substr($item['product_name'] ?? '', 0, 20)) ?>\n';
    text += '  <?= $item['quantity'] ?> x <?= number_format($item['unit_price'], 2) ?> = <?= number_format($itemTotal, 2) ?>\n';
    <?php endforeach; ?>
    
    text += '--------------------------------\n';
    text += 'Subtotal:  <?= number_format($subtotal, 2) ?> <?= $currency ?>\n';
    <?php if ($taxEnabled && $taxRate > 0): ?>
    text += 'Tax (<?= $taxRate ?>%): <?= number_format($taxAmount, 2) ?> <?= $currency ?>\n';
    <?php endif; ?>
    text += '================================\n';
    text += 'TOTAL:     <?= number_format($total, 2) ?> <?= $currency ?>\n';
    text += '================================\n';
    text += 'Payment: <?= strtoupper($sale['payment_method'] ?? 'CASH') ?>\n';
    <?php if ($change > 0): ?>
    text += 'Change:  <?= number_format($change, 2) ?> <?= $currency ?>\n';
    <?php endif; ?>
    text += '================================\n';
    text += '\n     Thank You!     \n';
    text += '  شكراً لزيارتكم  \n';
    text += '\n';
    return text;
}
</script>

</body>
</html>
