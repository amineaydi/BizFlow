<?php
session_start();
require_once 'db.php';

requireSuperAdmin();

$message = '';
$error = '';

// ========================================
// 🎯 HANDLE ACTIONS
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CREATE BUSINESS + OWNER
    if ($action === 'create_business') {
        $name = trim($_POST['business_name'] ?? '');
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['slug'] ?? $name)));
        $type = $_POST['type'] ?? 'general';
        $emoji = trim($_POST['emoji'] ?? '🏪');
        $plan = $_POST['plan'] ?? 'starter';
        $currency = trim($_POST['currency'] ?? 'DT');
        
        $ownerName = trim($_POST['owner_name'] ?? '');
        $ownerEmail = trim($_POST['owner_email'] ?? '');
        $ownerPassword = trim($_POST['owner_password'] ?? '');
        $ownerPhone = trim($_POST['owner_phone'] ?? '');
        
        if (!$name || !$ownerName || !$ownerEmail || !$ownerPassword) {
            $error = "⚠️ Fill all required fields.";
        } elseif (strlen($ownerPassword) < 6) {
            $error = "⚠️ Password must be 6+ characters.";
        } else {
            // Check email uniqueness
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $ownerEmail);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                $error = "❌ This email is already used.";
            } else {
                // Check slug uniqueness
                $check2 = $conn->prepare("SELECT id FROM businesses WHERE slug = ?");
                $check2->bind_param("s", $slug);
                $check2->execute();
                
                if ($check2->get_result()->num_rows > 0) {
                    $slug = $slug . '-' . time(); // Make unique
                }
                
                // Create business
                $stmt = $conn->prepare("
                    INSERT INTO businesses (name, slug, type, currency, currency_symbol, plan, logo_emoji, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->bind_param("sssssss", $name, $slug, $type, $currency, $currency, $plan, $emoji);
                $stmt->execute();
                $businessId = $conn->insert_id;
                
                if ($businessId) {
                    // Create owner
                    $hashedPass = password_hash($ownerPassword, PASSWORD_BCRYPT);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO users (business_id, username, password, full_name, role, email, phone, is_active, created_at)
                        VALUES (?, ?, ?, ?, 'owner', ?, ?, 1, NOW())
                    ");
                    $username = explode('@', $ownerEmail)[0]; // Use email prefix as username
                    $stmt->bind_param("isssss", $businessId, $username, $hashedPass, $ownerName, $ownerEmail, $ownerPhone);
                    $stmt->execute();
                    
                    // Create default theme
                    $conn->query("INSERT INTO business_themes (business_id, logo_emoji) VALUES ($businessId, '$emoji')");
                    
                    // Create default settings
                    $conn->query("INSERT INTO business_settings (business_id) VALUES ($businessId)");
                    
                    // Create default categories
                    $conn->query("INSERT INTO categories (business_id, name, icon, color) VALUES 
                        ($businessId, 'General', '📦', '#3b82f6'),
                        ($businessId, 'Featured', '⭐', '#fbbf24')");
                    
                    // Create default expense categories
                    $conn->query("INSERT INTO expense_categories (business_id, name, icon, color) VALUES 
                        ($businessId, 'Rent', '🏠', '#ef4444'),
                        ($businessId, 'Salaries', '💰', '#10b981'),
                        ($businessId, 'Utilities', '⚡', '#fbbf24'),
                        ($businessId, 'Other', '💸', '#6b7280')");
                    
                    $message = "✅ Business '<strong>$name</strong>' created successfully!<br>
                                📧 Owner email: <strong>$ownerEmail</strong><br>
                                🔑 Password: <strong>$ownerPassword</strong><br>
                                <small>📌 Share these credentials with the owner!</small>";
                } else {
                    $error = "❌ Failed to create business.";
                }
            }
        }
    }
    
    // TOGGLE ACTIVE
    if ($action === 'toggle_active') {
        $bid = intval($_POST['business_id']);
        $conn->query("UPDATE businesses SET is_active = 1 - is_active WHERE id = $bid");
        $message = "✅ Status updated!";
    }
    
    // CHANGE PLAN
    if ($action === 'change_plan') {
        $bid = intval($_POST['business_id']);
        $plan = $_POST['plan'] ?? 'free';
        $stmt = $conn->prepare("UPDATE businesses SET plan = ? WHERE id = ?");
        $stmt->bind_param("si", $plan, $bid);
        $stmt->execute();
        $message = "✅ Plan updated!";
    }
    
    // DELETE BUSINESS
    if ($action === 'delete_business') {
        $bid = intval($_POST['business_id']);
        $confirm = trim($_POST['confirm_name'] ?? '');
        
        $b = $conn->query("SELECT name FROM businesses WHERE id = $bid")->fetch_assoc();
        
        if ($b && $confirm === $b['name']) {
            $conn->query("UPDATE businesses SET is_active = 0, slug = CONCAT(slug, '_deleted_', UNIX_TIMESTAMP()) WHERE id = $bid");
            $message = "✅ Business deactivated.";
        } else {
            $error = "❌ Name doesn't match.";
        }
    }
    
    // RESET OWNER PASSWORD
    if ($action === 'reset_password') {
        $userId = intval($_POST['user_id']);
        $newPass = trim($_POST['new_password']);
        
        if (strlen($newPass) < 6) {
            $error = "⚠️ Password must be 6+ chars.";
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'owner'");
            $stmt->bind_param("si", $hashed, $userId);
            $stmt->execute();
            $message = "🔑 Password reset to: <strong>$newPass</strong>";
        }
    }
}

// ========================================
// 📊 LOAD STATS
// ========================================
$stats = [
    'total_businesses' => $conn->query("SELECT COUNT(*) c FROM businesses")->fetch_assoc()['c'],
    'active_businesses' => $conn->query("SELECT COUNT(*) c FROM businesses WHERE is_active = 1")->fetch_assoc()['c'],
    'total_sales' => $conn->query("SELECT COALESCE(SUM(total_amount), 0) t FROM sales WHERE status = 'completed'")->fetch_assoc()['t'],
    'sales_today' => $conn->query("SELECT COUNT(*) c FROM sales WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'],
    'total_users' => $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'],
    'total_products' => $conn->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'],
];

// Calculate platform revenue (based on plans)
$planPrices = ['free' => 0, 'starter' => 30, 'pro' => 75, 'enterprise' => 150];
$revenue = 0;
$plansData = $conn->query("SELECT plan, COUNT(*) c FROM businesses WHERE is_active = 1 GROUP BY plan");
while ($p = $plansData->fetch_assoc()) {
    $revenue += ($planPrices[$p['plan']] ?? 0) * $p['c'];
}

// Load all businesses
$businesses = $conn->query("
    SELECT b.*,
        (SELECT COUNT(*) FROM users u WHERE u.business_id = b.id) as user_count,
        (SELECT COUNT(*) FROM products p WHERE p.business_id = b.id) as product_count,
        (SELECT COUNT(*) FROM sales s WHERE s.business_id = b.id) as sale_count,
        (SELECT COALESCE(SUM(total_amount),0) FROM sales s WHERE s.business_id = b.id AND s.status='completed') as total_revenue,
        (SELECT u.id FROM users u WHERE u.business_id = b.id AND u.role = 'owner' LIMIT 1) as owner_id,
        (SELECT u.full_name FROM users u WHERE u.business_id = b.id AND u.role = 'owner' LIMIT 1) as owner_name,
        (SELECT u.email FROM users u WHERE u.business_id = b.id AND u.role = 'owner' LIMIT 1) as owner_email
    FROM businesses b
    ORDER BY b.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>👑 Platform HQ · BizFlow</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }

body {
    background: #0a0e1a;
    background-image: 
        radial-gradient(circle at 0% 0%, rgba(168,85,247,0.1) 0%, transparent 50%),
        radial-gradient(circle at 100% 100%, rgba(236,72,153,0.08) 0%, transparent 50%);
    color: white;
    min-height: 100vh;
}

/* HEADER */
.header {
    background: linear-gradient(135deg, #1a1f33, #151929);
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(168,85,247,0.2);
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(20px);
}

.brand {
    display: flex;
    align-items: center;
    gap: 14px;
}

.crown {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #a855f7, #ec4899);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    box-shadow: 0 8px 25px rgba(168,85,247,0.3);
}

.brand-text {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
}
.brand-text span {
    background: linear-gradient(135deg, #a855f7, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.brand-sub {
    font-size: 11px;
    color: #9ca3af;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.user-chip {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(168,85,247,0.1);
    padding: 8px 16px;
    border-radius: 30px;
    border: 1px solid rgba(168,85,247,0.3);
    font-size: 13px;
    font-weight: 600;
}

.btn-logout {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

/* CONTAINER */
.container {
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
}

.page-title {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    margin-bottom: 8px;
}
.page-sub {
    color: #9ca3af;
    font-size: 14px;
    margin-bottom: 30px;
}

/* ALERTS */
.alert {
    padding: 16px 20px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
}
.alert.success {
    background: rgba(16,185,129,0.1);
    color: #86efac;
    border: 1px solid rgba(16,185,129,0.3);
}
.alert.error {
    background: rgba(239,68,68,0.1);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.3);
}

/* STATS */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #1a1f33, #151929);
    border: 1px solid #2a3047;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    border-color: rgba(168,85,247,0.5);
}

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-label {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
}

.stat-value {
    font-size: 26px;
    font-weight: 800;
    margin-top: 2px;
}

/* GRID */
.grid {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 24px;
}

.card {
    background: #1a1f33;
    border: 1px solid #2a3047;
    border-radius: 20px;
    padding: 24px;
}

.card-title {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* FORM */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }

label {
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
}

input, select, textarea {
    padding: 12px 14px;
    background: #0f1424;
    border: 2px solid #2a3047;
    border-radius: 10px;
    color: white;
    font-size: 14px;
    font-family: inherit;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #a855f7;
}

select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23a855f7' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 36px;
}

select option { background: #0f1424; color: white; }

.btn {
    background: linear-gradient(135deg, #a855f7, #ec4899);
    color: white;
    border: none;
    padding: 14px;
    border-radius: 12px;
    font-weight: 800;
    cursor: pointer;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: 0.2s;
    width: 100%;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(168,85,247,0.3); }

.divider {
    height: 1px;
    background: rgba(255,255,255,0.06);
    margin: 18px 0;
}

.section-label {
    font-size: 12px;
    color: #a855f7;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 700;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* BUSINESS LIST */
.business-list { display: flex; flex-direction: column; gap: 14px; }

.biz-card {
    background: #0f1424;
    border: 1px solid #2a3047;
    border-radius: 16px;
    padding: 18px;
    border-left: 4px solid #6b7280;
    transition: 0.2s;
}
.biz-card.active { border-left-color: #10b981; }
.biz-card.inactive { border-left-color: #ef4444; opacity: 0.7; }
.biz-card:hover { transform: translateY(-2px); }

.biz-top {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 14px;
}

.biz-info { display: flex; gap: 14px; }

.biz-emoji {
    width: 52px;
    height: 52px;
    background: rgba(168,85,247,0.15);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
}

.biz-name {
    font-size: 17px;
    font-weight: 800;
    margin-bottom: 4px;
}

.biz-owner {
    font-size: 12px;
    color: #9ca3af;
}

.plan-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.plan-free { background: rgba(107,114,128,0.15); color: #9ca3af; }
.plan-starter { background: rgba(59,130,246,0.15); color: #60a5fa; }
.plan-pro { background: rgba(168,85,247,0.15); color: #c084fc; }
.plan-enterprise { background: rgba(251,191,36,0.15); color: #fbbf24; }

.biz-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    background: #1a1f33;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 14px;
}

.mini-stat {
    text-align: center;
}

.mini-val {
    font-size: 17px;
    font-weight: 800;
}

.mini-label {
    font-size: 9px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

.biz-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 8px 14px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: 0.2s;
    flex: 1;
    min-width: 90px;
}

.btn-toggle { background: #fbbf24; color: #000; }
.btn-plan { background: #3b82f6; color: white; }
.btn-reset { background: #f59e0b; color: white; }
.btn-view { background: #10b981; color: white; }
.btn-delete { background: #ef4444; color: white; }

.empty {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 80px;
    margin-bottom: 20px;
    opacity: 0.5;
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
    background: #1a1f33;
    border-radius: 20px;
    padding: 30px;
    max-width: 400px;
    width: 100%;
    border: 1px solid #2a3047;
}

@media (max-width: 1024px) {
    .grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
}

::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-thumb { background: #2a3047; border-radius: 4px; }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="brand">
        <div class="crown">👑</div>
        <div>
            <div class="brand-text">Bizflow <span>HQ</span></div>
            <div class="brand-sub">Platform Owner Control Panel</div>
        </div>
    </div>
    
    <div class="header-actions">
        <div class="user-chip">
            👤 <?= htmlspecialchars($_SESSION['super_admin_name'] ?? 'Admin') ?>
        </div>
        <a href="super_logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</div>

<div class="container">
    
    <h1 class="page-title">📊 Platform Dashboard</h1>
    <p class="page-sub">Manage all businesses on BizFlow platform</p>
    
    <?php if ($message): ?>
        <div class="alert success"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- STATS -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(168,85,247,0.15);color:#c084fc;">🏪</div>
            <div>
                <div class="stat-label">Total Businesses</div>
                <div class="stat-value"><?= $stats['total_businesses'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:#10b981;">✅</div>
            <div>
                <div class="stat-label">Active</div>
                <div class="stat-value"><?= $stats['active_businesses'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(251,191,36,0.15);color:#fbbf24;">💰</div>
            <div>
                <div class="stat-label">Monthly Revenue</div>
                <div class="stat-value"><?= number_format($revenue, 0) ?> DT</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;">📦</div>
            <div>
                <div class="stat-label">Total Sales</div>
                <div class="stat-value"><?= number_format($stats['total_sales'], 0) ?> DT</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(236,72,153,0.15);color:#ec4899;">👥</div>
            <div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $stats['total_users'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(168,85,247,0.15);color:#c084fc;">📊</div>
            <div>
                <div class="stat-label">Sales Today</div>
                <div class="stat-value"><?= $stats['sales_today'] ?></div>
            </div>
        </div>
    </div>
    
    <div class="grid">
        
        <!-- CREATE BUSINESS FORM -->
        <div>
            <div class="card">
                <div class="card-title">➕ Create New Business</div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_business">
                    
                    <div class="section-label">🏪 Business Info</div>
                    
                    <div class="form-group">
                        <label>Business Name *</label>
                        <input type="text" name="business_name" placeholder="e.g. Mohamed's Clothing" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Emoji</label>
                            <input type="text" name="emoji" placeholder="🏪" maxlength="2" value="🏪">
                        </div>
                        <div class="form-group">
                            <label>URL Slug</label>
                            <input type="text" name="slug" placeholder="mohamed-clothes">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Business Type</label>
                            <select name="type">
                                <option value="general">📦 General</option>
                                <option value="clothing">👔 Clothing</option>
                                <option value="electronics">📱 Electronics</option>
                                <option value="food">🍔 Food</option>
                                <option value="pharmacy">💊 Pharmacy</option>
                                <option value="bakery">🥐 Bakery</option>
                                <option value="grocery">🛒 Grocery</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Currency</label>
                            <input type="text" name="currency" value="DT" maxlength="5">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription Plan</label>
                        <select name="plan">
                            <option value="free">🆓 Free</option>
                            <option value="starter" selected>⭐ Starter (30 DT/mo)</option>
                            <option value="pro">🚀 Pro (75 DT/mo)</option>
                            <option value="enterprise">💎 Enterprise (150 DT/mo)</option>
                        </select>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <div class="section-label">👤 Owner Credentials</div>
                    
                    <div class="form-group">
                        <label>Owner Full Name *</label>
                        <input type="text" name="owner_name" placeholder="Mohamed Ben Ali" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Owner Email *</label>
                        <input type="email" name="owner_email" placeholder="mohamed@email.com" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Owner Password *</label>
                            <input type="text" name="owner_password" placeholder="Min 6 chars" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="owner_phone" placeholder="+216...">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">🚀 Create Business</button>
                </form>
            </div>
        </div>
        
        <!-- BUSINESSES LIST -->
        <div>
            <div class="card">
                <div class="card-title">🏪 All Businesses (<?= count($businesses) ?>)</div>
                
                <?php if (empty($businesses)): ?>
                    <div class="empty">
                        <div class="empty-icon">🏪</div>
                        <h3>No businesses yet</h3>
                        <p style="margin-top:8px;font-size:13px;">Create your first business on the left!</p>
                    </div>
                <?php else: ?>
                    <div class="business-list">
                        <?php foreach ($businesses as $b): ?>
                            <div class="biz-card <?= $b['is_active'] ? 'active' : 'inactive' ?>">
                                
                                <div class="biz-top">
                                    <div class="biz-info">
                                        <div class="biz-emoji"><?= htmlspecialchars($b['logo_emoji'] ?? '🏪') ?></div>
                                        <div>
                                            <div class="biz-name"><?= htmlspecialchars($b['name']) ?></div>
                                            <div class="biz-owner">
                                                👤 <?= htmlspecialchars($b['owner_name'] ?? 'No owner') ?>
                                                <?php if ($b['owner_email']): ?>
                                                    · 📧 <?= htmlspecialchars($b['owner_email']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="plan-badge plan-<?= $b['plan'] ?>">
                                        <?= strtoupper($b['plan']) ?>
                                    </span>
                                </div>
                                
                                <div class="biz-stats">
                                    <div class="mini-stat">
                                        <div class="mini-val"><?= $b['user_count'] ?></div>
                                        <div class="mini-label">Users</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-val"><?= $b['product_count'] ?></div>
                                        <div class="mini-label">Products</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-val"><?= $b['sale_count'] ?></div>
                                        <div class="mini-label">Sales</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-val"><?= number_format($b['total_revenue'], 0) ?></div>
                                        <div class="mini-label">DT Revenue</div>
                                    </div>
                                </div>
                                
                                <div class="biz-actions">
                                    <!-- Toggle Active -->
                                    <form method="POST" style="display:contents;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="business_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="action-btn btn-toggle">
                                            <?= $b['is_active'] ? '⏸️ Suspend' : '▶️ Activate' ?>
                                        </button>
                                    </form>
                                    
                                    <!-- Change Plan -->
                                    <button class="action-btn btn-plan" onclick="changePlan(<?= $b['id'] ?>, '<?= $b['plan'] ?>')">
                                        💎 Plan
                                    </button>
                                    
                                    <!-- Reset Password -->
                                    <?php if ($b['owner_id']): ?>
                                        <button class="action-btn btn-reset" onclick="resetPass(<?= $b['owner_id'] ?>, '<?= htmlspecialchars($b['owner_name'], ENT_QUOTES) ?>')">
                                            🔑 Reset
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Delete -->
                                    <button class="action-btn btn-delete" onclick="deleteBiz(<?= $b['id'] ?>, '<?= htmlspecialchars($b['name'], ENT_QUOTES) ?>')">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<div class="modal" id="planModal">
    <div class="modal-content">
        <h3 style="font-family:'Playfair Display',serif;font-size:22px;margin-bottom:20px;">💎 Change Plan</h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_plan">
            <input type="hidden" name="business_id" id="planBizId">
            
            <div class="form-group">
                <label>Select Plan</label>
                <select name="plan" id="planSelect">
                    <option value="free">🆓 Free (0 DT)</option>
                    <option value="starter">⭐ Starter (30 DT/mo)</option>
                    <option value="pro">🚀 Pro (75 DT/mo)</option>
                    <option value="enterprise">💎 Enterprise (150 DT/mo)</option>
                </select>
            </div>
            
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeModal('planModal')" class="action-btn" style="background:#6b7280;color:white;flex:1;">Cancel</button>
                <button type="submit" class="btn" style="flex:1;margin:0;">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="resetModal">
    <div class="modal-content">
        <h3 style="font-family:'Playfair Display',serif;font-size:22px;margin-bottom:8px;">🔑 Reset Password</h3>
        <p style="color:#9ca3af;font-size:13px;margin-bottom:20px;">For owner: <strong id="resetName"></strong></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            
            <div class="form-group">
                <label>New Password (min 6 chars)</label>
                <input type="text" name="new_password" required minlength="6" placeholder="NewPass123">
            </div>
            
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeModal('resetModal')" class="action-btn" style="background:#6b7280;color:white;flex:1;">Cancel</button>
                <button type="submit" class="btn" style="flex:1;margin:0;">Reset</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="deleteModal">
    <div class="modal-content">
        <h3 style="font-family:'Playfair Display',serif;font-size:22px;margin-bottom:8px;color:#ef4444;">🗑️ Delete Business</h3>
        <p style="color:#9ca3af;font-size:13px;margin-bottom:8px;">⚠️ This will suspend the business and all data!</p>
        <p style="color:#fbbf24;font-size:13px;margin-bottom:20px;">Type business name to confirm: <strong id="deleteName"></strong></p>
        <form method="POST">
            <input type="hidden" name="action" value="delete_business">
            <input type="hidden" name="business_id" id="deleteBizId">
            
            <div class="form-group">
                <input type="text" name="confirm_name" required placeholder="Business name here...">
            </div>
            
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeModal('deleteModal')" class="action-btn" style="background:#6b7280;color:white;flex:1;">Cancel</button>
                <button type="submit" class="action-btn btn-delete" style="flex:1;">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function changePlan(id, currentPlan) {
    document.getElementById('planBizId').value = id;
    document.getElementById('planSelect').value = currentPlan;
    document.getElementById('planModal').classList.add('show');
}

function resetPass(userId, name) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetName').textContent = name;
    document.getElementById('resetModal').classList.add('show');
}

function deleteBiz(id, name) {
    document.getElementById('deleteBizId').value = id;
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close on outside click
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => {
        if (e.target === m) closeModal(m.id);
    });
});
</script>

</body>
</html>
