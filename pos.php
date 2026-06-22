<?php
session_start();
require_once 'db.php';
require_once 'theme.php';

requireLogin();

$bid = getBusinessId();
$uid = getUserId();
$business = getBusinessInfo();
$theme = loadCurrentTheme();
$currency = $business['currency_symbol'] ?? 'DT';

// Get settings
$settings = $conn->query("SELECT * FROM business_settings WHERE business_id = $bid")->fetch_assoc();
$taxRate = floatval($settings['tax_rate'] ?? 0);
$taxEnabled = intval($settings['tax_enabled'] ?? 0);

// Today's stats for this user
$today = date('Y-m-d');
$myStats = $conn->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount),0) as revenue
    FROM sales WHERE business_id = $bid AND user_id = $uid 
    AND DATE(created_at) = '$today' AND status = 'completed'
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="theme-color" content="<?= $theme['primary_color'] ?>">
<title>POS · <?= htmlspecialchars($business['name']) ?></title>
<link rel="manifest" href="manifest.json">
<?= renderThemeCSS($theme) ?>
<style>
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; user-select:none; }

body {
    background: var(--bg-dark);
    color: var(--text);
    font-family: var(--font-body);
    height: 100vh;
    overflow: hidden;
}

/* ===== LAYOUT ===== */
.pos-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    height: 100vh;
}

/* ===== LEFT: PRODUCTS ===== */
.products-side {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* TOP BAR */
.top-bar {
    background: var(--bg-card);
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
}

.brand-name {
    font-family: var(--font-heading);
    font-size: 18px;
    font-weight: 700;
}

.brand-sub { font-size: 11px; color: #9ca3af; }

.top-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-dark);
    padding: 6px 14px 6px 6px;
    border-radius: 25px;
    font-size: 12px;
    font-weight: 600;
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
    font-size: 12px;
    font-weight: 700;
}

.icon-btn {
    background: var(--bg-dark);
    border: 1px solid rgba(255,255,255,0.06);
    width: 38px; height: 38px;
    border-radius: 10px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    text-decoration: none;
}

.icon-btn:hover { background: var(--primary); }

/* SEARCH + CATEGORIES */
.search-row {
    padding: 16px 20px;
    background: var(--bg-card);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    background: var(--bg-dark);
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    padding: 12px 18px 12px 44px;
    color: white;
    font-size: 14px;
    font-family: inherit;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%239ca3af' width='18' height='18'%3E%3Cpath d='M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 14px center;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
}

.scan-btn {
    background: var(--bg-dark);
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    width: 48px;
    color: white;
    cursor: pointer;
    font-size: 20px;
}

.scan-btn:hover { background: var(--primary); border-color: var(--primary); }

/* CATEGORIES SCROLL */
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
    transition: 0.2s;
    border: none;
    font-family: inherit;
}

.cat-pill.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
}

/* PRODUCTS GRID */
.products-grid-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
}

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

.product-card:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
    box-shadow: 0 8px 20px rgba(59,130,246,0.2);
}

.product-card:active {
    transform: scale(0.97);
}

.product-card.out-of-stock {
    opacity: 0.4;
    cursor: not-allowed;
}

.product-card.out-of-stock:hover {
    transform: none;
    border-color: rgba(255,255,255,0.06);
    box-shadow: none;
}

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

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

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
    top: 8px;
    right: 8px;
    background: rgba(16,185,129,0.9);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
}

.stock-badge.low {
    background: rgba(251,191,36,0.9);
    color: #000;
}

.stock-badge.out {
    background: rgba(239,68,68,0.9);
}

/* ===== RIGHT: CART ===== */
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

/* CUSTOMER SECTION */
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.customer-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.customer-selected {
    background: rgba(59,130,246,0.1);
    border: 1px solid var(--primary);
    padding: 10px;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.customer-info { display: flex; align-items: center; gap: 10px; }

.customer-avatar {
    width: 32px; height: 32px;
    background: var(--primary);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 13px;
}

.customer-name { font-size: 13px; font-weight: 700; }
.customer-phone { font-size: 11px; color: #9ca3af; }

/* CART ITEMS */
.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px;
}

.cart-empty {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.cart-empty .icon {
    font-size: 60px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.cart-item {
    background: var(--bg-dark);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 8px;
    animation: slideIn 0.2s;
}

@keyframes slideIn {
    from { transform: translateX(20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.cart-item-top {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 8px;
}

.cart-item-name {
    font-size: 13px;
    font-weight: 700;
    flex: 1;
}

.cart-item-remove {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 18px;
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
    border: none;
    border-radius: 6px;
    color: white;
    font-size: 16px;
    cursor: pointer;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qty-btn:hover { background: var(--primary); }
.qty-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.qty-value {
    min-width: 30px;
    text-align: center;
    font-weight: 800;
    font-size: 14px;
}

.cart-item-price {
    font-weight: 800;
    color: var(--primary);
    font-size: 15px;
}

.cart-item-each {
    font-size: 11px;
    color: #6b7280;
}

/* TOTALS */
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

/* CHECKOUT */
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
    box-shadow: 0 10px 25px rgba(59,130,246,0.3);
}

.btn-checkout:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
}

/* MODALS */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(10px);
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

.form-group { margin-bottom: 14px; }

.form-input, .form-select {
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

label {
    display: block;
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    margin-bottom: 6px;
}

/* TOAST */
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
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: slideIn 0.3s;
    max-width: 350px;
}

.toast.error { background: linear-gradient(135deg, #ef4444, #dc2626); }

/* SUCCESS MODAL */
.success-modal {
    text-align: center;
}

.success-icon {
    font-size: 80px;
    margin-bottom: 15px;
    animation: bounce 0.5s;
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.success-amount {
    font-size: 36px;
    font-weight: 800;
    color: var(--accent);
    margin: 15px 0;
}

.cash-input-wrap {
    margin: 20px 0;
}

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

/* RESPONSIVE */
@media (max-width: 1024px) {
    .pos-layout { grid-template-columns: 1fr 350px; }
    .products-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}

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
        box-shadow: -10px 0 30px rgba(0,0,0,0.5);
    }
    .cart-side.open { right: 0; }
    .cart-toggle {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 10px 30px rgba(59,130,246,0.4);
        z-index: 100;
    }
    .cart-toggle-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        min-width: 24px;
        height: 24px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

@media (min-width: 769px) {
    .cart-toggle { display: none; }
}

::-webkit-scrollbar { width: 8px; height: 6px; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
</style>
</head>
<body>

<div class="pos-layout">
    
    <!-- ===== LEFT: PRODUCTS ===== -->
    <div class="products-side">
        
        <!-- TOP BAR -->
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
        
        <!-- SEARCH -->
        <div class="search-row">
            <input type="text" id="searchInput" class="search-input" placeholder="Search products by name, SKU or barcode..." autofocus>
            <button class="scan-btn" title="Scan barcode" onclick="alert('Barcode scanner: Use phone camera!')">📷</button>
        </div>
        
        <!-- CATEGORIES -->
        <div class="cats-scroll" id="catsScroll">
            <button class="cat-pill active" onclick="filterCategory(0, this)">
                🏠 All
            </button>
            <?php
            $cats = $conn->query("SELECT * FROM categories WHERE business_id = $bid AND is_active = 1 ORDER BY name");
            while ($c = $cats->fetch_assoc()):
            ?>
                <button class="cat-pill" onclick="filterCategory(<?= $c['id'] ?>, this)">
                    <?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?>
                </button>
            <?php endwhile; ?>
        </div>
        
        <!-- PRODUCTS GRID -->
        <div class="products-grid-wrap">
            <div class="products-grid" id="productsGrid">
                <?php
                $products = $conn->query("
                    SELECT * FROM products 
                    WHERE business_id = $bid AND is_active = 1 
                    ORDER BY sold_count DESC, name
                ");
                while ($p = $products->fetch_assoc()):
                    $outOfStock = $p['stock_quantity'] <= 0;
                    $lowStock = !$outOfStock && $p['stock_quantity'] <= $p['low_stock_threshold'];
                ?>
                    <div class="product-card <?= $outOfStock ? 'out-of-stock' : '' ?>" 
                         data-id="<?= $p['id'] ?>"
                         data-category="<?= $p['category_id'] ?? 0 ?>"
                         data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
                         data-sku="<?= htmlspecialchars(strtolower($p['sku'] ?? '')) ?>"
                         data-barcode="<?= htmlspecialchars($p['barcode'] ?? '') ?>"
                         <?= $outOfStock ? '' : 'onclick="addToCart(' . htmlspecialchars(json_encode([
                             'id' => $p['id'],
                             'name' => $p['name'],
                             'price' => floatval($p['selling_price']),
                             'stock' => $p['stock_quantity']
                         ])) . ')"' ?>>
                        
                        <?php if ($outOfStock): ?>
                            <span class="stock-badge out">OUT</span>
                        <?php elseif ($lowStock): ?>
                            <span class="stock-badge low"><?= $p['stock_quantity'] ?></span>
                        <?php else: ?>
                            <span class="stock-badge"><?= $p['stock_quantity'] ?></span>
                        <?php endif; ?>
                        
                        <div class="product-image">
                            <?php if ($p['image_url']): ?>
                                <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="" onerror="this.style.display='none'">
                            <?php else: ?>
                                📦
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="product-price"><?= number_format($p['selling_price'], 2) ?> <?= $currency ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    
    <!-- ===== RIGHT: CART ===== -->
    <div class="cart-side" id="cartSide">
        
        <div class="cart-header">
            <div class="cart-title">🛒 Cart <span id="cartCount" style="color:#9ca3af;font-size:13px;font-weight:600;"></span></div>
            <button class="cart-clear" onclick="clearCart()">Clear</button>
        </div>
        
        <!-- CUSTOMER -->
        <div class="customer-section">
            <button class="customer-btn" id="customerBtn" onclick="openModal('customerModal')">
                👤 Add Customer (optional)
            </button>
        </div>
        
        <!-- CART ITEMS -->
        <div class="cart-items" id="cartItems">
            <div class="cart-empty">
                <div class="icon">🛒</div>
                <div>Cart is empty</div>
                <p style="font-size:12px;margin-top:8px;">Click products to add</p>
            </div>
        </div>
        
        <!-- TOTALS -->
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
            <div class="total-row">
                <span>Discount</span>
                <span id="discountDisplay">0.00 <?= $currency ?></span>
            </div>
            <div class="total-row grand">
                <span>TOTAL</span>
                <span id="totalDisplay">0.00 <?= $currency ?></span>
            </div>
        </div>
        
        <!-- CHECKOUT -->
        <div class="checkout-section">
            <div class="payment-methods">
                <button class="payment-btn active" data-method="cash" onclick="selectPayment('cash', this)">
                    💵 Cash
                </button>
                <button class="payment-btn" data-method="card" onclick="selectPayment('card', this)">
                    💳 Card
                </button>
            </div>
            
            <button class="btn-checkout" id="checkoutBtn" onclick="checkout()" disabled>
                💳 Checkout
            </button>
        </div>
    </div>
</div>

<!-- Mobile cart toggle -->
<button class="cart-toggle" onclick="toggleCart()">
    🛒
    <span class="cart-toggle-badge" id="mobileCartBadge" style="display:none;">0</span>
</button>

<!-- ===== MODALS ===== -->

<!-- CUSTOMER MODAL -->
<div class="modal" id="customerModal">
    <div class="modal-content">
        <div class="modal-title">👤 Select Customer</div>
        
        <input type="text" class="form-input" placeholder="🔍 Search customers..." id="customerSearch" onkeyup="filterCustomers()" style="margin-bottom:15px;">
        
        <div id="customersList" style="max-height:300px;overflow-y:auto;">
            <?php
            $customers = $conn->query("SELECT * FROM customers WHERE business_id = $bid AND is_active = 1 ORDER BY name");
            while ($c = $customers->fetch_assoc()):
            ?>
                <div class="customer-item" 
                     data-name="<?= htmlspecialchars(strtolower($c['name'])) ?>"
                     data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>"
                     onclick='selectCustomer(<?= htmlspecialchars(json_encode([
                         "id" => $c["id"],
                         "name" => $c["name"],
                         "phone" => $c["phone"]
                     ])) ?>)'
                     style="background:var(--bg-dark);padding:12px;border-radius:10px;margin-bottom:8px;cursor:pointer;display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;">
                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:700;"><?= htmlspecialchars($c['name']) ?></div>
                        <?php if ($c['phone']): ?>
                            <div style="font-size:11px;color:#9ca3af;">📞 <?= htmlspecialchars($c['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <button class="btn-checkout" style="margin-top:15px;background:rgba(255,255,255,0.1);" onclick="closeModal('customerModal')">Cancel</button>
    </div>
</div>

<!-- PAYMENT MODAL -->
<div class="modal" id="paymentModal">
    <div class="modal-content" style="max-width:400px;text-align:center;">
        <div class="modal-title" style="text-align:center;">💵 Complete Sale</div>
        
        <div style="font-size:14px;color:#9ca3af;">Total to pay:</div>
        <div style="font-size:36px;font-weight:800;color:var(--primary);margin:10px 0 20px;" id="paymentTotal">0.00 <?= $currency ?></div>
        
        <div id="cashPaymentSection">
            <label>Cash Received</label>
            <input type="number" step="0.01" class="cash-input" id="cashReceived" placeholder="0.00" oninput="calculateChange()">
            
            <div class="change-display" id="changeDisplay" style="display:none;">
                Change: <span id="changeAmount">0.00 <?= $currency ?></span>
            </div>
        </div>
        
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button class="payment-btn" style="flex:1;" onclick="closeModal('paymentModal')">Cancel</button>
            <button class="btn-checkout" style="flex:1;margin:0;" onclick="confirmPayment()">✅ Confirm</button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div class="modal" id="successModal">
    <div class="modal-content success-modal" style="max-width:400px;">
        <div class="success-icon">🎉</div>
        <div class="modal-title" style="text-align:center;">Sale Complete!</div>
        <div class="success-amount" id="successAmount">0.00 <?= $currency ?></div>
        <div style="color:#9ca3af;margin-bottom:15px;">Invoice: <strong id="successInvoice"></strong></div>
        <div id="successChange" style="background:rgba(16,185,129,0.1);padding:14px;border-radius:10px;color:#10b981;font-weight:700;margin-bottom:15px;display:none;">
            💰 Change: <span id="successChangeAmount"></span>
        </div>
        <button class="btn-checkout" onclick="newSale()">🛒 New Sale</button>
    </div>
</div>

<script>
const BUSINESS_ID = <?= $bid ?>;
const USER_ID = <?= $uid ?>;
const USER_NAME = '<?= addslashes($_SESSION['user_name']) ?>';
const CURRENCY = '<?= $currency ?>';
const TAX_RATE = <?= $taxRate ?>;
const TAX_ENABLED = <?= $taxEnabled ? 'true' : 'false' ?>;

let cart = [];
let selectedCustomer = null;
let selectedPayment = 'cash';
let currentSaleData = null;

// ===== SEARCH =====
document.getElementById('searchInput').addEventListener('input', filterProducts);

function filterProducts() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name;
        const sku = card.dataset.sku;
        const barcode = card.dataset.barcode;
        const matches = !q || name.includes(q) || sku.includes(q) || barcode.includes(q);
        card.style.display = matches ? '' : 'none';
    });
}

// ===== CATEGORY FILTER =====
function filterCategory(catId, btn) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('.product-card').forEach(card => {
        const cat = parseInt(card.dataset.category);
        const show = catId === 0 || cat === catId;
        card.style.display = show ? '' : 'none';
    });
}

// ===== CART =====
function addToCart(product) {
    const existing = cart.find(i => i.id === product.id);
    
    if (existing) {
        if (existing.qty >= product.stock) {
            showToast('⚠️ No more stock available', 'error');
            return;
        }
        existing.qty++;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            stock: product.stock,
            qty: 1
        });
    }
    
    renderCart();
    
    // Haptic feedback
    if (navigator.vibrate) navigator.vibrate(50);
}

function updateQty(id, change) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    
    const newQty = item.qty + change;
    
    if (newQty <= 0) {
        cart = cart.filter(i => i.id !== id);
    } else if (newQty > item.stock) {
        showToast('⚠️ Stock limit reached', 'error');
        return;
    } else {
        item.qty = newQty;
    }
    
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    if (!confirm('Clear all items from cart?')) return;
    
    cart = [];
    selectedCustomer = null;
    document.getElementById('customerBtn').innerHTML = '👤 Add Customer (optional)';
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartItems');
    const totals = document.getElementById('cartTotals');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const count = document.getElementById('cartCount');
    const mobileBadge = document.getElementById('mobileCartBadge');
    
    if (cart.length === 0) {
        list.innerHTML = `
            <div class="cart-empty">
                <div class="icon">🛒</div>
                <div>Cart is empty</div>
                <p style="font-size:12px;margin-top:8px;">Click products to add</p>
            </div>`;
        totals.style.display = 'none';
        checkoutBtn.disabled = true;
        count.textContent = '';
        mobileBadge.style.display = 'none';
        return;
    }
    
    const totalItems = cart.reduce((sum, i) => sum + i.qty, 0);
    count.textContent = `(${totalItems})`;
    mobileBadge.textContent = totalItems;
    mobileBadge.style.display = 'flex';
    
    list.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div class="cart-item-top">
                <div class="cart-item-name">${item.name}</div>
                <button class="cart-item-remove" onclick="removeFromCart(${item.id})">×</button>
            </div>
            <div class="cart-item-bottom">
                <div class="qty-controls">
                    <button class="qty-btn" onclick="updateQty(${item.id}, -1)">−</button>
                    <span class="qty-value">${item.qty}</span>
                    <button class="qty-btn" onclick="updateQty(${item.id}, 1)" ${item.qty >= item.stock ? 'disabled' : ''}>+</button>
                </div>
                <div>
                    <div class="cart-item-price">${(item.price * item.qty).toFixed(2)} ${CURRENCY}</div>
                    <div class="cart-item-each">${item.price.toFixed(2)} × ${item.qty}</div>
                </div>
            </div>
        </div>
    `).join('');
    
    totals.style.display = 'block';
    checkoutBtn.disabled = false;
    
    // Calculate totals
    const subtotal = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
    const tax = TAX_ENABLED ? (subtotal * TAX_RATE / 100) : 0;
    const discount = 0; // Future: discount codes
    const total = subtotal + tax - discount;
    
    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2) + ' ' + CURRENCY;
    if (document.getElementById('taxDisplay')) {
        document.getElementById('taxDisplay').textContent = tax.toFixed(2) + ' ' + CURRENCY;
    }
    document.getElementById('discountDisplay').textContent = discount.toFixed(2) + ' ' + CURRENCY;
    document.getElementById('totalDisplay').textContent = total.toFixed(2) + ' ' + CURRENCY;
}

// ===== CUSTOMER =====
function selectCustomer(c) {
    selectedCustomer = c;
    document.getElementById('customerBtn').outerHTML = `
        <div class="customer-selected">
            <div class="customer-info">
                <div class="customer-avatar">${c.name.charAt(0).toUpperCase()}</div>
                <div>
                    <div class="customer-name">${c.name}</div>
                    ${c.phone ? `<div class="customer-phone">📞 ${c.phone}</div>` : ''}
                </div>
            </div>
            <button onclick="removeCustomer()" style="background:none;border:none;color:#ef4444;font-size:20px;cursor:pointer;">×</button>
        </div>`;
    closeModal('customerModal');
}

function removeCustomer() {
    selectedCustomer = null;
    document.querySelector('.customer-section').innerHTML = `
        <button class="customer-btn" id="customerBtn" onclick="openModal('customerModal')">
            👤 Add Customer (optional)
        </button>`;
}

function filterCustomers() {
    const q = document.getElementById('customerSearch').value.toLowerCase().trim();
    document.querySelectorAll('.customer-item').forEach(item => {
        const name = item.dataset.name;
        const phone = item.dataset.phone;
        const matches = !q || name.includes(q) || phone.includes(q);
        item.style.display = matches ? '' : 'none';
    });
}

// ===== PAYMENT =====
function selectPayment(method, btn) {
    selectedPayment = method;
    document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// ===== CHECKOUT =====
function checkout() {
    if (cart.length === 0) return;
    
    const subtotal = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
    const tax = TAX_ENABLED ? (subtotal * TAX_RATE / 100) : 0;
    const total = subtotal + tax;
    
    if (selectedPayment === 'cash') {
        // Show cash modal
        document.getElementById('paymentTotal').textContent = total.toFixed(2) + ' ' + CURRENCY;
        document.getElementById('cashReceived').value = '';
        document.getElementById('changeDisplay').style.display = 'none';
        document.getElementById('cashReceived').focus();
        openModal('paymentModal');
    } else {
        // Direct card payment
        processSale(total, total, 0);
    }
}

function calculateChange() {
    const total = parseFloat(document.getElementById('paymentTotal').textContent);
    const received = parseFloat(document.getElementById('cashReceived').value) || 0;
    const change = received - total;
    
    if (change >= 0) {
        document.getElementById('changeAmount').textContent = change.toFixed(2) + ' ' + CURRENCY;
        document.getElementById('changeDisplay').style.display = 'block';
    } else {
        document.getElementById('changeDisplay').style.display = 'none';
    }
}

function confirmPayment() {
    const total = parseFloat(document.getElementById('paymentTotal').textContent);
    const received = parseFloat(document.getElementById('cashReceived').value) || 0;
    
    if (received < total) {
        showToast('⚠️ Insufficient amount', 'error');
        return;
    }
    
    const change = received - total;
    processSale(total, received, change);
}

function processSale(total, paid, change) {
    const data = {
        items: cart,
        customer_id: selectedCustomer?.id || null,
        payment_method: selectedPayment,
        paid_amount: paid,
        change_amount: change,
        total: total
    };
    
    fetch('pos_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'create_sale', ...data})
    })
    .then(r => {
        if (!r.ok) throw new Error('Network error');
        return r.text();  // Get as text first
    })
    .then(text => {
        // Try to parse as JSON
        let res;
        try {
            res = JSON.parse(text);
        } catch (e) {
            console.error('Server response:', text);
            throw new Error('Invalid response from server');
        }
        
        if (res.success) {
            closeModal('paymentModal');
            
            document.getElementById('successAmount').textContent = total.toFixed(2) + ' ' + CURRENCY;
            document.getElementById('successInvoice').textContent = res.invoice_number;
            
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
    .catch(e => {
        console.error('Sale error:', e);
        showToast('❌ ' + e.message, 'error');
    });
}
    .then(res => {
        if (res.success) {
            closeModal('paymentModal');
            
            // Show success
            document.getElementById('successAmount').textContent = total.toFixed(2) + ' ' + CURRENCY;
            document.getElementById('successInvoice').textContent = res.invoice_number;
            
            if (change > 0) {
                document.getElementById('successChange').style.display = 'block';
                document.getElementById('successChangeAmount').textContent = change.toFixed(2) + ' ' + CURRENCY;
            } else {
                document.getElementById('successChange').style.display = 'none';
            }
            
            openModal('successModal');
            
            // Vibrate
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
        } else {
            showToast('❌ ' + (res.message || 'Sale failed'), 'error');
        }
    })
    .catch(e => {
        console.error(e);
        showToast('❌ Network error', 'error');
    });
}

function newSale() {
    cart = [];
    selectedCustomer = null;
    closeModal('successModal');
    renderCart();
    removeCustomer();
    document.getElementById('searchInput').focus();
    
    // Reload to update stock badges
    setTimeout(() => location.reload(), 500);
}

// ===== MODALS =====
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => {
        if (e.target === m) closeModal(m.id);
    });
});

// ===== MOBILE CART =====
function toggleCart() {
    document.getElementById('cartSide').classList.toggle('open');
}

// ===== TOAST =====
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', e => {
    // ESC to close modals
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(m => closeModal(m.id));
    }
    
    // F2 to checkout
    if (e.key === 'F2' && cart.length > 0) {
        e.preventDefault();
        checkout();
    }
    
    // F3 to focus search
    if (e.key === 'F3') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});

console.log('🟢 BizFlow POS Loaded');
console.log('💼 Business:', BUSINESS_ID);
console.log('👤 User:', USER_NAME);
console.log('⌨️ Shortcuts: F2=Checkout, F3=Search, ESC=Close');
</script>

</body>
</html>
