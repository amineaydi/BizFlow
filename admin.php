<?php
session_start();
require_once 'db.php';
require_once 'theme.php';

// ✅ Only owners/managers can access (NOT cashiers)
requireLogin();
if (in_array($_SESSION['user_role'] ?? '', ['cashier', 'worker'])) {
    header("Location: pos.php");
    exit;
}

$bid = getBusinessId();
$uid = getUserId();
$business = getBusinessInfo();
$theme = loadCurrentTheme();

if (!$business) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Current tab
$tab = $_GET['tab'] ?? 'dashboard';

// ========================================
// 📊 LOAD QUICK STATS (for header)
// ========================================
$today = date('Y-m-d');

$todayStats = $conn->query("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM sales 
    WHERE business_id = $bid 
    AND DATE(created_at) = '$today'
    AND status = 'completed'
")->fetch_assoc();

$activeOrders = $conn->query("
    SELECT COUNT(*) c FROM sales 
    WHERE business_id = $bid 
    AND status = 'pending'
")->fetch_assoc()['c'];

$lowStock = $conn->query("
    SELECT COUNT(*) c FROM products 
    WHERE business_id = $bid 
    AND stock_quantity <= low_stock_threshold
    AND is_active = 1
")->fetch_assoc()['c'];

$totalProducts = $conn->query("SELECT COUNT(*) c FROM products WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalCustomers = $conn->query("SELECT COUNT(*) c FROM customers WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalStaff = $conn->query("SELECT COUNT(*) c FROM users WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="<?= $theme['primary_color'] ?>">
<title><?= htmlspecialchars($business['name']) ?> · Admin · BizFlow</title>
<link rel="manifest" href="manifest.json">
<?= renderThemeCSS($theme) ?>
<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    background: var(--bg-dark);
    color: var(--text);
    font-family: var(--font-body);
    min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: 100vh;
}

.sidebar {
    background: linear-gradient(180deg, var(--bg-card), #0f1424);
    border-right: 1px solid rgba(255,255,255,0.06);
    padding: 24px 16px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    margin-bottom: 20px;
}

.brand-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.brand-text {
    flex: 1;
    overflow: hidden;
}

.brand-name {
    font-family: var(--font-heading);
    font-size: 18px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.brand-role {
    font-size: 11px;
    color: #9ca3af;
}

.nav-section {
    margin-bottom: 20px;
}

.nav-title {
    font-size: 10px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 700;
    padding: 0 12px;
    margin-bottom: 8px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: 0.2s;
    margin-bottom: 4px;
    position: relative;
}

.nav-item:hover {
    background: rgba(255,255,255,0.04);
    color: white;
}

.nav-item.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 6px 20px rgba(59,130,246,0.3);
}

.nav-icon {
    font-size: 18px;
    width: 22px;
    text-align: center;
}

.nav-badge {
    margin-left: auto;
    background: rgba(239,68,68,0.15);
    color: #ef4444;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 800;
}

.nav-item.active .nav-badge {
    background: rgba(255,255,255,0.2);
    color: white;
}

/* ===== MAIN ===== */
.main {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* TOP BAR */
.topbar {
    background: var(--bg-card);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    padding: 16px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-title {
    font-family: var(--font-heading);
    font-size: 22px;
    font-weight: 700;
}

.live-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16,185,129,0.15);
    color: #10b981;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.live-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%,100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.quick-stat {
    background: var(--bg-dark);
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 12px;
    color: #9ca3af;
}

.quick-stat strong {
    color: var(--primary);
    font-size: 14px;
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--bg-dark);
    padding: 6px 14px 6px 6px;
    border-radius: 30px;
    border: 1px solid rgba(255,255,255,0.06);
}

.user-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
}

.user-name {
    font-size: 13px;
    font-weight: 600;
}

.btn-logout {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
    padding: 8px 14px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

/* ===== CONTENT ===== */
.content {
    padding: 30px;
    flex: 1;
}

.welcome-banner {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 24px;
    color: white;
    position: relative;
    overflow: hidden;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.welcome-title {
    font-family: var(--font-heading);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 6px;
}

.welcome-sub {
    font-size: 14px;
    opacity: 0.9;
}

/* STATS GRID */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    gap: 14px;
    align-items: center;
    transition: 0.2s;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: -20px;
    right: -20px;
    width: 80px;
    height: 80px;
    background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
    opacity: 0.1;
    border-radius: 50%;
}

.stat-card:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
}

.stat-card .icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stat-card .info {
    flex: 1;
}

.stat-label {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
}

.stat-value {
    font-size: 24px;
    font-weight: 800;
    margin-top: 2px;
}

.stat-change {
    font-size: 11px;
    color: #10b981;
    margin-top: 4px;
}

/* CARDS */
.cards-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

.card {
    background: var(--bg-card);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 20px;
    padding: 24px;
}

.card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-title {
    font-family: var(--font-heading);
    font-size: 18px;
    font-weight: 700;
}

.btn-sm {
    background: var(--bg-dark);
    color: white;
    border: 1px solid rgba(255,255,255,0.1);
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
}

.btn-sm:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* QUICK ACTIONS */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.action-btn {
    background: var(--bg-dark);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 14px;
    padding: 16px;
    cursor: pointer;
    text-decoration: none;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    transition: 0.2s;
    text-align: center;
}

.action-btn:hover {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: transparent;
    transform: translateY(-2px);
}

.action-icon {
    font-size: 28px;
}

.action-label {
    font-size: 12px;
    font-weight: 700;
}

/* RECENT SALES */
.sales-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sale-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-dark);
    border-radius: 10px;
}

.sale-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sale-icon {
    width: 36px;
    height: 36px;
    background: rgba(16,185,129,0.15);
    color: #10b981;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.sale-details {
    font-size: 13px;
}

.sale-customer {
    font-weight: 700;
}

.sale-time {
    color: #9ca3af;
    font-size: 11px;
}

.sale-amount {
    font-weight: 800;
    color: var(--accent);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 60px;
    margin-bottom: 12px;
    opacity: 0.4;
}

/* ===== TABLE STYLES ===== */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.data-table th {
    text-align: left;
    padding: 12px;
    background: var(--bg-dark);
    color: #9ca3af;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 13px;
}

.data-table tr:hover td {
    background: rgba(255,255,255,0.02);
}

.btn {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: 0.2s;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59,130,246,0.3);
}

.btn-danger { background: #ef4444; }
.btn-warning { background: #fbbf24; color: #000; }
.btn-success { background: #10b981; }

/* FORM */
.form-group { margin-bottom: 16px; }

.form-group label {
    display: block;
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    margin-bottom: 8px;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-dark);
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: white;
    font-size: 14px;
    font-family: inherit;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .layout { grid-template-columns: 80px 1fr; }
    .sidebar { padding: 20px 8px; }
    .nav-item span:not(.nav-icon):not(.nav-badge),
    .brand-text, .nav-title { display: none; }
    .nav-item { justify-content: center; padding: 12px 8px; }
    .cards-row { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .layout { grid-template-columns: 1fr; }
    .sidebar { display: none; }
    .content { padding: 20px 16px; }
    .topbar { padding: 12px 16px; }
}

::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
</style>
</head>
<body>

<div class="layout">
    
    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        
        <div class="brand">
            <div class="brand-icon"><?= htmlspecialchars($business['logo_emoji'] ?? '🏪') ?></div>
            <div class="brand-text">
                <div class="brand-name"><?= htmlspecialchars($business['name']) ?></div>
                <div class="brand-role">Admin Panel</div>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Overview</div>
            <a href="?tab=dashboard" class="nav-item <?= $tab === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="pos.php" class="nav-item">
                <span class="nav-icon">🛒</span>
                <span>POS Terminal</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Sales</div>
            <a href="?tab=sales" class="nav-item <?= $tab === 'sales' ? 'active' : '' ?>">
                <span class="nav-icon">💰</span>
                <span>All Sales</span>
            </a>
            <a href="?tab=customers" class="nav-item <?= $tab === 'customers' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span>
                <span>Customers</span>
                <?php if ($totalCustomers > 0): ?>
                    <span class="nav-badge"><?= $totalCustomers ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Inventory</div>
            <a href="?tab=products" class="nav-item <?= $tab === 'products' ? 'active' : '' ?>">
                <span class="nav-icon">📦</span>
                <span>Products</span>
                <?php if ($lowStock > 0): ?>
                    <span class="nav-badge"><?= $lowStock ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=categories" class="nav-item <?= $tab === 'categories' ? 'active' : '' ?>">
                <span class="nav-icon">📂</span>
                <span>Categories</span>
            </a>
            <a href="?tab=suppliers" class="nav-item <?= $tab === 'suppliers' ? 'active' : '' ?>">
                <span class="nav-icon">🏢</span>
                <span>Suppliers</span>
            </a>
            <a href="?tab=purchases" class="nav-item <?= $tab === 'purchases' ? 'active' : '' ?>">
                <span class="nav-icon">📥</span>
                <span>Purchases</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Finance</div>
            <a href="?tab=expenses" class="nav-item <?= $tab === 'expenses' ? 'active' : '' ?>">
                <span class="nav-icon">💸</span>
                <span>Expenses</span>
            </a>
            <a href="?tab=reports" class="nav-item <?= $tab === 'reports' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span>Reports</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Management</div>
            <a href="?tab=staff" class="nav-item <?= $tab === 'staff' ? 'active' : '' ?>">
                <span class="nav-icon">👨‍💼</span>
                <span>Staff & PINs</span>
                <?php if ($totalStaff > 0): ?>
                    <span class="nav-badge"><?= $totalStaff ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=settings" class="nav-item <?= $tab === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon">⚙️</span>
                <span>Settings</span>
            </a>
            <a href="?tab=theme" class="nav-item <?= $tab === 'theme' ? 'active' : '' ?>">
                <span class="nav-icon">🎨</span>
                <span>Theme</span>
            </a>
        </div>
    </div>
    
    <!-- ===== MAIN CONTENT ===== -->
    <div class="main">
        
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="topbar-left">
                <div class="page-title">
                    <?php
                    $titles = [
                        'dashboard' => '📊 Dashboard',
                        'sales' => '💰 All Sales',
                        'customers' => '👥 Customers',
                        'products' => '📦 Products',
                        'categories' => '📂 Categories',
                        'suppliers' => '🏢 Suppliers',
                        'purchases' => '📥 Purchases',
                        'expenses' => '💸 Expenses',
                        'reports' => '📈 Reports',
                        'staff' => '👨‍💼 Staff & PINs',
                        'settings' => '⚙️ Settings',
                        'theme' => '🎨 Theme Designer'
                    ];
                    echo $titles[$tab] ?? '📊 Dashboard';
                    ?>
                </div>
                <div class="live-badge">
                    <span class="live-dot"></span>
                    LIVE
                </div>
            </div>
            
            <div class="topbar-right">
                <div class="quick-stat">
                    Today: <strong><?= number_format($todayStats['revenue'], 0) ?> <?= $business['currency_symbol'] ?? 'DT' ?></strong>
                </div>
                
                <div class="user-menu">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
                    <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                </div>
                
                <a href="logout.php" class="btn-logout">🚪 Logout</a>
            </div>
        </div>
        
        <!-- CONTENT -->
        <div class="content">
            
            <?php if ($tab === 'dashboard'): ?>
                
                <!-- ===== DASHBOARD TAB ===== -->
                <div class="welcome-banner">
                    <div class="welcome-content">
                        <div class="welcome-title">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</div>
                        <div class="welcome-sub">Here's what's happening at <?= htmlspecialchars($business['name']) ?> today</div>
                    </div>
                </div>
                
                <!-- STATS -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon" style="background:rgba(16,185,129,0.15);color:#10b981;">💰</div>
                        <div class="info">
                            <div class="stat-label">Today's Revenue</div>
                            <div class="stat-value"><?= number_format($todayStats['revenue'], 0) ?> <?= $business['currency_symbol'] ?? 'DT' ?></div>
                            <div class="stat-change">↗ Live</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;">📦</div>
                        <div class="info">
                            <div class="stat-label">Sales Today</div>
                            <div class="stat-value"><?= $todayStats['count'] ?></div>
                            <div class="stat-change">Real-time</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon" style="background:rgba(251,191,36,0.15);color:#fbbf24;">⚠️</div>
                        <div class="info">
                            <div class="stat-label">Low Stock</div>
                            <div class="stat-value"><?= $lowStock ?></div>
                            <div class="stat-change" style="color:#fbbf24;">Needs attention</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon" style="background:rgba(168,85,247,0.15);color:#a855f7;">📊</div>
                        <div class="info">
                            <div class="stat-label">Products</div>
                            <div class="stat-value"><?= $totalProducts ?></div>
                            <div class="stat-change">In catalog</div>
                        </div>
                    </div>
                </div>
                
                <!-- CARDS ROW -->
                <div class="cards-row">
                    
                    <!-- RECENT SALES -->
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">📋 Recent Sales</div>
                            <a href="?tab=sales" class="btn-sm">View All →</a>
                        </div>
                        
                        <?php
                        $recentSales = $conn->query("
                            SELECT s.*, c.name as customer_name, u.full_name as cashier_name
                            FROM sales s
                            LEFT JOIN customers c ON c.id = s.customer_id
                            LEFT JOIN users u ON u.id = s.user_id
                            WHERE s.business_id = $bid
                            AND s.status = 'completed'
                            ORDER BY s.created_at DESC
                            LIMIT 8
                        ");
                        ?>
                        
                        <?php if ($recentSales->num_rows === 0): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <div>No sales yet today</div>
                                <p style="margin-top:10px;font-size:13px;">Sales will appear here in real-time</p>
                            </div>
                        <?php else: ?>
                            <div class="sales-list">
                                <?php while ($s = $recentSales->fetch_assoc()): ?>
                                    <div class="sale-item">
                                        <div class="sale-info">
                                            <div class="sale-icon">💰</div>
                                            <div class="sale-details">
                                                <div class="sale-customer">
                                                    <?= htmlspecialchars($s['customer_name'] ?? 'Walk-in Customer') ?>
                                                </div>
                                                <div class="sale-time">
                                                    by <?= htmlspecialchars($s['cashier_name'] ?? 'System') ?>
                                                    · <?= date('H:i', strtotime($s['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sale-amount">
                                            <?= number_format($s['total_amount'], 2) ?> <?= $business['currency_symbol'] ?? 'DT' ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- QUICK ACTIONS -->
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">⚡ Quick Actions</div>
                        </div>
                        
                        <div class="quick-actions">
                            <a href="pos.php" class="action-btn">
                                <div class="action-icon">🛒</div>
                                <div class="action-label">New Sale</div>
                            </a>
                            <a href="?tab=products&action=new" class="action-btn">
                                <div class="action-icon">➕</div>
                                <div class="action-label">Add Product</div>
                            </a>
                            <a href="?tab=customers&action=new" class="action-btn">
                                <div class="action-icon">👤</div>
                                <div class="action-label">Add Customer</div>
                            </a>
                            <a href="?tab=staff&action=new" class="action-btn">
                                <div class="action-icon">🔑</div>
                                <div class="action-label">Add Cashier</div>
                            </a>
                            <a href="?tab=expenses&action=new" class="action-btn">
                                <div class="action-icon">💸</div>
                                <div class="action-label">Add Expense</div>
                            </a>
                            <a href="?tab=reports" class="action-btn">
                                <div class="action-icon">📊</div>
                                <div class="action-label">View Reports</div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- TOP PRODUCTS -->
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">🏆 Top Selling Products Today</div>
                    </div>
                    
                    <?php
                    $topProducts = $conn->query("
                        SELECT p.name, p.selling_price, SUM(si.quantity) as sold, SUM(si.total) as revenue
                        FROM sale_items si
                        JOIN sales s ON s.id = si.sale_id
                        JOIN products p ON p.id = si.product_id
                        WHERE s.business_id = $bid
                        AND DATE(s.created_at) = '$today'
                        AND s.status = 'completed'
                        GROUP BY p.id
                        ORDER BY sold DESC
                        LIMIT 5
                    ");
                    ?>
                    
                    <?php if ($topProducts->num_rows === 0): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <div>No sales data yet</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Product</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; while ($p = $topProducts->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if ($rank === 1) echo '🥇';
                                            elseif ($rank === 2) echo '🥈';
                                            elseif ($rank === 3) echo '🥉';
                                            else echo "#$rank"; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                        <td><?= $p['sold'] ?> units</td>
                                        <td style="color:var(--accent);font-weight:800;">
                                            <?= number_format($p['revenue'], 2) ?> <?= $business['currency_symbol'] ?? 'DT' ?>
                                        </td>
                                    </tr>
                                <?php $rank++; endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($tab === 'products'): ?>
                
                <!-- ===== PRODUCTS TAB ===== -->
                <?php include 'tabs/products_tab.php'; ?>
                
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">📦 Products Management</div>
                        <a href="?tab=products&action=new" class="btn">➕ Add Product</a>
                    </div>
                    
                    <?php
                    $products = $conn->query("
                        SELECT p.*, c.name as category_name 
                        FROM products p
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE p.business_id = $bid 
                        ORDER BY p.created_at DESC
                    ");
                    ?>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $products->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                                        <?php if ($p['sku']): ?>
                                            <br><small style="color:#9ca3af;">SKU: <?= htmlspecialchars($p['sku']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                                    <td><strong><?= number_format($p['selling_price'], 2) ?></strong></td>
                                    <td>
                                        <?php 
                                        $stockClass = $p['stock_quantity'] <= $p['low_stock_threshold'] ? 'color:#ef4444' : 'color:#10b981';
                                        ?>
                                        <span style="<?= $stockClass ?>;font-weight:700;">
                                            <?= $p['stock_quantity'] ?> <?= $p['unit'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($p['is_active']): ?>
                                            <span style="color:#10b981;">● Active</span>
                                        <?php else: ?>
                                            <span style="color:#ef4444;">○ Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-sm">✏️ Edit</button>
                                        <button class="btn-sm" style="background:rgba(239,68,68,0.1);color:#ef4444;">🗑️</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($tab === 'staff'): ?>
                
                <!-- ===== STAFF TAB ===== -->
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">👨‍💼 Staff & PIN Management</div>
                        <a href="?tab=staff&action=new" class="btn">➕ Add Cashier</a>
                    </div>
                    
                    <p style="color:#9ca3af;margin-bottom:20px;font-size:13px;">
                        Manage your cashiers and their PINs. Cashiers can only access POS to make sales.
                    </p>
                    
                    <?php
                    $staff = $conn->query("
                        SELECT * FROM users 
                        WHERE business_id = $bid 
                        ORDER BY role, full_name
                    ");
                    ?>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>PIN</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $staff->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                                    <td>
                                        <span style="background:rgba(59,130,246,0.15);color:#60a5fa;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;text-transform:uppercase;">
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($u['pin']): ?>
                                            <code style="background:var(--bg-dark);padding:4px 10px;border-radius:6px;font-size:14px;color:var(--primary);font-weight:700;">
                                                <?= htmlspecialchars($u['pin']) ?>
                                            </code>
                                        <?php else: ?>
                                            <small style="color:#6b7280;">No PIN</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small style="color:#9ca3af;">
                                            <?= $u['last_login'] ? date('M d H:i', strtotime($u['last_login'])) : 'Never' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn-sm">✏️ Edit</button>
                                        <?php if ($u['id'] != $uid): ?>
                                            <button class="btn-sm" style="background:rgba(239,68,68,0.1);color:#ef4444;">🗑️</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php else: ?>
                
                <!-- ===== OTHER TABS PLACEHOLDER ===== -->
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-icon">🚧</div>
                        <h3 style="margin-bottom:10px;">Coming Soon!</h3>
                        <p>This section is being built. Check back soon!</p>
                        <a href="?tab=dashboard" class="btn" style="margin-top:20px;">← Back to Dashboard</a>
                    </div>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- ===== PUSHER REAL-TIME ===== -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
const BUSINESS_ID = <?= $bid ?>;
const USER_NAME = '<?= addslashes($_SESSION['user_name']) ?>';

// 🚀 Pusher real-time
const pusher = new Pusher('692a2afe9b9c204f0136', { cluster: 'eu' });

// Listen for sales
const salesChannel = pusher.subscribe(`business-${BUSINESS_ID}-sales`);
salesChannel.bind('new-sale', (data) => {
    console.log('💰 New sale!', data);
    showNotification('💰 New Sale!', `${data.amount} DT by ${data.cashier}`);
    
    // Auto-refresh after 3 seconds
    setTimeout(() => location.reload(), 3000);
});

// Listen for stock updates
const stockChannel = pusher.subscribe(`business-${BUSINESS_ID}-stock`);
stockChannel.bind('stock-updated', (data) => {
    console.log('📦 Stock update', data);
});

// Listen for alerts
const alertsChannel = pusher.subscribe(`business-${BUSINESS_ID}-alerts`);
alertsChannel.bind('low-stock', (data) => {
    showNotification('⚠️ Low Stock Alert', `${data.product_name} - Only ${data.stock} left!`);
});

// Notification function
function showNotification(title, body) {
    // Toast
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 16px 22px;
        border-radius: 14px;
        font-weight: 600;
        z-index: 9999;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: slideIn 0.4s;
        max-width: 350px;
    `;
    toast.innerHTML = `<strong>${title}</strong><br><small style="opacity:0.9;">${body}</small>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
    
    // Browser notification
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, { body, icon: 'icons/icon-192.png' });
    }
}

// Request notification permission
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

console.log('🟢 BizFlow Admin connected');
console.log('🏪 Business:', BUSINESS_ID);
console.log('👤 User:', USER_NAME);
</script>

<style>
@keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

</body>
</html>
