<?php
session_start();
require_once 'db.php';
require_once 'theme.php';

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

$today = date('Y-m-d');

// ========================================
// 📊 LOAD ALL DATA AT ONCE
// ========================================

// Today's stats
$todayStats = $conn->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
    FROM sales WHERE business_id = $bid AND DATE(created_at) = '$today' AND status = 'completed'
")->fetch_assoc();

$totalProducts = $conn->query("SELECT COUNT(*) c FROM products WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalCustomers = $conn->query("SELECT COUNT(*) c FROM customers WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalStaff = $conn->query("SELECT COUNT(*) c FROM users WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalSuppliers = $conn->query("SELECT COUNT(*) c FROM suppliers WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$lowStock = $conn->query("SELECT COUNT(*) c FROM products WHERE business_id = $bid AND stock_quantity <= low_stock_threshold AND is_active = 1")->fetch_assoc()['c'];

// Month stats
$monthStart = date('Y-m-01');
$monthStats = $conn->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
    FROM sales WHERE business_id = $bid AND DATE(created_at) >= '$monthStart' AND status = 'completed'
")->fetch_assoc();

// Monthly expenses
$monthExpenses = $conn->query("
    SELECT COALESCE(SUM(amount), 0) t FROM expenses WHERE business_id = $bid AND expense_date >= '$monthStart'
")->fetch_assoc()['t'];

$monthProfit = $monthStats['revenue'] - $monthExpenses;
$currency = $business['currency_symbol'] ?? 'DT';
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
    width: 44px; height: 44px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
}

.brand-text { flex: 1; overflow: hidden; }
.brand-name {
    font-family: var(--font-heading);
    font-size: 18px; font-weight: 700;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.brand-role { font-size: 11px; color: #9ca3af; }

.nav-section { margin-bottom: 20px; }
.nav-title {
    font-size: 10px; color: #6b7280;
    text-transform: uppercase; letter-spacing: 1.5px;
    font-weight: 700; padding: 0 12px; margin-bottom: 8px;
}

.nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px; border-radius: 10px;
    color: #94a3b8; text-decoration: none;
    font-size: 14px; font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    margin-bottom: 4px;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
}

.nav-item:hover { background: rgba(255,255,255,0.04); color: white; }

.nav-item.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 6px 20px rgba(59,130,246,0.3);
}

.nav-icon { font-size: 18px; width: 22px; text-align: center; }

.nav-badge {
    margin-left: auto;
    background: rgba(239,68,68,0.15); color: #ef4444;
    padding: 2px 8px; border-radius: 10px;
    font-size: 10px; font-weight: 800;
}

.nav-item.active .nav-badge { background: rgba(255,255,255,0.2); color: white; }

/* ===== MAIN ===== */
.main { display: flex; flex-direction: column; min-height: 100vh; }

.topbar {
    background: var(--bg-card);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    padding: 16px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky; top: 0; z-index: 100;
}

.topbar-left { display: flex; align-items: center; gap: 14px; }

.page-title {
    font-family: var(--font-heading);
    font-size: 22px; font-weight: 700;
}

.live-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(16,185,129,0.15); color: #10b981;
    padding: 5px 12px; border-radius: 20px;
    font-size: 11px; font-weight: 700;
}

.live-dot {
    width: 8px; height: 8px; background: #10b981;
    border-radius: 50%; animation: pulse 2s infinite;
}

@keyframes pulse {
    0%,100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

.topbar-right { display: flex; align-items: center; gap: 12px; }

.quick-stat {
    background: var(--bg-dark);
    padding: 8px 14px; border-radius: 10px;
    font-size: 12px; color: #9ca3af;
}
.quick-stat strong { color: var(--primary); font-size: 14px; }

.user-menu {
    display: flex; align-items: center; gap: 10px;
    background: var(--bg-dark);
    padding: 6px 14px 6px 6px;
    border-radius: 30px;
    border: 1px solid rgba(255,255,255,0.06);
}

.user-avatar {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 13px;
}

.user-name { font-size: 13px; font-weight: 600; }

.btn-logout {
    background: rgba(239,68,68,0.1); color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
    padding: 8px 14px; border-radius: 10px;
    text-decoration: none; font-size: 12px; font-weight: 600;
}

/* ===== CONTENT ===== */
.content { padding: 30px; flex: 1; }

/* TABS */
.tab-content { display: none; animation: fadeIn 0.3s; }
.tab-content.active { display: block; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* WELCOME */
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
    top: -50%; right: -20%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.welcome-content { position: relative; z-index: 1; }
.welcome-title { font-family: var(--font-heading); font-size: 28px; font-weight: 700; margin-bottom: 6px; }
.welcome-sub { font-size: 14px; opacity: 0.9; }

/* STATS */
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
    display: flex; gap: 14px; align-items: center;
    transition: 0.2s;
    position: relative; overflow: hidden;
}

.stat-card:hover { transform: translateY(-3px); border-color: var(--primary); }

.stat-card .icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
}

.stat-card .info { flex: 1; }
.stat-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
.stat-value { font-size: 24px; font-weight: 800; margin-top: 2px; }
.stat-change { font-size: 11px; color: #10b981; margin-top: 4px; }

/* CARDS */
.cards-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }

.card {
    background: var(--bg-card);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
}

.card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-title {
    font-family: var(--font-heading);
    font-size: 18px; font-weight: 700;
}

/* TABLE */
.data-table { width: 100%; border-collapse: collapse; }

.data-table th {
    text-align: left; padding: 12px;
    background: var(--bg-dark);
    color: #9ca3af;
    font-size: 11px; text-transform: uppercase;
    letter-spacing: 0.5px; font-weight: 700;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 13px;
}

.data-table tr:hover td { background: rgba(255,255,255,0.02); }

/* BUTTONS */
.btn {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white; border: none;
    padding: 12px 20px; border-radius: 10px;
    font-weight: 700; cursor: pointer;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center; gap: 8px;
    transition: 0.2s;
    font-family: inherit;
}

.btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,0.3); }
.btn-danger { background: #ef4444; }
.btn-warning { background: #fbbf24; color: #000; }
.btn-success { background: #10b981; }

.btn-sm {
    padding: 6px 12px; border-radius: 8px;
    font-size: 11px; font-weight: 600;
    border: 1px solid rgba(255,255,255,0.1);
    background: var(--bg-dark);
    color: white; cursor: pointer;
    margin-right: 4px;
    font-family: inherit;
}

.btn-sm:hover { border-color: var(--primary); color: var(--primary); }

/* FORMS */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { margin-bottom: 16px; }

.form-group label {
    display: block;
    font-size: 11px; color: #9ca3af;
    text-transform: uppercase; letter-spacing: 1px;
    font-weight: 700; margin-bottom: 8px;
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
    outline: none; border-color: var(--primary);
}

.form-textarea { min-height: 80px; resize: vertical; }

select.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%233b82f6' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 40px;
}

/* MODAL */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
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
    border: 1px solid rgba(255,255,255,0.1);
}

.modal-title {
    font-family: var(--font-heading);
    font-size: 22px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    margin-left: auto;
}

/* ALERT */
.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 600;
    animation: slideDown 0.3s;
}
.alert.success { background: rgba(16,185,129,0.15); color: #86efac; border: 1px solid rgba(16,185,129,0.3); }
.alert.error { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }

@keyframes slideDown {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* EMPTY STATE */
.empty-state { text-align: center; padding: 60px 20px; color: #6b7280; }
.empty-icon { font-size: 80px; margin-bottom: 20px; opacity: 0.5; }

/* QUICK ACTIONS */
.quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }

.action-btn {
    background: var(--bg-dark);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 14px;
    padding: 16px;
    cursor: pointer;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    transition: 0.2s;
    text-align: center;
    text-decoration: none;
    font-family: inherit;
}

.action-btn:hover {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: transparent;
    transform: translateY(-2px);
}

.action-icon { font-size: 28px; }
.action-label { font-size: 12px; font-weight: 700; }

/* SALES LIST */
.sales-list { display: flex; flex-direction: column; gap: 10px; }

.sale-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-dark);
    border-radius: 10px;
}

.sale-info { display: flex; align-items: center; gap: 12px; }

.sale-icon {
    width: 36px; height: 36px;
    background: rgba(16,185,129,0.15);
    color: #10b981;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
}

.sale-details { font-size: 13px; }
.sale-customer { font-weight: 700; }
.sale-time { color: #9ca3af; font-size: 11px; }
.sale-amount { font-weight: 800; color: var(--accent); }

/* CATEGORY BADGE */
.cat-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
}

/* STATUS BADGES */
.status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-active { background: rgba(16,185,129,0.15); color: #10b981; }
.status-inactive { background: rgba(239,68,68,0.15); color: #ef4444; }
.status-low { background: rgba(251,191,36,0.15); color: #fbbf24; }

/* RESPONSIVE */
@media (max-width: 1024px) {
    .layout { grid-template-columns: 80px 1fr; }
    .sidebar { padding: 20px 8px; }
    .nav-item span:not(.nav-icon):not(.nav-badge),
    .brand-text, .nav-title { display: none; }
    .nav-item { justify-content: center; padding: 12px 8px; }
    .cards-row { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
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
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
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
            <button class="nav-item active" onclick="switchTab('dashboard', this)">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </button>
            <a href="pos.php" class="nav-item">
                <span class="nav-icon">🛒</span>
                <span>POS Terminal</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Sales</div>
            <button class="nav-item" onclick="switchTab('sales', this)">
                <span class="nav-icon">💰</span>
                <span>All Sales</span>
            </button>
            <button class="nav-item" onclick="switchTab('customers', this)">
                <span class="nav-icon">👥</span>
                <span>Customers</span>
                <?php if ($totalCustomers > 0): ?>
                    <span class="nav-badge"><?= $totalCustomers ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Inventory</div>
            <button class="nav-item" onclick="switchTab('products', this)">
                <span class="nav-icon">📦</span>
                <span>Products</span>
                <?php if ($lowStock > 0): ?>
                    <span class="nav-badge"><?= $lowStock ?></span>
                <?php endif; ?>
            </button>
            <button class="nav-item" onclick="switchTab('categories', this)">
                <span class="nav-icon">📂</span>
                <span>Categories</span>
            </button>
            <button class="nav-item" onclick="switchTab('suppliers', this)">
                <span class="nav-icon">🏢</span>
                <span>Suppliers</span>
            </button>
            <button class="nav-item" onclick="switchTab('purchases', this)">
                <span class="nav-icon">📥</span>
                <span>Purchases</span>
            </button>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Finance</div>
            <button class="nav-item" onclick="switchTab('expenses', this)">
                <span class="nav-icon">💸</span>
                <span>Expenses</span>
            </button>
            <button class="nav-item" onclick="switchTab('reports', this)">
                <span class="nav-icon">📈</span>
                <span>Reports</span>
            </button>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Management</div>
            <button class="nav-item" onclick="switchTab('staff', this)">
                <span class="nav-icon">👨‍💼</span>
                <span>Staff & PINs</span>
                <span class="nav-badge"><?= $totalStaff ?></span>
            </button>
            <button class="nav-item" onclick="switchTab('settings', this)">
                <span class="nav-icon">⚙️</span>
                <span>Settings</span>
            </button>
        </div>
    </div>
    
    <!-- ===== MAIN ===== -->
    <div class="main">
        
        <div class="topbar">
            <div class="topbar-left">
                <div class="page-title" id="currentTabTitle">📊 Dashboard</div>
                <div class="live-badge">
                    <span class="live-dot"></span>
                    LIVE
                </div>
            </div>
            
            <div class="topbar-right">
                <div class="quick-stat">
                    Today: <strong><?= number_format($todayStats['revenue'], 0) ?> <?= $currency ?></strong>
                </div>
                
                <div class="user-menu">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
                    <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                </div>
                
                <a href="logout.php" class="btn-logout">🚪 Logout</a>
            </div>
        </div>
        
        <div class="content">
            
            <!-- ===== ALERT (from URL params) ===== -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert success">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['err'])): ?>
                <div class="alert error">❌ <?= htmlspecialchars($_GET['err']) ?></div>
            <?php endif; ?>
            
            <!-- ===== TAB: DASHBOARD ===== -->
            <div class="tab-content active" id="tab-dashboard">
                
                <div class="welcome-banner">
                    <div class="welcome-content">
                        <div class="welcome-title">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</div>
                        <div class="welcome-sub">Here's what's happening at <?= htmlspecialchars($business['name']) ?> today</div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon" style="background:rgba(16,185,129,0.15);color:#10b981;">💰</div>
                        <div class="info">
                            <div class="stat-label">Today's Revenue</div>
                            <div class="stat-value"><?= number_format($todayStats['revenue'], 0) ?> <?= $currency ?></div>
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
                        <div class="icon" style="background:rgba(168,85,247,0.15);color:#a855f7;">💎</div>
                        <div class="info">
                            <div class="stat-label">Monthly Profit</div>
                            <div class="stat-value"><?= number_format($monthProfit, 0) ?> <?= $currency ?></div>
                            <div class="stat-change">After expenses</div>
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
                </div>
                
                <div class="cards-row">
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">📋 Recent Sales</div>
                            <button class="btn-sm" onclick="switchTabByName('sales')">View All →</button>
                        </div>
                        
                        <?php
                        $recentSales = $conn->query("
                            SELECT s.*, c.name as customer_name, u.full_name as cashier_name
                            FROM sales s
                            LEFT JOIN customers c ON c.id = s.customer_id
                            LEFT JOIN users u ON u.id = s.user_id
                            WHERE s.business_id = $bid AND s.status = 'completed'
                            ORDER BY s.created_at DESC LIMIT 5
                        ");
                        ?>
                        
                        <?php if ($recentSales->num_rows === 0): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <div>No sales yet</div>
                            </div>
                        <?php else: ?>
                            <div class="sales-list">
                                <?php while ($s = $recentSales->fetch_assoc()): ?>
                                    <div class="sale-item">
                                        <div class="sale-info">
                                            <div class="sale-icon">💰</div>
                                            <div class="sale-details">
                                                <div class="sale-customer"><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></div>
                                                <div class="sale-time">by <?= htmlspecialchars($s['cashier_name'] ?? '-') ?> · <?= date('H:i', strtotime($s['created_at'])) ?></div>
                                            </div>
                                        </div>
                                        <div class="sale-amount"><?= number_format($s['total_amount'], 2) ?> <?= $currency ?></div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card">
                        <div class="card-head">
                            <div class="card-title">⚡ Quick Actions</div>
                        </div>
                        
                        <div class="quick-actions">
                            <a href="pos.php" class="action-btn">
                                <div class="action-icon">🛒</div>
                                <div class="action-label">New Sale</div>
                            </a>
                            <button class="action-btn" onclick="switchTabByName('products'); setTimeout(()=>openModal('productModal'), 300);">
                                <div class="action-icon">➕</div>
                                <div class="action-label">Add Product</div>
                            </button>
                            <button class="action-btn" onclick="switchTabByName('customers'); setTimeout(()=>openModal('customerModal'), 300);">
                                <div class="action-icon">👤</div>
                                <div class="action-label">Add Customer</div>
                            </button>
                            <button class="action-btn" onclick="switchTabByName('staff'); setTimeout(()=>openModal('staffModal'), 300);">
                                <div class="action-icon">🔑</div>
                                <div class="action-label">Add Cashier</div>
                            </button>
                            <button class="action-btn" onclick="switchTabByName('expenses'); setTimeout(()=>openModal('expenseModal'), 300);">
                                <div class="action-icon">💸</div>
                                <div class="action-label">Add Expense</div>
                            </button>
                            <button class="action-btn" onclick="switchTabByName('reports');">
                                <div class="action-icon">📊</div>
                                <div class="action-label">View Reports</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ===== TAB: PRODUCTS ===== -->
            <div class="tab-content" id="tab-products">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">📦 Products (<?= $totalProducts ?>)</div>
                        <button class="btn" onclick="openModal('productModal')">➕ Add Product</button>
                    </div>
                    
                    <?php
                    $products = $conn->query("
                        SELECT p.*, c.name as cat_name, c.color as cat_color, c.icon as cat_icon
                        FROM products p
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE p.business_id = $bid
                        ORDER BY p.created_at DESC
                    ");
                    ?>
                    
                    <?php if ($products->num_rows === 0): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <h3>No products yet</h3>
                            <p style="margin-top:10px;">Click "Add Product" to get started!</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Cost</th>
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
                                        <td>
                                            <?php if ($p['cat_name']): ?>
                                                <span class="cat-badge" style="background:<?= $p['cat_color'] ?>20;color:<?= $p['cat_color'] ?>;">
                                                    <?= $p['cat_icon'] ?> <?= htmlspecialchars($p['cat_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <small style="color:#6b7280;">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($p['cost_price'], 2) ?></td>
                                        <td><strong><?= number_format($p['selling_price'], 2) ?></strong></td>
                                        <td>
                                            <?php
                                            $stockClass = 'status-active';
                                            if ($p['stock_quantity'] <= 0) $stockClass = 'status-inactive';
                                            elseif ($p['stock_quantity'] <= $p['low_stock_threshold']) $stockClass = 'status-low';
                                            ?>
                                            <span class="status-badge <?= $stockClass ?>">
                                                <?= $p['stock_quantity'] ?> <?= $p['unit'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $p['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $p['is_active'] ? '● Active' : '○ Hidden' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-sm" onclick='editProduct(<?= json_encode($p) ?>)'>✏️</button>
                                            <button class="btn-sm" onclick="adjustStock(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')">📦</button>
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('product', <?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== TAB: CATEGORIES ===== -->
            <div class="tab-content" id="tab-categories">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">📂 Categories</div>
                        <button class="btn" onclick="openModal('categoryModal')">➕ Add Category</button>
                    </div>
                    
                    <?php $cats = $conn->query("SELECT * FROM categories WHERE business_id = $bid ORDER BY name"); ?>
                    
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;">
                        <?php while ($c = $cats->fetch_assoc()): ?>
                            <div style="background:var(--bg-dark);padding:18px;border-radius:14px;border-left:4px solid <?= $c['color'] ?>;">
                                <div style="font-size:32px;margin-bottom:8px;"><?= htmlspecialchars($c['icon']) ?></div>
                                <div style="font-weight:800;font-size:16px;"><?= htmlspecialchars($c['name']) ?></div>
                                <button class="btn-sm" style="margin-top:10px;color:#ef4444;" onclick="deleteItem('category', <?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')">🗑️ Delete</button>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            
            <!-- ===== TAB: CUSTOMERS ===== -->
            <div class="tab-content" id="tab-customers">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">👥 Customers (<?= $totalCustomers ?>)</div>
                        <button class="btn" onclick="openModal('customerModal')">➕ Add Customer</button>
                    </div>
                    
                    <?php $customers = $conn->query("SELECT * FROM customers WHERE business_id = $bid AND is_active = 1 ORDER BY name"); ?>
                    
                    <?php if ($customers->num_rows === 0): ?>
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <h3>No customers yet</h3>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>Name</th><th>Phone</th><th>Email</th><th>Points</th><th>Spent</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($c = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                                        <td>🎁 <?= $c['loyalty_points'] ?></td>
                                        <td><strong><?= number_format($c['total_spent'], 0) ?> <?= $currency ?></strong></td>
                                        <td>
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('customer', <?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== TAB: SUPPLIERS ===== -->
            <div class="tab-content" id="tab-suppliers">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">🏢 Suppliers</div>
                        <button class="btn" onclick="openModal('supplierModal')">➕ Add Supplier</button>
                    </div>
                    
                    <?php $suppliers = $conn->query("SELECT * FROM suppliers WHERE business_id = $bid AND is_active = 1 ORDER BY name"); ?>
                    
                    <?php if ($suppliers->num_rows === 0): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🏢</div>
                            <h3>No suppliers yet</h3>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Balance</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($s = $suppliers->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['contact_person'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
                                        <td><?= number_format($s['current_balance'], 2) ?> <?= $currency ?></td>
                                        <td>
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('supplier', <?= $s['id'] ?>, '<?= addslashes($s['name']) ?>')">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== TAB: EXPENSES ===== -->
            <div class="tab-content" id="tab-expenses">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">💸 Expenses (This Month: <?= number_format($monthExpenses, 0) ?> <?= $currency ?>)</div>
                        <button class="btn" onclick="openModal('expenseModal')">➕ Add Expense</button>
                    </div>
                    
                    <?php 
                    $expenses = $conn->query("
                        SELECT e.*, ec.name as cat_name, ec.icon as cat_icon, ec.color as cat_color
                        FROM expenses e
                        LEFT JOIN expense_categories ec ON ec.id = e.category_id
                        WHERE e.business_id = $bid
                        ORDER BY e.expense_date DESC, e.id DESC
                        LIMIT 50
                    "); 
                    ?>
                    
                    <?php if ($expenses->num_rows === 0): ?>
                        <div class="empty-state">
                            <div class="empty-icon">💸</div>
                            <h3>No expenses recorded</h3>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr><th>Date</th><th>Title</th><th>Category</th><th>Amount</th><th>Method</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($e = $expenses->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M d', strtotime($e['expense_date'])) ?></td>
                                        <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
                                        <td>
                                            <?php if ($e['cat_name']): ?>
                                                <span class="cat-badge" style="background:<?= $e['cat_color'] ?>20;color:<?= $e['cat_color'] ?>;">
                                                    <?= $e['cat_icon'] ?> <?= htmlspecialchars($e['cat_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:#ef4444;font-weight:800;">-<?= number_format($e['amount'], 2) ?> <?= $currency ?></td>
                                        <td><small><?= htmlspecialchars($e['payment_method']) ?></small></td>
                                        <td>
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('expense', <?= $e['id'] ?>, '<?= addslashes($e['title']) ?>')">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== TAB: STAFF ===== -->
            <div class="tab-content" id="tab-staff">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">👨‍💼 Staff & PINs</div>
                        <button class="btn" onclick="openModal('staffModal')">➕ Add Cashier</button>
                    </div>
                    
                    <?php $staff = $conn->query("SELECT * FROM users WHERE business_id = $bid ORDER BY role, full_name"); ?>
                    
                    <table class="data-table">
                        <thead>
                            <tr><th>Name</th><th>Role</th><th>Email</th><th>PIN</th><th>Last Login</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $staff->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                                    <td><span class="status-badge status-active"><?= htmlspecialchars($u['role']) ?></span></td>
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
                                    <td><small style="color:#9ca3af;"><?= $u['last_login'] ? date('M d H:i', strtotime($u['last_login'])) : 'Never' ?></small></td>
                                    <td>
                                        <?php if ($u['role'] !== 'owner' || $u['id'] != $uid): ?>
                                            <button class="btn-sm" onclick="resetPin(<?= $u['id'] ?>, '<?= addslashes($u['full_name']) ?>')">🔑</button>
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('staff', <?= $u['id'] ?>, '<?= addslashes($u['full_name']) ?>')">🗑️</button>
                                        <?php else: ?>
                                            <small style="color:#9ca3af;">You</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ===== TAB: SETTINGS ===== -->
            <div class="tab-content" id="tab-settings">
                
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">⚙️ Business Settings</div>
                    </div>
                    
                    <?php $settings = $conn->query("SELECT * FROM business_settings WHERE business_id = $bid")->fetch_assoc(); ?>
                    
                    <form method="POST" action="admin_action.php">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <h4 style="margin-bottom:15px;font-family:var(--font-heading);">🏪 Business Info</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Business Name</label>
                                <input type="text" name="business_name" class="form-input" value="<?= htmlspecialchars($business['name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Currency Symbol</label>
                                <input type="text" name="currency" class="form-input" value="<?= htmlspecialchars($business['currency_symbol']) ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="business_phone" class="form-input" value="<?= htmlspecialchars($business['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="business_email" class="form-input" value="<?= htmlspecialchars($business['email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="business_address" class="form-input" value="<?= htmlspecialchars($business['address'] ?? '') ?>">
                        </div>
                        
                        <hr style="margin:20px 0;border:none;border-top:1px solid rgba(255,255,255,0.06);">
                        
                        <h4 style="margin-bottom:15px;font-family:var(--font-heading);">💰 Tax Settings</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tax Rate (%)</label>
                                <input type="number" step="0.01" name="tax_rate" class="form-input" value="<?= $settings['tax_rate'] ?>">
                            </div>
                            <div class="form-group">
                                <label>Invoice Prefix</label>
                                <input type="text" name="invoice_prefix" class="form-input" value="<?= htmlspecialchars($settings['invoice_prefix']) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="checkbox" name="tax_enabled" <?= $settings['tax_enabled'] ? 'checked' : '' ?>>
                                Enable Tax on Sales
                            </label>
                        </div>
                        
                        <hr style="margin:20px 0;border:none;border-top:1px solid rgba(255,255,255,0.06);">
                        
                        <h4 style="margin-bottom:15px;font-family:var(--font-heading);">🧾 Receipt</h4>
                        
                        <div class="form-group">
                            <label>Receipt Header</label>
                            <textarea name="receipt_header" class="form-textarea" placeholder="Thank you for shopping with us!"><?= htmlspecialchars($settings['receipt_header'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Receipt Footer</label>
                            <textarea name="receipt_footer" class="form-textarea" placeholder="Visit us again!"><?= htmlspecialchars($settings['receipt_footer'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn">💾 Save Settings</button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">🔒 Change Your Password</div>
                    </div>
                    
                    <form method="POST" action="admin_action.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-input" minlength="6" required>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-input" minlength="6" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">🔑 Change Password</button>
                    </form>
                </div>
            </div>
            
            <!-- ===== OTHER TABS PLACEHOLDER ===== -->
            <div class="tab-content" id="tab-sales">
                <div class="card">
                    <div class="card-title">💰 All Sales</div>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>Sales history will appear here</p>
                    </div>
                </div>
            </div>
            
            <div class="tab-content" id="tab-purchases">
                <div class="card">
                    <div class="card-title">📥 Purchases</div>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <p>Stock purchases will appear here</p>
                    </div>
                </div>
            </div>
            
            <div class="tab-content" id="tab-reports">
                <div class="card">
                    <div class="card-title">📈 Reports</div>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>Beautiful charts coming here</p>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- ===== MODALS ===== -->

<!-- PRODUCT MODAL -->
<div class="modal" id="productModal">
    <div class="modal-content">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title" id="productModalTitle">➕ Add Product</div>
            <button class="close-btn" onclick="closeModal('productModal')">✕</button>
        </div>
        
        <form method="POST" action="admin_action.php" id="productForm">
            <input type="hidden" name="action" value="add_product" id="productAction">
            <input type="hidden" name="product_id" id="productId">
            
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" class="form-input" id="productName" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-select" id="productCategory">
                        <option value="">-- None --</option>
                        <?php
                        $cats = $conn->query("SELECT * FROM categories WHERE business_id = $bid ORDER BY name");
                        while ($c = $cats->fetch_assoc()):
                        ?>
                            <option value="<?= $c['id'] ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" class="form-input" id="productSku">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Cost Price</label>
                    <input type="number" step="0.01" name="cost_price" class="form-input" id="productCost" value="0">
                </div>
                <div class="form-group">
                    <label>Selling Price *</label>
                    <input type="number" step="0.01" name="selling_price" class="form-input" id="productPrice" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_quantity" class="form-input" id="productStock" value="0">
                </div>
                <div class="form-group">
                    <label>Low Stock Alert</label>
                    <input type="number" name="low_stock_threshold" class="form-input" id="productLowStock" value="5">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit" class="form-select" id="productUnit">
                        <option value="piece">Piece</option>
                        <option value="kg">Kilogram</option>
                        <option value="liter">Liter</option>
                        <option value="meter">Meter</option>
                        <option value="box">Box</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Image URL (optional)</label>
                    <input type="url" name="image_url" class="form-input" id="productImage" placeholder="https://...">
                </div>
            </div>
            
            <button type="submit" class="btn">💾 Save Product</button>
        </form>
    </div>
</div>

<!-- CATEGORY MODAL -->
<div class="modal" id="categoryModal">
    <div class="modal-content" style="max-width:400px;">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title">➕ Add Category</div>
            <button class="close-btn" onclick="closeModal('categoryModal')">✕</button>
        </div>
        
        <form method="POST" action="admin_action.php">
            <input type="hidden" name="action" value="add_category">
            
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Icon (Emoji)</label>
                    <input type="text" name="icon" class="form-input" value="📦" maxlength="2">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" class="form-input" value="#3b82f6" style="height:48px;">
                </div>
            </div>
            
            <button type="submit" class="btn">💾 Save</button>
        </form>
    </div>
</div>

<!-- CUSTOMER MODAL -->
<div class="modal" id="customerModal">
    <div class="modal-content">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title">➕ Add Customer</div>
            <button class="close-btn" onclick="closeModal('customerModal')">✕</button>
        </div>
        
        <form method="POST" action="admin_action.php">
            <input type="hidden" name="action" value="add_customer">
            
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-input">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-input">
                </div>
            </div>
            
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-input">
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-textarea"></textarea>
            </div>
            
            <button type="submit" class="btn">💾 Save Customer</button>
        </form>
    </div>
</div>

<!-- SUPPLIER MODAL -->
<div class="modal" id="supplierModal">
    <div class="modal-content">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title">➕ Add Supplier</div>
            <button class="close-btn" onclick="closeModal('supplierModal')">✕</button>
        </div>
        
        <form method="POST" action="admin_action.php">
            <input type="hidden" name="action" value="add_supplier">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Company Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" class="form-input">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-input">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-input">
                </div>
            </div>
            
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-input">
            </div>
            
            <button type="submit" class="btn">💾 Save Supplier</button>
        </form>
    </div>
</div>

<!-- EXPENSE MODAL -->
<div class="modal" id="expenseModal">
    <div class="modal-content">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title">💸 Add Expense</div>
            <button class="close-btn" onclick="closeModal('expenseModal')">✕</button>
        </div>
        
        <form method="POST" action="admin_action.php">
            <input type="hidden" name="action" value="add_expense">
            
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-input" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" step="0.01" name="amount" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- None --</option>
                        <?php
                        $exCats = $conn->query("SELECT * FROM expense_categories WHERE business_id = $bid");
                        while ($ec = $exCats->fetch_assoc()):
                        ?>
                            <option value="<?= $ec['id'] ?>"><?= $ec['icon'] ?> <?= htmlspecialchars($ec['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="expense_date" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash">💵 Cash</option>
                        <option value="card">💳 Card</option>
                        <option value="bank">🏦 Bank Transfer</option>
                        <option value="other">📝 Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Vendor / Paid To</label>
                <input type="text" name="vendor" class="form-input">
            </div>
            
            <button type="submit" class="btn">💾 Save Expense</button>
        </form>
    </div>
</div>

<!-- STAFF MODAL -->
<div class="modal" id="staffModal">
    <div class="modal-content">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title">👤 Add Staff Member</div>
            <button class="close-btn" onclick="closeModal('staffModal')">✕</button>
        </div>
        
        <form method="POST" action="admin_action.php">
            <input type="hidden" name="action" value="add_staff">
            
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" class="form-input" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-select">
                        <option value="cashier">💼 Cashier</option>
                        <option value="manager">👨‍💼 Manager</option>
                        <option value="worker">👤 Worker</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>PIN (4 digits, leave empty for auto)</label>
                    <input type="text" name="pin" class="form-input" maxlength="10" placeholder="Auto-generate">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email (for password login)</label>
                    <input type="email" name="email" class="form-input">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-input">
                </div>
            </div>
            
            <div class="form-group">
                <label>Password (if using email login)</label>
                <input type="text" name="password" class="form-input" placeholder="Leave empty if PIN only">
            </div>
            
            <button type="submit" class="btn">💾 Add Staff</button>
        </form>
    </div>
</div>

<!-- STOCK ADJUSTMENT MODAL -->
<div class="modal" id="stockModal">
    <div class="modal-content" style="max-width:400px;">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title">📦 Adjust Stock</div>
            <button class="close-btn" onclick="closeModal('stockModal')">✕</button>
        </div>
        
        <p style="color:#9ca3af;margin-bottom:20px;">Product: <strong id="stockProductName"></strong></p>
        
        <form method="POST" action="admin_action.php">
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="product_id" id="stockProductId">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-select">
                        <option value="in">📥 Add Stock (In)</option>
                        <option value="out">📤 Remove Stock (Out)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" class="form-input" min="1" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="reason" class="form-input" placeholder="e.g. Restock, damaged, etc.">
            </div>
            
            <button type="submit" class="btn">💾 Update Stock</button>
        </form>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-title" style="color:#ef4444;">🗑️ Confirm Delete</div>
        <p style="color:#9ca3af;margin:15px 0;">Are you sure you want to delete <strong id="deleteName"></strong>?</p>
        
        <form method="POST" action="admin_action.php" id="deleteForm">
            <input type="hidden" name="action" id="deleteAction">
            <input type="hidden" name="" id="deleteIdInput">
            
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeModal('deleteModal')" class="btn" style="flex:1;background:#6b7280;">Cancel</button>
                <button type="submit" class="btn btn-danger" style="flex:1;">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// ===== TAB SWITCHING =====
function switchTab(tabName, button) {
    // Update sidebar
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    if (button) button.classList.add('active');
    
    // Show content
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Update title
    const titles = {
        'dashboard': '📊 Dashboard',
        'sales': '💰 All Sales',
        'customers': '👥 Customers',
        'products': '📦 Products',
        'categories': '📂 Categories',
        'suppliers': '🏢 Suppliers',
        'purchases': '📥 Purchases',
        'expenses': '💸 Expenses',
        'reports': '📈 Reports',
        'staff': '👨‍💼 Staff & PINs',
        'settings': '⚙️ Settings'
    };
    document.getElementById('currentTabTitle').textContent = titles[tabName] || 'Dashboard';
    
    // Update URL without reload
    history.pushState({}, '', '?tab=' + tabName);
    
    // Scroll to top
    window.scrollTo(0, 0);
}

function switchTabByName(tabName) {
    // Find and click the right nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        const onclick = item.getAttribute('onclick') || '';
        if (onclick.includes(`'${tabName}'`)) {
            item.click();
        }
    });
}

// ===== MODALS =====
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => {
        if (e.target === m) closeModal(m.id);
    });
});

// ===== EDIT PRODUCT =====
function editProduct(p) {
    document.getElementById('productAction').value = 'edit_product';
    document.getElementById('productId').value = p.id;
    document.getElementById('productName').value = p.name;
    document.getElementById('productCategory').value = p.category_id || '';
    document.getElementById('productSku').value = p.sku || '';
    document.getElementById('productCost').value = p.cost_price;
    document.getElementById('productPrice').value = p.selling_price;
    document.getElementById('productStock').value = p.stock_quantity;
    document.getElementById('productLowStock').value = p.low_stock_threshold;
    document.getElementById('productUnit').value = p.unit;
    document.getElementById('productImage').value = p.image_url || '';
    document.getElementById('productModalTitle').textContent = '✏️ Edit Product';
    
    openModal('productModal');
}

// ===== ADJUST STOCK =====
function adjustStock(productId, name) {
    document.getElementById('stockProductId').value = productId;
    document.getElementById('stockProductName').textContent = name;
    openModal('stockModal');
}

// ===== RESET PIN =====
function resetPin(userId, name) {
    if (!confirm(`Reset PIN for ${name}?`)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin_action.php';
    form.innerHTML = `
        <input type="hidden" name="action" value="reset_pin">
        <input type="hidden" name="user_id" value="${userId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// ===== DELETE ITEM =====
function deleteItem(type, id, name) {
    const actions = {
        'product': 'delete_product',
        'category': 'delete_category',
        'customer': 'delete_customer',
        'supplier': 'delete_supplier',
        'expense': 'delete_expense',
        'staff': 'delete_staff'
    };
    
    const fieldNames = {
        'product': 'product_id',
        'category': 'category_id',
        'customer': 'customer_id',
        'supplier': 'supplier_id',
        'expense': 'expense_id',
        'staff': 'user_id'
    };
    
    document.getElementById('deleteAction').value = actions[type];
    document.getElementById('deleteName').textContent = name;
    
    const input = document.getElementById('deleteIdInput');
    input.name = fieldNames[type];
    input.value = id;
    
    openModal('deleteModal');
}

// ===== INITIAL TAB FROM URL =====
const urlParams = new URLSearchParams(window.location.search);
const initialTab = urlParams.get('tab');
if (initialTab) {
    setTimeout(() => switchTabByName(initialTab), 100);
}

// ===== PUSHER REAL-TIME =====
const BUSINESS_ID = <?= $bid ?>;
const USER_NAME = '<?= addslashes($_SESSION['user_name']) ?>';

const pusher = new Pusher('692a2afe9b9c204f0136', { cluster: 'eu' });

const salesChannel = pusher.subscribe(`business-${BUSINESS_ID}-sales`);
salesChannel.bind('new-sale', (data) => {
    showToast('💰 New Sale!', `${data.amount} ${data.currency || 'DT'} by ${data.cashier}`);
});

function showToast(title, body) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; top: 80px; right: 20px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white; padding: 16px 22px;
        border-radius: 14px; font-weight: 600;
        z-index: 9999;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        max-width: 350px;
        animation: slideIn 0.4s;
    `;
    toast.innerHTML = `<strong>${title}</strong><br><small style="opacity:0.9;">${body}</small>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

console.log('🟢 BizFlow Admin Loaded');
</script>

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="pwa.js"></script>
</body>
</html>
