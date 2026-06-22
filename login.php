<?php
session_start();
require_once 'db.php';
require_once 'theme.php';

// ✅ Already logged in? Redirect
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if (in_array($role, ['cashier', 'worker'])) {
        header("Location: pos.php");
    } else {
        header("Location: admin.php");
    }
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// ========================================
// 🔐 HANDLE LOGIN
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF check
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
        $error = "⚠️ Security token invalid. Refresh and try again.";
    } else {
        $loginType = $_POST['login_type'] ?? 'password';
        
        // ===== PASSWORD LOGIN (Owner/Manager) =====
        if ($loginType === 'password') {
            $businessId = intval($_POST['business_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            if (!$businessId || !$username || !$password) {
                $error = "⚠️ Please fill in all fields.";
            } else {
                $stmt = $conn->prepare("
                    SELECT u.*, b.name as business_name, b.is_active as biz_active
                    FROM users u
                    JOIN businesses b ON b.id = u.business_id
                    WHERE u.username = ? AND u.business_id = ? AND u.is_active = 1
                    LIMIT 1
                ");
                $stmt->bind_param("si", $username, $businessId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($user = $result->fetch_assoc()) {
                    
                    if (!$user['biz_active']) {
                        $error = "🚫 This business is suspended. Contact support.";
                    } else {
                        // Verify password
                        $isValid = password_verify($password, $user['password']) 
                                || hash_equals($user['password'], $password);
                        
                        if ($isValid) {
                            // ✅ Login success!
                            session_regenerate_id(true);
                            
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['full_name'];
                            $_SESSION['user_username'] = $user['username'];
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['business_id'] = $user['business_id'];
                            $_SESSION['business_name'] = $user['business_name'];
                            $_SESSION['login_time'] = time();
                            
                            // Update last login
                            @$conn->query("UPDATE users SET last_login=NOW() WHERE id=" . intval($user['id']));
                            
                            // Audit log
                            auditLog('login', 'user', $user['id'], "User {$user['username']} logged in");
                            
                            // Redirect based on role
                            if (in_array($user['role'], ['cashier', 'worker'])) {
                                header("Location: pos.php");
                            } else {
                                header("Location: admin.php");
                            }
                            exit;
                        } else {
                            $error = "❌ Invalid username or password.";
                        }
                    }
                } else {
                    $error = "❌ User not found.";
                }
            }
        }
        
        // ===== PIN LOGIN (Cashier Quick Access) =====
        else if ($loginType === 'pin') {
            $pin = trim($_POST['pin'] ?? '');
            
            if (!$pin) {
                $error = "⚠️ Please enter your PIN.";
            } else {
                $stmt = $conn->prepare("
                    SELECT u.*, b.name as business_name, b.is_active as biz_active
                    FROM users u
                    JOIN businesses b ON b.id = u.business_id
                    WHERE u.pin = ? AND u.is_active = 1
                    LIMIT 1
                ");
                $stmt->bind_param("s", $pin);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($user = $result->fetch_assoc()) {
                    
                    if (!$user['biz_active']) {
                        $error = "🚫 This business is suspended.";
                    } else {
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['business_id'] = $user['business_id'];
                        $_SESSION['business_name'] = $user['business_name'];
                        $_SESSION['login_time'] = time();
                        
                        @$conn->query("UPDATE users SET last_login=NOW() WHERE id=" . intval($user['id']));
                        auditLog('login_pin', 'user', $user['id'], "PIN login: {$user['username']}");
                        
                        // PIN users always go to POS
                        header("Location: pos.php");
                        exit;
                    }
                } else {
                    $error = "❌ Invalid PIN.";
                }
            }
        }
    }
}

// Get list of active businesses for dropdown
$businesses = [];
$result = $conn->query("SELECT id, name, logo_emoji FROM businesses WHERE is_active = 1 ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#3b82f6">
<title>BizFlow · Login</title>
<link rel="manifest" href="manifest.json">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }

body {
    background: #0a0e1a;
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(59,130,246,0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(96,165,250,0.1) 0%, transparent 50%);
    color: white;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-wrapper {
    width: 100%;
    max-width: 460px;
}

.login-card {
    background: linear-gradient(135deg, #1a1f33, #151929);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 28px;
    padding: 45px 35px;
    text-align: center;
    box-shadow: 0 25px 80px rgba(0,0,0,0.5);
}

.logo-wrapper {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #3b82f6, #60a5fa);
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
    margin: 0 auto 20px;
    box-shadow: 0 15px 40px rgba(59,130,246,0.4);
    animation: pulse 3s infinite;
}

@keyframes pulse {
    0%,100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.logo-text {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}
.logo-text span { 
    background: linear-gradient(135deg, #3b82f6, #60a5fa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.tagline {
    color: #94a3b8;
    font-size: 13px;
    margin-bottom: 30px;
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 600;
    text-align: left;
}
.alert.error {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: #fca5a5;
    animation: shake 0.4s;
}
.alert.success {
    background: rgba(16,185,129,0.1);
    border: 1px solid rgba(16,185,129,0.3);
    color: #86efac;
}

@keyframes shake {
    0%,100% { transform: translateX(0); }
    25% { transform: translateX(-8px); }
    75% { transform: translateX(8px); }
}

/* Tabs */
.tabs {
    display: flex;
    gap: 6px;
    background: #0a0e1a;
    padding: 5px;
    border-radius: 14px;
    margin-bottom: 25px;
}

.tab {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    transition: 0.2s;
    text-align: center;
}

.tab.active {
    background: linear-gradient(135deg, #3b82f6, #60a5fa);
    color: white;
    box-shadow: 0 6px 20px rgba(59,130,246,0.3);
}

/* Form */
.form-content { display: none; }
.form-content.active { display: block; }

.form-group {
    text-align: left;
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 8px;
}

.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    opacity: 0.5;
}

.form-input, .form-select {
    width: 100%;
    background: #0a0e1a;
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 14px;
    padding: 15px 16px 15px 46px;
    color: #fff;
    font-size: 15px;
    font-family: inherit;
    transition: 0.2s;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: #3b82f6;
}

.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%233b82f6' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 40px;
}

.form-select option {
    background: #0a0e1a;
    color: white;
}

.pin-input {
    text-align: center !important;
    font-size: 32px !important;
    letter-spacing: 14px !important;
    padding: 18px 16px !important;
    font-weight: 800 !important;
}

.btn-login {
    width: 100%;
    background: linear-gradient(135deg, #3b82f6, #60a5fa);
    color: #fff;
    border: none;
    padding: 16px;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    box-shadow: 0 10px 30px rgba(59,130,246,0.3);
    margin-top: 8px;
    transition: 0.2s;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(59,130,246,0.5);
}

.toggle-password {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    font-size: 14px;
    padding: 4px;
}

.footer-text {
    margin-top: 25px;
    font-size: 12px;
    color: #475569;
    line-height: 1.6;
}

.footer-text a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 700;
}

.security-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16,185,129,0.1);
    color: #10b981;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 15px;
}

.no-business {
    background: rgba(251,191,36,0.1);
    border: 1px solid rgba(251,191,36,0.3);
    color: #fbbf24;
    padding: 16px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 20px;
    font-size: 13px;
}

@media (max-width: 480px) {
    .login-card { padding: 35px 25px; }
    .logo-text { font-size: 26px; }
}
</style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="logo-wrapper">💼</div>
        <div class="logo-text">Biz<span>Flow</span></div>
        <div class="tagline">Small Business Manager</div>
        
        <?php if ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if (empty($businesses)): ?>
            <div class="no-business">
                ⚠️ No businesses registered yet.<br>
                <a href="super_login.php" style="color:#fbbf24;text-decoration:underline;">
                    Platform Admin Login →
                </a>
            </div>
        <?php else: ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('password', this)">🔐 Login</div>
            <div class="tab" onclick="switchTab('pin', this)">🔢 Quick PIN</div>
        </div>
        
        <!-- PASSWORD LOGIN -->
        <div class="form-content active" id="form-password">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="login_type" value="password">
                
                <div class="form-group">
                    <label>🏪 Business</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🏪</span>
                        <select name="business_id" class="form-select" required>
                            <option value="">Select your business...</option>
                            <?php foreach ($businesses as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= htmlspecialchars($b['logo_emoji'] ?? '🏪') ?> 
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>👤 Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" class="form-input" placeholder="Enter username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>🔒 Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" id="passwordInput" class="form-input" placeholder="••••••••" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">🚀 Sign In</button>
            </form>
        </div>
        
        <!-- PIN LOGIN -->
        <div class="form-content" id="form-pin">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="login_type" value="pin">
                
                <div class="form-group">
                    <label style="text-align:center;">🔢 Enter Your PIN</label>
                    <input type="password" name="pin" class="form-input pin-input" placeholder="••••" maxlength="10" inputmode="numeric" required autofocus>
                </div>
                
                <button type="submit" class="btn-login">⚡ Quick Login</button>
            </form>
            
            <div style="text-align:center;margin-top:15px;font-size:12px;color:#64748b;">
                💡 For cashiers - fastest access
            </div>
        </div>
        
        <?php endif; ?>
        
        <div class="footer-text">
            New business? <a href="register.php">Create Account</a><br>
            Platform admin? <a href="super_login.php">👑 HQ Login</a>
        </div>
        
        <div class="security-badge">
            🛡️ Secure Multi-Tenant Platform
        </div>
    </div>
</div>

<script>
function switchTab(tab, el) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    
    document.querySelectorAll('.form-content').forEach(f => f.classList.remove('active'));
    document.getElementById('form-' + tab).classList.add('active');
    
    // Auto-focus
    if (tab === 'pin') {
        setTimeout(() => document.querySelector('.pin-input').focus(), 100);
    }
}

function togglePassword() {
    const input = document.getElementById('passwordInput');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
