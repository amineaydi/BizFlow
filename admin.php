<?php
session_start();
require_once 'db.php';
require_once 'theme.php';

requireAdminLogin();

$bid = getBusinessId();
$uid = getUserId();
$business = getBusinessInfo();
$theme = loadCurrentTheme();
$currency = $business['currency_symbol'] ?? 'DT';

$today = date('Y-m-d');

// Stats
$todayStats = $conn->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
    FROM sales WHERE business_id = $bid AND DATE(created_at) = '$today' AND status = 'completed'
")->fetch_assoc();

$totalProducts = $conn->query("SELECT COUNT(*) c FROM products WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalCustomers = $conn->query("SELECT COUNT(*) c FROM customers WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalStaff = $conn->query("SELECT COUNT(*) c FROM users WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$totalSuppliers = $conn->query("SELECT COUNT(*) c FROM suppliers WHERE business_id = $bid AND is_active = 1")->fetch_assoc()['c'];
$lowStock = $conn->query("SELECT COUNT(*) c FROM products WHERE business_id = $bid AND stock_quantity <= low_stock_threshold AND is_active = 1")->fetch_assoc()['c'];

$monthStart = date('Y-m-01');
$monthStats = $conn->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
    FROM sales WHERE business_id = $bid AND DATE(created_at) >= '$monthStart' AND status = 'completed'
")->fetch_assoc();

$monthExpenses = $conn->query("
    SELECT COALESCE(SUM(amount), 0) t FROM expenses WHERE business_id = $bid AND expense_date >= '$monthStart'
")->fetch_assoc()['t'];

$monthProfit = $monthStats['revenue'] - $monthExpenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="theme-color" content="<?= $theme['primary_color'] ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= htmlspecialchars($business['name']) ?> · Admin</title>
<link rel="manifest" href="manifest_admin.json">
<?= renderThemeCSS($theme) ?>
<style>
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }

body {
    background: var(--bg-dark);
    color: var(--text);
    font-family: var(--font-body);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}

/* ===== LAYOUT ===== */
.layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.sidebar {
    background: linear-gradient(180deg, var(--bg-card), #0f1424);
    border-right: 1px solid rgba(255,255,255,0.06);
    padding: 24px 16px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    transition: left 0.3s ease;
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

.sidebar-close {
    display: none;
    background: rgba(239,68,68,0.1);
    border: none;
    color: #ef4444;
    width: 36px; height: 36px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 18px;
}

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
    gap: 12px;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 0;
}

.mobile-menu-btn {
    display: none;
    background: var(--bg-dark);
    border: 1px solid rgba(255,255,255,0.06);
    color: white;
    width: 42px; height: 42px;
    border-radius: 10px;
    font-size: 20px;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.mobile-menu-btn:hover { background: var(--primary); }

.page-title {
    font-family: var(--font-heading);
    font-size: 22px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
    flex-shrink: 0;
}

.live-dot {
    width: 8px; height: 8px;
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
    gap: 10px;
    flex-shrink: 0;
}

.quick-stat {
    background: var(--bg-dark);
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 12px;
    color: #9ca3af;
    white-space: nowrap;
}
.quick-stat strong { color: var(--primary); font-size: 14px; }

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
    width: 32px; height: 32px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
}

.user-name { font-size: 13px; font-weight: 600; }

.btn-logout {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
    padding: 8px 14px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

/* ===== OVERLAY ===== */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    z-index: 999;
}
.sidebar-overlay.show { display: block; }

/* ===== CONTENT ===== */
.content { padding: 30px; flex: 1; }

.tab-content { display: none; animation: fadeIn 0.3s; }
.tab-content.active { display: block; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== WELCOME ===== */
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

/* ===== STATS ===== */
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
}

.stat-card:hover { transform: translateY(-3px); border-color: var(--primary); }

.stat-card .icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stat-card .info { flex: 1; min-width: 0; }
.stat-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
.stat-value { font-size: 24px; font-weight: 800; margin-top: 2px; }
.stat-change { font-size: 11px; color: #10b981; margin-top: 4px; }

/* ===== CARDS ===== */
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
    flex-wrap: wrap;
    gap: 10px;
}

.card-title {
    font-family: var(--font-heading);
    font-size: 18px; font-weight: 700;
}

/* ===== TABLE ===== */
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

/* ===== BUTTONS ===== */
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
    font-family: inherit;
}

.btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,0.3); }
.btn-danger { background: #ef4444; }
.btn-warning { background: #fbbf24; color: #000; }
.btn-success { background: #10b981; }

.btn-sm {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.1);
    background: var(--bg-dark);
    color: white;
    cursor: pointer;
    margin-right: 4px;
    font-family: inherit;
}

.btn-sm:hover { border-color: var(--primary); color: var(--primary); }

/* ===== FORMS ===== */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
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

.form-textarea { min-height: 80px; resize: vertical; }

select.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%233b82f6' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 40px;
}

/* ===== MODAL ===== */
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

/* ===== ALERT ===== */
.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 600;
}
.alert.success { background: rgba(16,185,129,0.15); color: #86efac; border: 1px solid rgba(16,185,129,0.3); }
.alert.error { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }

/* ===== EMPTY STATE ===== */
.empty-state { text-align: center; padding: 60px 20px; color: #6b7280; }
.empty-icon { font-size: 80px; margin-bottom: 20px; opacity: 0.5; }

/* ===== QUICK ACTIONS ===== */
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

/* ===== SALES LIST ===== */
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
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.sale-details { font-size: 13px; }
.sale-customer { font-weight: 700; }
.sale-time { color: #9ca3af; font-size: 11px; }
.sale-amount { font-weight: 800; color: var(--accent); }

/* ===== BADGES ===== */
.cat-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
}

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

::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

/* ============================================ */
/* 📱 MOBILE RESPONSIVE - COMPLETE */
/* ============================================ */

@media (max-width: 1024px) {
    .layout { grid-template-columns: 1fr; }
    
    .sidebar {
        position: fixed;
        left: -300px;
        top: 0;
        height: 100vh;
        width: 280px;
        z-index: 1000;
        padding: 20px 16px;
    }
    
    .sidebar.show {
        left: 0;
        box-shadow: 10px 0 40px rgba(0,0,0,0.6);
    }
    
    .sidebar-close {
        display: flex;
        align-items: center;
        justify-content: center;
        position: absolute;
        top: 16px;
        right: 16px;
    }
    
    .mobile-menu-btn {
        display: flex;
    }
    
    .cards-row { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .content { padding: 20px 16px; }
}

@media (max-width: 768px) {
    .topbar {
        padding: 12px 14px;
        gap: 8px;
    }
    
    .page-title {
        font-size: 16px;
    }
    
    .live-badge {
        display: none;
    }
    
    .quick-stat {
        display: none;
    }
    
    .user-menu .user-name {
        display: none;
    }
    
    .user-menu {
        padding: 4px;
    }
    
    .btn-logout {
        padding: 8px 12px;
        font-size: 14px;
    }
    
    .welcome-banner {
        padding: 20px;
    }
    
    .welcome-title {
        font-size: 20px;
    }
    
    .welcome-sub {
        font-size: 13px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-card {
        padding: 14px;
        gap: 10px;
    }
    
    .stat-card .icon {
        width: 42px;
        height: 42px;
        font-size: 20px;
    }
    
    .stat-value {
        font-size: 18px;
    }
    
    .stat-label {
        font-size: 9px;
    }
    
    .card {
        padding: 16px;
        border-radius: 14px;
        margin-bottom: 14px;
    }
    
    .card-title {
        font-size: 16px;
    }
    
    /* Convert tables to cards on mobile */
    .data-table {
        font-size: 12px;
    }
    
    .data-table thead {
        display: none;
    }
    
    .data-table tr {
        display: block;
        background: var(--bg-dark);
        margin-bottom: 10px;
        border-radius: 12px;
        padding: 12px;
        border: 1px solid rgba(255,255,255,0.06);
    }
    
    .data-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    
    .data-table td:last-child {
        border-bottom: none;
        padding-top: 10px;
    }
    
    .data-table td::before {
        content: attr(data-label);
        font-weight: 700;
        color: #9ca3af;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .modal {
        padding: 0;
        align-items: flex-end;
    }
    
    .modal-content {
        max-width: 100%;
        width: 100%;
        max-height: 95vh;
        border-radius: 20px 20px 0 0;
        padding: 24px 20px;
    }
    
    .form-input, .form-select, .form-textarea {
        padding: 14px 12px;
        font-size: 16px;
    }
    
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-btn {
        padding: 14px 10px;
    }
    
    .btn {
        padding: 11px 16px;
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .page-title {
        font-size: 15px;
    }
    
    .topbar {
        padding: 10px 12px;
    }
    
    .content {
        padding: 14px 12px;
    }
    
    .welcome-banner {
        padding: 18px;
    }
    
    .welcome-title {
        font-size: 18px;
    }
}
</style>
</head>
<body>

<div class="layout">
    
    <!-- ===== SIDEBAR OVERLAY ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar" id="sidebar">
        
        <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
        
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
                <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
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
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_user_name'], 0, 1)) ?></div>
                    <span class="user-name"><?= htmlspecialchars($_SESSION['admin_user_name']) ?></span>
                </div>
                
                <a href="admin_logout.php" class="btn-logout">🚪</a>
            </div>
        </div>
        
        <div class="content">
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert success">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['err'])): ?>
                <div class="alert error">❌ <?= htmlspecialchars($_GET['err']) ?></div>
            <?php endif; ?>
            
            <!-- ===== DASHBOARD TAB ===== -->
            <div class="tab-content active" id="tab-dashboard">
                
                <div class="welcome-banner">
                    <div class="welcome-content">
                        <div class="welcome-title">Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['admin_user_name'])[0]) ?>! 👋</div>
                        <div class="welcome-sub">Here's what's happening today</div>
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
                            <div class="stat-change" style="color:#fbbf24;">Items low</div>
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
                                <div class="action-label">Reports</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ===== PRODUCTS TAB ===== -->
            <div class="tab-content" id="tab-products">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">📦 Products (<?= $totalProducts ?>)</div>
                        <button class="btn" onclick="openModal('productModal')">➕ Add</button>
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
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($p = $products->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Product">
                                            <strong><?= htmlspecialchars($p['name']) ?></strong>
                                            <?php if ($p['sku']): ?>
                                                <br><small style="color:#9ca3af;">SKU: <?= htmlspecialchars($p['sku']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Category">
                                            <?php if ($p['cat_name']): ?>
                                                <span class="cat-badge" style="background:<?= $p['cat_color'] ?>20;color:<?= $p['cat_color'] ?>;">
                                                    <?= $p['cat_icon'] ?> <?= htmlspecialchars($p['cat_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <small style="color:#6b7280;">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Price"><strong><?= number_format($p['selling_price'], 2) ?></strong></td>
                                        <td data-label="Stock">
                                            <?php
                                            $stockClass = 'status-active';
                                            if ($p['stock_quantity'] <= 0) $stockClass = 'status-inactive';
                                            elseif ($p['stock_quantity'] <= $p['low_stock_threshold']) $stockClass = 'status-low';
                                            ?>
                                            <span class="status-badge <?= $stockClass ?>">
                                                <?= $p['stock_quantity'] ?> <?= $p['unit'] ?>
                                            </span>
                                        </td>
                                        <td data-label="Actions">
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
            
            <!-- ===== CATEGORIES TAB ===== -->
            <div class="tab-content" id="tab-categories">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">📂 Categories</div>
                        <button class="btn" onclick="openModal('categoryModal')">➕ Add</button>
                    </div>
                    
                    <?php $cats = $conn->query("SELECT * FROM categories WHERE business_id = $bid ORDER BY name"); ?>
                    
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
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
            
            <!-- ===== CUSTOMERS TAB ===== -->
            <div class="tab-content" id="tab-customers">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">👥 Customers (<?= $totalCustomers ?>)</div>
                        <button class="btn" onclick="openModal('customerModal')">➕ Add</button>
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
                                <tr><th>Name</th><th>Phone</th><th>Spent</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($c = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Name"><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                        <td data-label="Phone"><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                                        <td data-label="Spent"><strong><?= number_format($c['total_spent'], 0) ?> <?= $currency ?></strong></td>
                                        <td data-label="Actions">
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('customer', <?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== SUPPLIERS TAB ===== -->
            <div class="tab-content" id="tab-suppliers">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">🏢 Suppliers</div>
                        <button class="btn" onclick="openModal('supplierModal')">➕ Add</button>
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
                                <tr><th>Name</th><th>Contact</th><th>Phone</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($s = $suppliers->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Name"><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                        <td data-label="Contact"><?= htmlspecialchars($s['contact_person'] ?? '-') ?></td>
                                        <td data-label="Phone"><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                                        <td data-label="Actions">
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('supplier', <?= $s['id'] ?>, '<?= addslashes($s['name']) ?>')">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== EXPENSES TAB ===== -->
            <div class="tab-content" id="tab-expenses">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">💸 Expenses (<?= number_format($monthExpenses, 0) ?> <?= $currency ?> this month)</div>
                        <button class="btn" onclick="openModal('expenseModal')">➕ Add</button>
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
                                <tr><th>Date</th><th>Title</th><th>Amount</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($e = $expenses->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Date"><?= date('M d', strtotime($e['expense_date'])) ?></td>
                                        <td data-label="Title">
                                            <strong><?= htmlspecialchars($e['title']) ?></strong>
                                            <?php if ($e['cat_name']): ?>
                                                <br><small><?= $e['cat_icon'] ?> <?= htmlspecialchars($e['cat_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Amount" style="color:#ef4444;font-weight:800;">-<?= number_format($e['amount'], 2) ?> <?= $currency ?></td>
                                        <td data-label="Actions">
                                            <button class="btn-sm" style="color:#ef4444;" onclick="deleteItem('expense', <?= $e['id'] ?>, '<?= addslashes($e['title']) ?>')">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== STAFF TAB ===== -->
            <div class="tab-content" id="tab-staff">
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">👨‍💼 Staff & PINs</div>
                        <button class="btn" onclick="openModal('staffModal')">➕ Add Cashier</button>
                    </div>
                    
                    <?php $staff = $conn->query("SELECT * FROM users WHERE business_id = $bid ORDER BY role, full_name"); ?>
                    
                    <table class="data-table">
                        <thead>
                            <tr><th>Name</th><th>Role</th><th>PIN</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $staff->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Name">
                                        <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                                        <?php if ($u['email']): ?>
                                            <br><small style="color:#9ca3af;"><?= htmlspecialchars($u['email']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Role">
                                        <span class="status-badge status-active"><?= htmlspecialchars($u['role']) ?></span>
                                    </td>
                                    <td data-label="PIN">
                                        <?php if ($u['pin']): ?>
                                            <code style="background:var(--bg-dark);padding:6px 12px;border-radius:8px;font-size:14px;color:var(--primary);font-weight:700;">
                                                <?= htmlspecialchars($u['pin']) ?>
                                            </code>
                                        <?php else: ?>
                                            <small style="color:#6b7280;">No PIN</small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
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
            
            <!-- ===== SETTINGS TAB ===== -->
            <div class="tab-content" id="tab-settings">
                <?php $settings = $conn->query("SELECT * FROM business_settings WHERE business_id = $bid")->fetch_assoc(); ?>
                
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">⚙️ Business Settings</div>
                    </div>
                    
                    <form method="POST" action="admin_action.php">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <h4 style="margin-bottom:15px;font-family:var(--font-heading);">🏪 Business Info</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Business Name</label>
                                <input type="text" name="business_name" class="form-input" value="<?= htmlspecialchars($business['name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Currency</label>
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
                        
                        <h4 style="margin-bottom:15px;font-family:var(--font-heading);">💰 Tax & Receipts</h4>
                        
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
                                Enable Tax
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Receipt Header</label>
                            <textarea name="receipt_header" class="form-textarea"><?= htmlspecialchars($settings['receipt_header'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Receipt Footer</label>
                            <textarea name="receipt_footer" class="form-textarea"><?= htmlspecialchars($settings['receipt_footer'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn">💾 Save Settings</button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-head">
                        <div class="card-title">🔒 Change Password</div>
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
                                <label>Confirm</label>
                                <input type="password" name="confirm_password" class="form-input" minlength="6" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">🔑 Change Password</button>
                    </form>
                </div>
            </div>
            
            <!-- Other tabs placeholders -->
            <div class="tab-content" id="tab-sales">
                <div class="card">
                    <div class="card-title">💰 Sales History</div>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>Coming soon!</p>
                    </div>
                </div>
            </div>
            
            <!-- ===== REPORTS TAB ===== -->
<div class="tab-content" id="tab-reports">
    
    <?php
    // ========================================
    // 📊 LOAD ALL REPORT DATA
    // ========================================
    
    // Period selector
    $period = $_GET['period'] ?? '7';
    $periods = ['7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days', '365' => 'Last Year'];
    
    $startDate = date('Y-m-d', strtotime("-{$period} days"));
    
    // Total Revenue
    $totalRev = $conn->query("
        SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count
        FROM sales 
        WHERE business_id = $bid 
        AND status = 'completed'
        AND DATE(created_at) >= '$startDate'
    ")->fetch_assoc();
    
    // Total Profit
    $totalProfit = $conn->query("
        SELECT COALESCE(SUM(si.profit), 0) as profit
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        WHERE s.business_id = $bid 
        AND s.status = 'completed'
        AND DATE(s.created_at) >= '$startDate'
    ")->fetch_assoc()['profit'];
    
    // Total Expenses
    $totalExp = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE business_id = $bid 
        AND expense_date >= '$startDate'
    ")->fetch_assoc()['total'];
    
    // Net Profit
    $netProfit = $totalProfit - $totalExp;
    
    // Average transaction
    $avgTransaction = $totalRev['count'] > 0 ? $totalRev['total'] / $totalRev['count'] : 0;
    
    // Daily sales for chart
    $dailySales = [];
    $result = $conn->query("
        SELECT DATE(created_at) as date, 
               COALESCE(SUM(total_amount), 0) as revenue,
               COUNT(*) as count
        FROM sales 
        WHERE business_id = $bid 
        AND status = 'completed'
        AND DATE(created_at) >= '$startDate'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    while ($row = $result->fetch_assoc()) {
        $dailySales[] = $row;
    }
    
    // Top 10 products
    $topProducts = $conn->query("
        SELECT p.name, p.selling_price,
               SUM(si.quantity) as units_sold,
               SUM(si.total) as revenue,
               SUM(si.profit) as profit
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        JOIN products p ON p.id = si.product_id
        WHERE s.business_id = $bid 
        AND s.status = 'completed'
        AND DATE(s.created_at) >= '$startDate'
        GROUP BY p.id
        ORDER BY units_sold DESC
        LIMIT 10
    ");
    
    // Top customers
    $topCustomers = $conn->query("
        SELECT c.name, c.phone, COUNT(s.id) as visits, SUM(s.total_amount) as spent
        FROM customers c
        JOIN sales s ON s.customer_id = c.id
        WHERE s.business_id = $bid 
        AND s.status = 'completed'
        AND DATE(s.created_at) >= '$startDate'
        GROUP BY c.id
        ORDER BY spent DESC
        LIMIT 10
    ");
    
    // Top cashiers
    $topCashiers = $conn->query("
        SELECT u.full_name, COUNT(s.id) as sales_count, SUM(s.total_amount) as revenue
        FROM sales s
        JOIN users u ON u.id = s.user_id
        WHERE s.business_id = $bid 
        AND s.status = 'completed'
        AND DATE(s.created_at) >= '$startDate'
        GROUP BY u.id
        ORDER BY revenue DESC
        LIMIT 5
    ");
    
    // Sales by category
    $byCategory = $conn->query("
        SELECT c.name, c.color, c.icon,
               SUM(si.quantity) as units,
               SUM(si.total) as revenue
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        JOIN products p ON p.id = si.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE s.business_id = $bid 
        AND s.status = 'completed'
        AND DATE(s.created_at) >= '$startDate'
        GROUP BY c.id
        ORDER BY revenue DESC
    ");
    
    // Peak hours
    $peakHours = [];
    $result = $conn->query("
        SELECT HOUR(created_at) as hour, COUNT(*) as count, SUM(total_amount) as revenue
        FROM sales
        WHERE business_id = $bid
        AND status = 'completed'
        AND DATE(created_at) >= '$startDate'
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    while ($row = $result->fetch_assoc()) {
        $peakHours[intval($row['hour'])] = $row;
    }
    
    // Day of week
    $byDayOfWeek = [];
    $result = $conn->query("
        SELECT DAYOFWEEK(created_at) as day, 
               COUNT(*) as count, 
               SUM(total_amount) as revenue
        FROM sales
        WHERE business_id = $bid
        AND status = 'completed'
        AND DATE(created_at) >= '$startDate'
        GROUP BY DAYOFWEEK(created_at)
    ");
    while ($row = $result->fetch_assoc()) {
        $byDayOfWeek[intval($row['day'])] = $row;
    }
    
    // Payment methods
    $byPayment = $conn->query("
        SELECT payment_method, COUNT(*) as count, SUM(total_amount) as revenue
        FROM sales
        WHERE business_id = $bid
        AND status = 'completed'
        AND DATE(created_at) >= '$startDate'
        GROUP BY payment_method
    ");
    
    // Expense breakdown
    $expenseByCat = $conn->query("
        SELECT ec.name, ec.icon, ec.color, 
               COUNT(e.id) as count, 
               SUM(e.amount) as total
        FROM expenses e
        LEFT JOIN expense_categories ec ON ec.id = e.category_id
        WHERE e.business_id = $bid
        AND e.expense_date >= '$startDate'
        GROUP BY ec.id
        ORDER BY total DESC
    ");
    ?>
    
    <!-- PERIOD SELECTOR -->
    <div class="card" style="margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
            <div>
                <div class="card-title" style="margin-bottom:4px;">📈 Reports & Analytics</div>
                <div style="font-size:12px;color:#9ca3af;">Period: <?= $periods[$period] ?></div>
            </div>
            
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php foreach ($periods as $key => $label): ?>
                    <button onclick="changePeriod('<?= $key ?>')" 
                            class="btn-sm" 
                            style="<?= $period == $key ? 'background:var(--primary);color:white;border-color:transparent;' : '' ?>">
                        <?= $label ?>
                    </button>
                <?php endforeach; ?>
                
                <button class="btn-sm" onclick="window.print()" style="background:rgba(16,185,129,0.15);color:#10b981;border-color:rgba(16,185,129,0.3);">
                    🖨️ Print
                </button>
                
                <button class="btn-sm" onclick="exportCSV()" style="background:rgba(168,85,247,0.15);color:#a855f7;border-color:rgba(168,85,247,0.3);">
                    📤 Export CSV
                </button>
            </div>
        </div>
    </div>
    
    <!-- KEY METRICS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon" style="background:rgba(16,185,129,0.15);color:#10b981;">💰</div>
            <div class="info">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value"><?= number_format($totalRev['total'], 0) ?> <?= $currency ?></div>
                <div class="stat-change"><?= $totalRev['count'] ?> sales</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="icon" style="background:rgba(168,85,247,0.15);color:#a855f7;">📈</div>
            <div class="info">
                <div class="stat-label">Gross Profit</div>
                <div class="stat-value"><?= number_format($totalProfit, 0) ?> <?= $currency ?></div>
                <div class="stat-change">From sales</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="icon" style="background:rgba(239,68,68,0.15);color:#ef4444;">💸</div>
            <div class="info">
                <div class="stat-label">Total Expenses</div>
                <div class="stat-value"><?= number_format($totalExp, 0) ?> <?= $currency ?></div>
                <div class="stat-change">Operating costs</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;">💎</div>
            <div class="info">
                <div class="stat-label">Net Profit</div>
                <div class="stat-value" style="color:<?= $netProfit >= 0 ? '#10b981' : '#ef4444' ?>;">
                    <?= number_format($netProfit, 0) ?> <?= $currency ?>
                </div>
                <div class="stat-change"><?= $netProfit >= 0 ? '📈 Profit' : '📉 Loss' ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="icon" style="background:rgba(251,191,36,0.15);color:#fbbf24;">🎯</div>
            <div class="info">
                <div class="stat-label">Avg Transaction</div>
                <div class="stat-value"><?= number_format($avgTransaction, 0) ?> <?= $currency ?></div>
                <div class="stat-change">Per sale</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="icon" style="background:rgba(236,72,153,0.15);color:#ec4899;">📦</div>
            <div class="info">
                <div class="stat-label">Total Items Sold</div>
                <div class="stat-value">
                    <?php
                    $totalItems = $conn->query("
                        SELECT COALESCE(SUM(si.quantity), 0) total
                        FROM sale_items si
                        JOIN sales s ON s.id = si.sale_id
                        WHERE s.business_id = $bid 
                        AND s.status = 'completed'
                        AND DATE(s.created_at) >= '$startDate'
                    ")->fetch_assoc()['total'];
                    echo number_format($totalItems);
                    ?>
                </div>
                <div class="stat-change">Units</div>
            </div>
        </div>
    </div>
    
    <!-- REVENUE CHART -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">📊 Daily Revenue Trend</div>
        </div>
        <div style="position:relative;height:300px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    
    <!-- TWO COLUMNS -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;" class="reports-row">
        
        <!-- TOP PRODUCTS -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">🏆 Top Selling Products</div>
            </div>
            
            <?php if ($topProducts->num_rows === 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <p>No sales yet</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php $rank = 1; while ($p = $topProducts->fetch_assoc()): ?>
                        <div style="display:flex;align-items:center;gap:14px;padding:12px;background:var(--bg-dark);border-radius:10px;">
                            <div style="width:36px;height:36px;background:<?= $rank <= 3 ? ['#fbbf24', '#94a3b8', '#cd7f32'][$rank-1] : 'var(--primary)' ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;color:white;">
                                <?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : "#$rank" ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($p['name']) ?>
                                </div>
                                <div style="font-size:11px;color:#9ca3af;">
                                    <?= $p['units_sold'] ?> units · <?= number_format($p['profit'], 0) ?> <?= $currency ?> profit
                                </div>
                            </div>
                            <div style="font-weight:800;color:var(--primary);font-size:15px;white-space:nowrap;">
                                <?= number_format($p['revenue'], 0) ?> <?= $currency ?>
                            </div>
                        </div>
                    <?php $rank++; endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- TOP CUSTOMERS -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">👑 Top Customers</div>
            </div>
            
            <?php if ($topCustomers->num_rows === 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <p>No customer sales yet</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php $rank = 1; while ($c = $topCustomers->fetch_assoc()): ?>
                        <div style="display:flex;align-items:center;gap:14px;padding:12px;background:var(--bg-dark);border-radius:10px;">
                            <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;color:white;font-size:14px;">
                                <?= strtoupper(substr($c['name'], 0, 1)) ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($c['name']) ?></div>
                                <div style="font-size:11px;color:#9ca3af;">
                                    <?= $c['visits'] ?> visits
                                    <?php if ($c['phone']): ?>· 📞 <?= htmlspecialchars($c['phone']) ?><?php endif; ?>
                                </div>
                            </div>
                            <div style="font-weight:800;color:#10b981;font-size:15px;">
                                <?= number_format($c['spent'], 0) ?>
                            </div>
                        </div>
                    <?php $rank++; endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- TOP CASHIERS -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">🥇 Top Performing Cashiers</div>
        </div>
        
        <?php if ($topCashiers->num_rows === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">👨‍💼</div>
                <p>No staff sales yet</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Rank</th><th>Cashier</th><th>Sales</th><th>Revenue</th><th>Avg</th></tr>
                </thead>
                <tbody>
                    <?php $rank = 1; while ($cashier = $topCashiers->fetch_assoc()): 
                        $avg = $cashier['sales_count'] > 0 ? $cashier['revenue'] / $cashier['sales_count'] : 0;
                    ?>
                        <tr>
                            <td data-label="Rank">
                                <?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : "#$rank" ?>
                            </td>
                            <td data-label="Cashier"><strong><?= htmlspecialchars($cashier['full_name']) ?></strong></td>
                            <td data-label="Sales"><?= $cashier['sales_count'] ?></td>
                            <td data-label="Revenue" style="color:#10b981;font-weight:800;"><?= number_format($cashier['revenue'], 0) ?> <?= $currency ?></td>
                            <td data-label="Average"><?= number_format($avg, 0) ?> <?= $currency ?></td>
                        </tr>
                    <?php $rank++; endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- TWO COLUMNS -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;" class="reports-row">
        
        <!-- PEAK HOURS -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">⏰ Peak Hours</div>
            </div>
            <div style="position:relative;height:250px;">
                <canvas id="hoursChart"></canvas>
            </div>
        </div>
        
        <!-- DAY OF WEEK -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">📅 Sales by Day</div>
            </div>
            <div style="position:relative;height:250px;">
                <canvas id="weekChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- TWO COLUMNS -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;" class="reports-row">
        
        <!-- CATEGORY BREAKDOWN -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">🎨 Sales by Category</div>
            </div>
            
            <?php if ($byCategory->num_rows === 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">📂</div>
                    <p>No category sales</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php while ($cat = $byCategory->fetch_assoc()): 
                        $catRevenue = floatval($cat['revenue']);
                        $catPercent = $totalRev['total'] > 0 ? ($catRevenue / $totalRev['total']) * 100 : 0;
                    ?>
                        <div style="background:var(--bg-dark);padding:14px;border-radius:10px;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                                <div style="font-weight:700;">
                                    <?= $cat['icon'] ?? '📦' ?> <?= htmlspecialchars($cat['name'] ?? 'Uncategorized') ?>
                                </div>
                                <div style="font-weight:800;color:var(--primary);">
                                    <?= number_format($catRevenue, 0) ?> <?= $currency ?>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="flex:1;height:8px;background:rgba(255,255,255,0.05);border-radius:4px;overflow:hidden;">
                                    <div style="height:100%;width:<?= $catPercent ?>%;background:<?= $cat['color'] ?? '#3b82f6' ?>;"></div>
                                </div>
                                <div style="font-size:11px;color:#9ca3af;min-width:50px;text-align:right;">
                                    <?= number_format($catPercent, 1) ?>%
                                </div>
                            </div>
                            <div style="font-size:11px;color:#6b7280;margin-top:6px;">
                                <?= $cat['units'] ?> units sold
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- PAYMENT METHODS -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">💳 Payment Methods</div>
            </div>
            <div style="position:relative;height:250px;">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- EXPENSE BREAKDOWN -->
    <?php if ($expenseByCat->num_rows > 0): ?>
    <div class="card">
        <div class="card-head">
            <div class="card-title">💸 Expense Breakdown</div>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
            <?php while ($exp = $expenseByCat->fetch_assoc()): 
                $expPercent = $totalExp > 0 ? ($exp['total'] / $totalExp) * 100 : 0;
            ?>
                <div style="background:var(--bg-dark);padding:16px;border-radius:12px;border-left:4px solid <?= $exp['color'] ?? '#ef4444' ?>;">
                    <div style="font-size:24px;margin-bottom:8px;"><?= $exp['icon'] ?? '💸' ?></div>
                    <div style="font-weight:700;font-size:14px;margin-bottom:4px;"><?= htmlspecialchars($exp['name'] ?? 'Uncategorized') ?></div>
                    <div style="font-weight:800;color:#ef4444;font-size:18px;">
                        <?= number_format($exp['total'], 0) ?> <?= $currency ?>
                    </div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
                        <?= $exp['count'] ?> expenses · <?= number_format($expPercent, 1) ?>%
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- RECENT SALES TABLE -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">📋 Detailed Sales History</div>
            <div style="font-size:12px;color:#9ca3af;">Last 50 sales</div>
        </div>
        
        <?php
        $allSales = $conn->query("
            SELECT s.*, c.name as customer_name, u.full_name as cashier_name,
                   (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u ON u.id = s.user_id
            WHERE s.business_id = $bid 
            AND s.status = 'completed'
            AND DATE(s.created_at) >= '$startDate'
            ORDER BY s.created_at DESC
            LIMIT 50
        ");
        ?>
        
        <?php if ($allSales->num_rows === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p>No sales in this period</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Method</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $allSales->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Invoice">
                                <code style="background:var(--bg-dark);padding:4px 8px;border-radius:6px;font-size:11px;">
                                    <?= htmlspecialchars($s['invoice_number']) ?>
                                </code>
                            </td>
                            <td data-label="Date">
                                <?= date('M d, H:i', strtotime($s['created_at'])) ?>
                            </td>
                            <td data-label="Customer">
                                <?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?>
                            </td>
                            <td data-label="Cashier">
                                <?= htmlspecialchars($s['cashier_name'] ?? '-') ?>
                            </td>
                            <td data-label="Items">
                                <?= $s['items_count'] ?>
                            </td>
                            <td data-label="Method">
                                <span class="cat-badge" style="background:<?= $s['payment_method'] === 'cash' ? 'rgba(16,185,129,0.15)' : 'rgba(59,130,246,0.15)' ?>;color:<?= $s['payment_method'] === 'cash' ? '#10b981' : '#3b82f6' ?>;">
                                    <?= $s['payment_method'] === 'cash' ? '💵' : '💳' ?> <?= ucfirst($s['payment_method']) ?>
                                </span>
                            </td>
                            <td data-label="Total" style="color:#10b981;font-weight:800;">
                                <?= number_format($s['total_amount'], 2) ?> <?= $currency ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
    @media (max-width: 1024px) {
        .reports-row { grid-template-columns: 1fr !important; }
    }
    
    @media print {
        body { background: white !important; color: black !important; }
        .sidebar, .topbar, .nav-item, .btn, button { display: none !important; }
        .content { padding: 0 !important; }
        .card { 
            background: white !important; 
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            page-break-inside: avoid;
        }
        .card-title, .stat-label, .stat-value, td, th { color: black !important; }
    }
    </style>
</div>

<!-- ===== CHART.JS LIBRARY ===== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ===== CHART DATA FROM PHP =====
const CHART_DATA = {
    daily: <?= json_encode(array_map(function($d) {
        return ['date' => $d['date'], 'revenue' => floatval($d['revenue']), 'count' => intval($d['count'])];
    }, $dailySales)) ?>,
    
    hours: <?php
        $hoursData = [];
        for ($i = 0; $i < 24; $i++) {
            $hoursData[] = isset($peakHours[$i]) ? floatval($peakHours[$i]['revenue']) : 0;
        }
        echo json_encode($hoursData);
    ?>,
    
    weekDays: <?php
        // MySQL DAYOFWEEK: 1=Sunday, 7=Saturday
        $weekData = [];
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        for ($i = 1; $i <= 7; $i++) {
            $weekData[] = isset($byDayOfWeek[$i]) ? floatval($byDayOfWeek[$i]['revenue']) : 0;
        }
        echo json_encode($weekData);
    ?>,
    
    payments: <?php
        $payData = [];
        $payments = $conn->query("
            SELECT payment_method, SUM(total_amount) as total
            FROM sales
            WHERE business_id = $bid AND status = 'completed' AND DATE(created_at) >= '$startDate'
            GROUP BY payment_method
        ");
        while ($row = $payments->fetch_assoc()) {
            $payData[ucfirst($row['payment_method'])] = floatval($row['total']);
        }
        echo json_encode($payData);
    ?>,
    
    currency: '<?= $currency ?>'
};

// ===== INIT CHARTS WHEN TAB SHOWS =====
let chartsInitialized = false;

function initReportsCharts() {
    if (chartsInitialized) return;
    if (typeof Chart === 'undefined') {
        setTimeout(initReportsCharts, 200);
        return;
    }
    chartsInitialized = true;
    
    // Chart.js global config
    Chart.defaults.color = '#9ca3af';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
    Chart.defaults.font.family = 'Inter, sans-serif';
    
    // ===== REVENUE CHART =====
    const ctxRev = document.getElementById('revenueChart');
    if (ctxRev) {
        new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: CHART_DATA.daily.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('en', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue',
                    data: CHART_DATA.daily.map(d => d.revenue),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1f33',
                        padding: 14,
                        callbacks: {
                            label: ctx => ctx.parsed.y.toLocaleString() + ' ' + CHART_DATA.currency
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => v.toLocaleString() + ' ' + CHART_DATA.currency
                        }
                    }
                }
            }
        });
    }
    
    // ===== HOURS CHART =====
    const ctxHours = document.getElementById('hoursChart');
    if (ctxHours) {
        new Chart(ctxHours, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + 'h'),
                datasets: [{
                    label: 'Revenue',
                    data: CHART_DATA.hours,
                    backgroundColor: 'rgba(168,85,247,0.7)',
                    borderColor: '#a855f7',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    // ===== WEEK CHART =====
    const ctxWeek = document.getElementById('weekChart');
    if (ctxWeek) {
        new Chart(ctxWeek, {
            type: 'bar',
            data: {
                labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Revenue',
                    data: CHART_DATA.weekDays,
                    backgroundColor: [
                        'rgba(239,68,68,0.7)',
                        'rgba(59,130,246,0.7)',
                        'rgba(168,85,247,0.7)',
                        'rgba(236,72,153,0.7)',
                        'rgba(251,191,36,0.7)',
                        'rgba(16,185,129,0.7)',
                        'rgba(245,158,11,0.7)'
                    ],
                    borderWidth: 0,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    // ===== PAYMENT CHART =====
    const ctxPay = document.getElementById('paymentChart');
    if (ctxPay && Object.keys(CHART_DATA.payments).length > 0) {
        new Chart(ctxPay, {
            type: 'doughnut',
            data: {
                labels: Object.keys(CHART_DATA.payments),
                datasets: [{
                    data: Object.values(CHART_DATA.payments),
                    backgroundColor: ['#10b981', '#3b82f6', '#a855f7', '#fbbf24'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 13, weight: 'bold' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.label + ': ' + ctx.parsed.toLocaleString() + ' ' + CHART_DATA.currency
                        }
                    }
                }
            }
        });
    }
}

// ===== PERIOD CHANGE =====
function changePeriod(days) {
    window.location.href = '?tab=reports&period=' + days;
}

// ===== EXPORT CSV =====
function exportCSV() {
    let csv = 'Invoice,Date,Customer,Cashier,Items,Method,Total\n';
    
    document.querySelectorAll('#tab-reports table tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 7) {
            const data = [];
            cells.forEach(cell => {
                data.push('"' + cell.textContent.trim().replace(/\s+/g, ' ') + '"');
            });
            csv += data.join(',') + '\n';
        }
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'bizflow-report-' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// ===== INIT CHARTS WHEN REPORTS TAB CLICKED =====
document.addEventListener('DOMContentLoaded', () => {
    // Check if reports tab is active on load
    if (document.getElementById('tab-reports').classList.contains('active')) {
        setTimeout(initReportsCharts, 300);
    }
    
    // Init when tab clicked
    document.querySelectorAll('.nav-item').forEach(item => {
        const onclick = item.getAttribute('onclick') || '';
        if (onclick.includes("'reports'")) {
            item.addEventListener('click', () => {
                setTimeout(initReportsCharts, 300);
            });
        }
    });
});
</script>
            
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
        
        <form method="POST" action="admin_action.php">
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
                    <label>Stock</label>
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
                    <label>Image URL</label>
                    <input type="url" name="image_url" class="form-input" id="productImage" placeholder="https://...">
                </div>
            </div>
            
            <button type="submit" class="btn" style="width:100%;">💾 Save Product</button>
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
                    <label>Icon</label>
                    <input type="text" name="icon" class="form-input" value="📦" maxlength="2">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" class="form-input" value="#3b82f6" style="height:48px;">
                </div>
            </div>
            
            <button type="submit" class="btn" style="width:100%;">💾 Save</button>
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
            
            <button type="submit" class="btn" style="width:100%;">💾 Save Customer</button>
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
                    <label>Company *</label>
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
            
            <button type="submit" class="btn" style="width:100%;">💾 Save</button>
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
                    <label>Payment</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash">💵 Cash</option>
                        <option value="card">💳 Card</option>
                        <option value="bank">🏦 Bank</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Vendor</label>
                <input type="text" name="vendor" class="form-input">
            </div>
            
            <button type="submit" class="btn" style="width:100%;">💾 Save Expense</button>
        </form>
    </div>
</div>

<!-- STAFF MODAL -->
<div class="modal" id="staffModal">
    <div class="modal-content">
        <div style="display:flex;align-items:center;margin-bottom:20px;">
            <div class="modal-title">👤 Add Staff</div>
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
                    </select>
                </div>
                <div class="form-group">
                    <label>PIN (auto if empty)</label>
                    <input type="text" name="pin" class="form-input" maxlength="10">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-input">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-input">
                </div>
            </div>
            
            <div class="form-group">
                <label>Password (optional)</label>
                <input type="text" name="password" class="form-input" placeholder="For email login">
            </div>
            
            <button type="submit" class="btn" style="width:100%;">💾 Add Staff</button>
        </form>
    </div>
</div>

<!-- STOCK MODAL -->
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
                        <option value="in">📥 Add</option>
                        <option value="out">📤 Remove</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" class="form-input" min="1" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="reason" class="form-input" placeholder="e.g. Restock">
            </div>
            
            <button type="submit" class="btn" style="width:100%;">💾 Update</button>
        </form>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-title" style="color:#ef4444;">🗑️ Confirm Delete</div>
        <p style="color:#9ca3af;margin:15px 0;">Delete <strong id="deleteName"></strong>?</p>
        
        <form method="POST" action="admin_action.php">
            <input type="hidden" name="action" id="deleteAction">
            <input type="hidden" id="deleteIdInput">
            
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeModal('deleteModal')" class="btn" style="flex:1;background:#6b7280;">Cancel</button>
                <button type="submit" class="btn btn-danger" style="flex:1;">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// ===== MOBILE SIDEBAR =====
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
    
    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('show');
    document.getElementById('sidebarOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== TABS =====
function switchTab(tabName, button) {
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    if (button) button.classList.add('active');
    
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    
    const titles = {
        'dashboard': '📊 Dashboard',
        'sales': '💰 All Sales',
        'customers': '👥 Customers',
        'products': '📦 Products',
        'categories': '📂 Categories',
        'suppliers': '🏢 Suppliers',
        'expenses': '💸 Expenses',
        'reports': '📈 Reports',
        'staff': '👨‍💼 Staff',
        'settings': '⚙️ Settings'
    };
    document.getElementById('currentTabTitle').textContent = titles[tabName] || 'Dashboard';
    
    history.pushState({}, '', '?tab=' + tabName);
    window.scrollTo(0, 0);
    
    // Close sidebar on mobile
    if (window.innerWidth <= 1024) {
        setTimeout(closeSidebar, 200);
    }
}

function switchTabByName(tabName) {
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

document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => {
        if (e.target === m) closeModal(m.id);
    });
});

// ===== PRODUCT EDIT =====
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

// ===== STOCK ADJUST =====
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

// ===== DELETE =====
function deleteItem(type, id, name) {
    const actions = {
        'product': 'delete_product',
        'category': 'delete_category',
        'customer': 'delete_customer',
        'supplier': 'delete_supplier',
        'expense': 'delete_expense',
        'staff': 'delete_staff'
    };
    
    const fields = {
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
    input.name = fields[type];
    input.value = id;
    
    openModal('deleteModal');
}

// ===== KEYBOARD =====
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(m => closeModal(m.id));
        closeSidebar();
    }
});

// ===== URL TAB =====
const urlParams = new URLSearchParams(window.location.search);
const initialTab = urlParams.get('tab');
if (initialTab) {
    setTimeout(() => switchTabByName(initialTab), 100);
}

console.log('🟢 BizFlow Admin Loaded');
</script>

</body>
</html>
