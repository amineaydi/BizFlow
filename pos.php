<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'db.php';
require_once 'theme.php';

requireLogin();

$bid = getBusinessId();
$uid = getUserId();
$business = getBusinessInfo();
$theme = loadCurrentTheme();
$currency = $business['currency_symbol'] ?? 'DT';

$settings = $conn->query("SELECT * FROM business_settings WHERE business_id = $bid")->fetch_assoc();
$taxRate = floatval($settings['tax_rate'] ?? 0);
$taxEnabled = intval($settings['tax_enabled'] ?? 0);

$today = date('Y-m-d');
$myStats = $conn->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount),0) as revenue
    FROM sales WHERE business_id = $bid AND user_id = $uid 
    AND DATE(created_at) = '$today' AND status = 'completed'
")->fetch_assoc();

// Load all products as PHP array
$productsList = [];
$prodQuery = $conn->query("
    SELECT * FROM products 
    WHERE business_id = $bid AND is_active = 1 
    ORDER BY sold_count DESC, name
");
while ($p = $prodQuery->fetch_assoc()) {
    $productsList[] = $p;
}

// Load categories
$catsList = [];
$catsQuery = $conn->query("SELECT * FROM categories WHERE business_id = $bid AND is_active = 1 ORDER BY name");
while ($c = $catsQuery->fetch_assoc()) {
    $catsList[] = $c;
}

// Load customers
$customersList = [];
$custQuery = $conn->query("SELECT * FROM customers WHERE business_id = $bid AND is_active = 1 ORDER BY name");
while ($c = $custQuery->fetch_assoc()) {
    $customersList[] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>POS · BizFlow</title>
<link rel="manifest" href="manifest.json">
<?= renderThemeCSS($theme) ?>
<style>
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; user-select:none; }
body { background: var(--bg-dark); color: var(--text); font-family: var(--font-body); height: 100vh; overflow: hidden; }

.pos-layout { display: grid; grid-template-columns: 1fr 400px; height: 100vh; }

.products-side { display: flex; flex-direction: column; overflow: hidden; }

.top-bar {
    background: var(--bg-card);
    padding: 14px 20px;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.brand { display: flex; align-items: center; gap: 12px; }
.brand-icon {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
}
.brand-name { font-family: var(--font-heading); font-size: 18px; font-weight: 700; }
.brand-sub { font-size: 11px; color: #9ca3af; }

.top-actions { display: flex; align-items: center; gap: 10px; }

.user-chip {
    display: flex; align-items: center; gap: 8px;
    background: var(--bg-dark);
    padding: 6px 14px 6px 6px;
    border-radius: 25px;
    font-size: 12px; font-weight: 600;
}
.user-avatar {
    width: 28px; height: 28px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 12px;
}

.stat-chip {
    background: rgba(16,185,129,0.15);
    color: #10b981;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 12px; font-weight: 700;
}

.icon-btn {
    background: var(--bg-dark);
    border: 1px solid rgba(255,255,255,0.06);
    width: 38px; height: 38px;
    border-radius: 10px;
    color: white; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; text-decoration: none;
}
.icon-btn:hover { background: var(--primary); }

.search-row {
    padding: 16px 20px;
    background: var(--bg-card);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; gap: 10px;
}

.search-input {
    flex: 1;
    background: var(--bg-dark);
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    padding: 12px 18px;
    color: white;
    font-size: 14px;
    font-family: inherit;
}
.search-input:focus { outline: none; border-color: var(--primary); }

.cats-scroll {
    padding: 12px 20px;
    background: var(--bg-card);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    overflow-x: auto;
    white-space: nowrap;
}
.cats-scroll::-webkit-scrollbar { height: 0; }

.cat-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-dark);
    border: 1px solid rgba(255,255,255,0.06);
    padding: 8px 16px;
    border-radius: 20px;
    margin-right: 8px;
    color: white;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
}
.cat-pill.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: transparent;
}

.products-grid-wrap { flex: 1; overflow-y: auto; padding: 16px 20px; }
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
}

.product-card {
    background: var(--bg-card);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 14px;
    padding: 14px;
    cursor: pointer;
    transition: 0.15s;
    position: relative;
    overflow: hidden;
}
.product-card:hover { transform: translateY(-2px); border-color: var(--primary); }
.product-card:active { transform: scale(0.97); }
.product-card.out-of-stock { opacity: 0.4; cursor: not-allowed; }

.product-image {
    width: 100%;
    aspect-ratio: 1;
    background: var(--bg-dark);
    border-radius: 10px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    overflow: hidden;
}
.product-image img { width: 100%; height: 100%; object-fit: cover; }

.product-name {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.product-price {
    font-size: 16px;
    font-weight: 800;
    color: var(--primary);
}

.stock-badge {
    position: absolute;
    top: 8px; right: 8px;
    background: rgba(16,185,129,0.9);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
}
.stock-badge.low { background: rgba(251,191,36,0.9); color: #000; }
.stock-badge.out { background: rgba(239,68,68,0.9); }

.cart-side {
    background: var(--bg-card);
    display: flex;
    flex-direction: column;
    border-left: 1px solid rgba(255,255,255,0.06);
    height: 100vh;
}

.cart-header {
    padding: 18px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-title {
    font-family: var(--font-heading);
    font-size: 20px;
    font-weight: 700;
}

.cart-clear {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

.customer-section {
    padding: 14px 20px;
    background: var(--bg-dark);
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.customer-btn {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px dashed rgba(255,255,255,0.1);
    color: #9ca3af;
    padding: 10px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
}
.customer-btn:hover { border-color: var(--primary); color: var(--primary); }

.cart-items { flex: 1; overflow-y: auto; padding: 12px 16px; }

.cart-empty {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}
.cart-empty .icon { font-size: 60px; margin-bottom: 12px; opacity: 0.5; }

.cart-item {
    background: var(--bg-dark);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 8px;
}

.cart-item-top {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 8px;
}

.cart-item-name { font-size: 13px; font-weight: 700; flex: 1; }
.cart-item-remove {
    background: none; border: none;
    color: #ef4444;
    cursor: pointer; font-size: 18px;
    padding: 0 4px;
}

.cart-item-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.qty-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-card);
    border-radius: 8px;
    padding: 4px;
}

.qty-btn {
    width: 28px; height: 28px;
    background: var(--bg-dark);
    border: none; border-radius: 6px;
    color: white;
    font-size: 16px;
    cursor: pointer;
    font-weight: 800;
}
.qty-btn:hover { background: var(--primary); }
.qty-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.qty-value { min-width: 30px; text-align: center; font-weight: 800; font-size: 14px; }
.cart-item-price { font-weight: 800; color: var(--primary); font-size: 15px; }
.cart-item-each { font-size: 11px; color: #6b7280; }

.cart-totals {
    padding: 16px 20px;
    background: var(--bg-dark);
    border-top: 1px solid rgba(255,255,255,0.06);
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
    color: #9ca3af;
}

.total-row.grand {
    font-size: 22px;
    font-weight: 800;
    color: var(--primary);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.checkout-section {
    padding: 16px 20px;
    background: var(--bg-card);
    border-top: 1px solid rgba(255,255,255,0.06);
}

.payment-methods {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 12px;
}

.payment-btn {
    background: var(--bg-dark);
    border: 2px solid transparent;
    color: white;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
}
.payment-btn.active {
    border-color: var(--primary);
    background: rgba(59,130,246,0.1);
}

.btn-checkout {
    width: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    padding: 16px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-family: inherit;
}
.btn-checkout:disabled { opacity: 0.5; cursor: not-allowed; }

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal.show { display: flex; }

.modal-content {
    background: var(--bg-card);
    border-radius: 20px;
    padding: 30px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-title {
    font-family: var(--font-heading);
    font-size: 22px;
    margin-bottom: 20px;
}

label {
    display: block;
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    margin-bottom: 6px;
}

.form-input {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-dark);
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: white;
    font-size: 14px;
    font-family: inherit;
}
.form-input:focus { outline: none; border-color: var(--primary); }

.cash-input {
    width: 100%;
    padding: 16px;
    background: var(--bg-dark);
    border: 2px solid var(--primary);
    border-radius: 14px;
    color: white;
    font-size: 24px;
    text-align: center;
    font-weight: 800;
    font-family: inherit;
}

.change-display {
    background: rgba(16,185,129,0.1);
    border: 1px solid #10b981;
    color: #10b981;
    padding: 14px;
    border-radius: 10px;
    margin-top: 12px;
    font-size: 18px;
    font-weight: 800;
    text-align: center;
}

.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 14px 22px;
    border-radius: 12px;
    font-weight: 700;
    z-index: 99999;
    max-width: 350px;
}
.toast.error { background: linear-gradient(135deg, #ef4444, #dc2626); }

@media (max-width: 768px) {
    .pos-layout { grid-template-columns: 1fr; }
    .cart-side {
        position: fixed;
        right: -100%;
        top: 0;
        width: 90%;
        max-width: 400px;
        z-index: 1000;
        transition: right 0.3s;
    }
    .cart-side.open { right: 0; }
}

::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
</style>
</head>
<body>

<div class="pos-layout">
    
    <div class="products-side">
        
        <div class="top-bar">
            <div class="brand">
                <div class="brand-icon"><?= htmlspecialchars($business['logo_emoji'] ?? '🏪') ?></div>
                <div>
                    <div class="brand-name"><?= htmlspecialchars($business['name']) ?></div>
                    <div class="brand-sub">POS Terminal</div>
                </div>
            </div>
            
            <div class="top-actions">
                <div class="stat-chip">
                    💰 <?= number_format($myStats['revenue'], 0) ?> <?= $currency ?> · 📦 <?= $myStats['count'] ?>
                </div>
                
                <div class="user-chip">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </div>
                
                <?php if (!in_array($_SESSION['user_role'], ['cashier', 'worker'])): ?>
                    <a href="admin.php" class="icon-btn" title="Admin">⚙️</a>
                <?php endif; ?>
                
                <a href="logout.php" class="icon-btn" title="Logout">🚪</a>
            </div>
        </div>
        
        <div class="search-row">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search products..." autofocus>
        </div>
        
        <div class="cats-scroll">
            <button type="button" class="cat-pill active" data-cat="0">🏠 All</button>
            <?php foreach ($catsList as $c): ?>
                <button type="button" class="cat-pill" data-cat="<?= intval($c['id']) ?>">
                    <?= htmlspecialchars($c['icon'] ?? '📦') ?> <?= htmlspecialchars($c['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="products-grid-wrap">
            <div class="products-grid" id="productsGrid">
                <!-- Products will be rendered by JavaScript -->
            </div>
        </div>
    </div>
    
    <div class="cart-side" id="cartSide">
        
        <div class="cart-header">
            <div class="cart-title">🛒 Cart <span id="cartCount" style="color:#9ca3af;font-size:13px;font-weight:600;"></span></div>
            <button type="button" class="cart-clear" id="btnClearCart">Clear</button>
        </div>
        
        <div class="customer-section">
            <button type="button" class="customer-btn" id="customerBtn">
                👤 Add Customer (optional)
            </button>
        </div>
        
        <div class="cart-items" id="cartItems">
            <div class="cart-empty">
                <div class="icon">🛒</div>
                <div>Cart is empty</div>
                <p style="font-size:12px;margin-top:8px;">Click products to add</p>
            </div>
        </div>
        
        <div class="cart-totals" id="cartTotals" style="display:none;">
            <div class="total-row">
                <span>Subtotal</span>
                <span id="subtotalDisplay">0.00 <?= $currency ?></span>
            </div>
            <?php if ($taxEnabled && $taxRate > 0): ?>
                <div class="total-row">
                    <span>Tax (<?= $taxRate ?>%)</span>
                    <span id="taxDisplay">0.00 <?= $currency ?></span>
                </div>
            <?php endif; ?>
            <div class="total-row grand">
                <span>TOTAL</span>
                <span id="totalDisplay">0.00 <?= $currency ?></span>
            </div>
        </div>
        
        <div class="checkout-section">
            <div class="payment-methods">
                <button type="button" class="payment-btn active" data-method="cash">💵 Cash</button>
                <button type="button" class="payment-btn" data-method="card">💳 Card</button>
            </div>
            
            <button type="button" class="btn-checkout" id="btnCheckout" disabled>
                💳 Checkout
            </button>
        </div>
    </div>
</div>

<!-- CUSTOMER MODAL -->
<div class="modal" id="customerModal">
    <div class="modal-content">
        <div class="modal-title">👤 Select Customer</div>
        <input type="text" class="form-input" placeholder="🔍 Search..." id="customerSearch" style="margin-bottom:15px;">
        <div id="customersList" style="max-height:300px;overflow-y:auto;"></div>
        <button type="button" class="btn-checkout" style="margin-top:15px;background:rgba(255,255,255,0.1);" id="btnCloseCustomer">Cancel</button>
    </div>
</div>

<!-- PAYMENT MODAL -->
<div class="modal" id="paymentModal">
    <div class="modal-content" style="max-width:400px;text-align:center;">
        <div class="modal-title" style="text-align:center;">💵 Complete Sale</div>
        <div style="font-size:14px;color:#9ca3af;">Total to pay:</div>
        <div style="font-size:36px;font-weight:800;color:var(--primary);margin:10px 0 20px;" id="paymentTotal">0.00 <?= $currency ?></div>
        
        <label>Cash Received</label>
        <input type="number" step="0.01" class="cash-input" id="cashReceived" placeholder="0.00">
        
        <div class="change-display" id="changeDisplay" style="display:none;">
            Change: <span id="changeAmount">0.00 <?= $currency ?></span>
        </div>
        
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="button" class="payment-btn" style="flex:1;" id="btnCancelPayment">Cancel</button>
            <button type="button" class="btn-checkout" style="flex:1;margin:0;" id="btnConfirmPayment">✅ Confirm</button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div class="modal" id="successModal">
    <div class="modal-content" style="max-width:400px;text-align:center;">
        <div style="font-size:80px;margin-bottom:15px;">🎉</div>
        <div class="modal-title" style="text-align:center;">Sale Complete!</div>
        <div style="font-size:36px;font-weight:800;color:#10b981;margin:15px 0;" id="successAmount">0.00 <?= $currency ?></div>
        <div style="color:#9ca3af;margin-bottom:15px;">Invoice: <strong id="successInvoice"></strong></div>
        <div id="successChange" style="background:rgba(16,185,129,0.1);padding:14px;border-radius:10px;color:#10b981;font-weight:700;margin-bottom:15px;display:none;">
            💰 Change: <span id="successChangeAmount"></span>
        </div>
        <button type="button" class="btn-checkout" id="btnNewSale">🛒 New Sale</button>
    </div>
</div>

<script>
// ===== DATA FROM PHP =====
var ALL_PRODUCTS = <?= json_encode($productsList) ?>;
var ALL_CUSTOMERS = <?= json_encode($customersList) ?>;
var CURRENCY = <?= json_encode($currency) ?>;
var TAX_RATE = <?= floatval($taxRate) ?>;
var TAX_ENABLED = <?= $taxEnabled ? 'true' : 'false' ?>;
var BUSINESS_ID = <?= intval($bid) ?>;

var cart = [];
var selectedCustomer = null;
var selectedPayment = 'cash';
var currentCategory = 0;

// ===== RENDER PRODUCTS =====
function renderProducts() {
    var grid = document.getElementById('productsGrid');
    var search = document.getElementById('searchInput').value.toLowerCase().trim();
    var html = '';
    
    for (var i = 0; i < ALL_PRODUCTS.length; i++) {
        var p = ALL_PRODUCTS[i];
        var name = (p.name || '').toLowerCase();
        var sku = (p.sku || '').toLowerCase();
        var barcode = (p.barcode || '').toLowerCase();
        var cat = parseInt(p.category_id) || 0;
        var stock = parseInt(p.stock_quantity);
        var lowStock = parseInt(p.low_stock_threshold);
        
        // Filter by category
        if (currentCategory > 0 && cat !== currentCategory) continue;
        
        // Filter by search
        if (search && !name.includes(search) && !sku.includes(search) && !barcode.includes(search)) continue;
        
        var outOfStock = stock <= 0;
        var isLow = !outOfStock && stock <= lowStock;
        
        var badgeClass = 'stock-badge';
        var badgeText = stock;
        if (outOfStock) { badgeClass += ' out'; badgeText = 'OUT'; }
        else if (isLow) badgeClass += ' low';
        
        var image = p.image_url 
            ? '<img src="' + escapeHtml(p.image_url) + '" alt="" onerror="this.parentElement.innerHTML=\'📦\'">'
            : '📦';
        
        html += '<div class="product-card' + (outOfStock ? ' out-of-stock' : '') + '" data-id="' + p.id + '"' + 
                (outOfStock ? '' : ' onclick="addToCartById(' + p.id + ')"') + '>';
        html += '<span class="' + badgeClass + '">' + badgeText + '</span>';
        html += '<div class="product-image">' + image + '</div>';
        html += '<div class="product-name">' + escapeHtml(p.name) + '</div>';
        html += '<div class="product-price">' + parseFloat(p.selling_price).toFixed(2) + ' ' + CURRENCY + '</div>';
        html += '</div>';
    }
    
    if (html === '') {
        html = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#6b7280;">No products found</div>';
    }
    
    grid.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== CART =====
function addToCartById(productId) {
    var product = null;
    for (var i = 0; i < ALL_PRODUCTS.length; i++) {
        if (parseInt(ALL_PRODUCTS[i].id) === productId) {
            product = ALL_PRODUCTS[i];
            break;
        }
    }
    
    if (!product) return;
    
    var stock = parseInt(product.stock_quantity);
    var existing = null;
    for (var i = 0; i < cart.length; i++) {
        if (cart[i].id === productId) {
            existing = cart[i];
            break;
        }
    }
    
    if (existing) {
        if (existing.qty >= stock) {
            showToast('⚠️ No more stock', 'error');
            return;
        }
        existing.qty++;
    } else {
        cart.push({
            id: productId,
            name: product.name,
            price: parseFloat(product.selling_price),
            stock: stock,
            qty: 1
        });
    }
    
    renderCart();
    if (navigator.vibrate) navigator.vibrate(50);
}

function updateQty(id, change) {
    for (var i = 0; i < cart.length; i++) {
        if (cart[i].id === id) {
            var newQty = cart[i].qty + change;
            if (newQty <= 0) {
                cart.splice(i, 1);
            } else if (newQty > cart[i].stock) {
                showToast('⚠️ Stock limit reached', 'error');
                return;
            } else {
                cart[i].qty = newQty;
            }
            renderCart();
            return;
        }
    }
}

function removeFromCart(id) {
    for (var i = 0; i < cart.length; i++) {
        if (cart[i].id === id) {
            cart.splice(i, 1);
            break;
        }
    }
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    if (!confirm('Clear cart?')) return;
    cart = [];
    selectedCustomer = null;
    document.getElementById('customerBtn').innerHTML = '👤 Add Customer (optional)';
    renderCart();
}

function renderCart() {
    var list = document.getElementById('cartItems');
    var totals = document.getElementById('cartTotals');
    var btn = document.getElementById('btnCheckout');
    var count = document.getElementById('cartCount');
    
    if (cart.length === 0) {
        list.innerHTML = '<div class="cart-empty"><div class="icon">🛒</div><div>Cart is empty</div><p style="font-size:12px;margin-top:8px;">Click products to add</p></div>';
        totals.style.display = 'none';
        btn.disabled = true;
        count.textContent = '';
        return;
    }
    
    var totalItems = 0;
    for (var i = 0; i < cart.length; i++) totalItems += cart[i].qty;
    count.textContent = '(' + totalItems + ')';
    
    var html = '';
    for (var i = 0; i < cart.length; i++) {
        var item = cart[i];
        html += '<div class="cart-item">';
        html += '<div class="cart-item-top">';
        html += '<div class="cart-item-name">' + escapeHtml(item.name) + '</div>';
        html += '<button class="cart-item-remove" onclick="removeFromCart(' + item.id + ')">×</button>';
        html += '</div>';
        html += '<div class="cart-item-bottom">';
        html += '<div class="qty-controls">';
        html += '<button class="qty-btn" onclick="updateQty(' + item.id + ', -1)">−</button>';
        html += '<span class="qty-value">' + item.qty + '</span>';
        html += '<button class="qty-btn" onclick="updateQty(' + item.id + ', 1)"' + (item.qty >= item.stock ? ' disabled' : '') + '>+</button>';
        html += '</div>';
        html += '<div>';
        html += '<div class="cart-item-price">' + (item.price * item.qty).toFixed(2) + ' ' + CURRENCY + '</div>';
        html += '<div class="cart-item-each">' + item.price.toFixed(2) + ' × ' + item.qty + '</div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
    }
    
    list.innerHTML = html;
    totals.style.display = 'block';
    btn.disabled = false;
    
    var subtotal = 0;
    for (var i = 0; i < cart.length; i++) subtotal += cart[i].price * cart[i].qty;
    
    var tax = TAX_ENABLED ? (subtotal * TAX_RATE / 100) : 0;
    var total = subtotal + tax;
    
    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2) + ' ' + CURRENCY;
    if (document.getElementById('taxDisplay')) {
        document.getElementById('taxDisplay').textContent = tax.toFixed(2) + ' ' + CURRENCY;
    }
    document.getElementById('totalDisplay').textContent = total.toFixed(2) + ' ' + CURRENCY;
}

// ===== CUSTOMER =====
function renderCustomers(filter) {
    filter = (filter || '').toLowerCase().trim();
    var list = document.getElementById('customersList');
    var html = '';
    
    for (var i = 0; i < ALL_CUSTOMERS.length; i++) {
        var c = ALL_CUSTOMERS[i];
        var name = (c.name || '').toLowerCase();
        var phone = (c.phone || '').toLowerCase();
        
        if (filter && !name.includes(filter) && !phone.includes(filter)) continue;
        
        html += '<div onclick="selectCustomer(' + c.id + ')" style="background:var(--bg-dark);padding:12px;border-radius:10px;margin-bottom:8px;cursor:pointer;display:flex;align-items:center;gap:12px;">';
        html += '<div style="width:36px;height:36px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;">' + (c.name.charAt(0).toUpperCase()) + '</div>';
        html += '<div>';
        html += '<div style="font-weight:700;">' + escapeHtml(c.name) + '</div>';
        if (c.phone) html += '<div style="font-size:11px;color:#9ca3af;">📞 ' + escapeHtml(c.phone) + '</div>';
        html += '</div>';
        html += '</div>';
    }
    
    if (html === '') html = '<div style="text-align:center;padding:20px;color:#6b7280;">No customers found</div>';
    
    list.innerHTML = html;
}

function selectCustomer(id) {
    for (var i = 0; i < ALL_CUSTOMERS.length; i++) {
        if (parseInt(ALL_CUSTOMERS[i].id) === id) {
            selectedCustomer = ALL_CUSTOMERS[i];
            document.getElementById('customerBtn').innerHTML = '👤 ' + escapeHtml(selectedCustomer.name) + ' ✓';
            closeModal('customerModal');
            return;
        }
    }
}

// ===== MODALS =====
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// ===== CHECKOUT =====
function checkout() {
    if (cart.length === 0) return;
    
    var subtotal = 0;
    for (var i = 0; i < cart.length; i++) subtotal += cart[i].price * cart[i].qty;
    var tax = TAX_ENABLED ? (subtotal * TAX_RATE / 100) : 0;
    var total = subtotal + tax;
    
    if (selectedPayment === 'cash') {
        document.getElementById('paymentTotal').textContent = total.toFixed(2) + ' ' + CURRENCY;
        document.getElementById('cashReceived').value = '';
        document.getElementById('changeDisplay').style.display = 'none';
        openModal('paymentModal');
        setTimeout(function() { document.getElementById('cashReceived').focus(); }, 100);
    } else {
        processSale(total, total, 0);
    }
}

function calculateChange() {
    var total = parseFloat(document.getElementById('paymentTotal').textContent);
    var received = parseFloat(document.getElementById('cashReceived').value) || 0;
    var change = received - total;
    
    if (change >= 0) {
        document.getElementById('changeAmount').textContent = change.toFixed(2) + ' ' + CURRENCY;
        document.getElementById('changeDisplay').style.display = 'block';
    } else {
        document.getElementById('changeDisplay').style.display = 'none';
    }
}

function confirmPayment() {
    var total = parseFloat(document.getElementById('paymentTotal').textContent);
    var received = parseFloat(document.getElementById('cashReceived').value) || 0;
    
    if (received < total) {
        showToast('⚠️ Insufficient amount', 'error');
        return;
    }
    
    processSale(total, received, received - total);
}

function processSale(total, paid, change) {
    var data = {
        action: 'create_sale',
        items: cart,
        customer_id: selectedCustomer ? selectedCustomer.id : null,
        payment_method: selectedPayment,
        paid_amount: paid,
        change_amount: change,
        total: total
    };
    
    fetch('pos_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.text(); })
    .then(function(text) {
        var res;
        try {
            res = JSON.parse(text);
        } catch(e) {
            console.error('Bad response:', text);
            showToast('❌ Server error', 'error');
            return;
        }
        
        if (res.success) {
            closeModal('paymentModal');
            document.getElementById('successAmount').textContent = total.toFixed(2) + ' ' + CURRENCY;
            document.getElementById('successInvoice').textContent = res.invoice_number || '';
            
            if (change > 0) {
                document.getElementById('successChange').style.display = 'block';
                document.getElementById('successChangeAmount').textContent = change.toFixed(2) + ' ' + CURRENCY;
            } else {
                document.getElementById('successChange').style.display = 'none';
            }
            
            openModal('successModal');
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
        } else {
            showToast('❌ ' + (res.message || 'Sale failed'), 'error');
        }
    })
    .catch(function(e) {
        console.error(e);
        showToast('❌ Network error', 'error');
    });
}

function newSale() {
    cart = [];
    selectedCustomer = null;
    closeModal('successModal');
    renderCart();
    document.getElementById('customerBtn').innerHTML = '👤 Add Customer (optional)';
    setTimeout(function() { location.reload(); }, 500);
}

// ===== TOAST =====
function showToast(msg, type) {
    var toast = document.createElement('div');
    toast.className = 'toast' + (type === 'error' ? ' error' : '');
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}

// ===== EVENT LISTENERS =====
document.addEventListener('DOMContentLoaded', function() {
    
    // Search
    document.getElementById('searchInput').addEventListener('input', renderProducts);
    
    // Categories
    var catPills = document.querySelectorAll('.cat-pill');
    for (var i = 0; i < catPills.length; i++) {
        catPills[i].addEventListener('click', function() {
            for (var j = 0; j < catPills.length; j++) catPills[j].classList.remove('active');
            this.classList.add('active');
            currentCategory = parseInt(this.dataset.cat);
            renderProducts();
        });
    }
    
    // Payment methods
    var payBtns = document.querySelectorAll('.payment-btn');
    for (var i = 0; i < payBtns.length; i++) {
        payBtns[i].addEventListener('click', function() {
            if (!this.dataset.method) return;
            for (var j = 0; j < payBtns.length; j++) {
                if (payBtns[j].dataset.method) payBtns[j].classList.remove('active');
            }
            this.classList.add('active');
            selectedPayment = this.dataset.method;
        });
    }
    
    // Buttons
    document.getElementById('btnClearCart').addEventListener('click', clearCart);
    document.getElementById('btnCheckout').addEventListener('click', checkout);
    document.getElementById('customerBtn').addEventListener('click', function() {
        renderCustomers();
        openModal('customerModal');
    });
    document.getElementById('btnCloseCustomer').addEventListener('click', function() { closeModal('customerModal'); });
    document.getElementById('btnCancelPayment').addEventListener('click', function() { closeModal('paymentModal'); });
    document.getElementById('btnConfirmPayment').addEventListener('click', confirmPayment);
    document.getElementById('btnNewSale').addEventListener('click', newSale);
    document.getElementById('cashReceived').addEventListener('input', calculateChange);
    document.getElementById('customerSearch').addEventListener('input', function() {
        renderCustomers(this.value);
    });
    
    // Close modals on backdrop click
    var modals = document.querySelectorAll('.modal');
    for (var i = 0; i < modals.length; i++) {
        modals[i].addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('show');
        });
    }
    
    // Initial render
    renderProducts();
    
    console.log('✅ BizFlow POS Ready');
    console.log('📦 Products loaded:', ALL_PRODUCTS.length);
    console.log('👥 Customers:', ALL_CUSTOMERS.length);
});
</script>
<script src="pwa.js"></script>
</body>
</html>
