<?php
session_start();
require_once 'db.php';

// Already logged in as admin? Go to admin panel
if (isset($_SESSION['admin_user_id'])) {
    header("Location: admin.php");
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['admin_csrf']) {
        $error = "⚠️ Security error.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (!$email || !$password) {
            $error = "⚠️ Fill all fields.";
        } else {
            $stmt = $conn->prepare("
                SELECT u.*, b.name as business_name, b.is_active as biz_active, b.logo_emoji
                FROM users u
                JOIN businesses b ON b.id = u.business_id
                WHERE u.email = ? AND u.is_active = 1
                AND u.role IN ('owner', 'manager', 'admin')
                LIMIT 1
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                
                if (!$user['biz_active']) {
                    $error = "🚫 Your business is suspended. Contact support.";
                } else {
                    $isValid = password_verify($password, $user['password']) 
                            || hash_equals($user['password'], $password);
                    
                    if ($isValid) {
                        session_regenerate_id(true);
                        
                        // ✅ Use admin_* session keys (separate from POS)
                        $_SESSION['admin_user_id'] = $user['id'];
                        $_SESSION['admin_user_name'] = $user['full_name'];
                        $_SESSION['admin_user_email'] = $user['email'];
                        $_SESSION['admin_user_role'] = $user['role'];
                        $_SESSION['admin_business_id'] = $user['business_id'];
                        $_SESSION['admin_business_name'] = $user['business_name'];
                        $_SESSION['admin_business_logo'] = $user['logo_emoji'];
                        $_SESSION['admin_login_time'] = time();
                        
                        // Also set generic for compatibility
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['business_id'] = $user['business_id'];
                        $_SESSION['business_name'] = $user['business_name'];
                        $_SESSION['business_logo'] = $user['logo_emoji'];
                        
                        @$conn->query("UPDATE users SET last_login=NOW() WHERE id=" . intval($user['id']));
                        
                        header("Location: admin.php");
                        exit;
                    } else {
                        $error = "❌ Wrong password.";
                    }
                }
            } else {
                $error = "❌ Email not found or not an admin account.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#3b82f6">
<title>Admin Login · BizFlow</title>
<link rel="manifest" href="manifest_admin.json">
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

.login-card {
    background: linear-gradient(135deg, #1a1f33, #151929);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 28px;
    padding: 45px 35px;
    text-align: center;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 25px 80px rgba(0,0,0,0.5);
}

.logo-wrapper {
    width: 80px; height: 80px;
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
    margin-bottom: 4px;
}
.logo-text span { 
    background: linear-gradient(135deg, #3b82f6, #60a5fa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.badge {
    display: inline-block;
    background: rgba(59,130,246,0.15);
    color: #60a5fa;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    margin: 15px 0 25px;
    border: 1px solid rgba(59,130,246,0.3);
    text-transform: uppercase;
    letter-spacing: 1.5px;
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

@keyframes shake {
    0%,100% { transform: translateX(0); }
    25% { transform: translateX(-8px); }
    75% { transform: translateX(8px); }
}

.form-group { text-align: left; margin-bottom: 18px; }

.form-group label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 8px;
}

.input-wrapper { position: relative; }

.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    opacity: 0.5;
}

.form-input {
    width: 100%;
    background: #0a0e1a;
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 14px;
    padding: 15px 16px 15px 46px;
    color: #fff;
    font-size: 16px;
    font-family: inherit;
}

.form-input:focus {
    outline: none;
    border-color: #3b82f6;
    background: #0f1424;
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
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(59,130,246,0.5);
}

.footer-links {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
    font-size: 12px;
    color: #475569;
    line-height: 1.8;
}

.footer-links a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 700;
}

.alt-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 15px;
}

.alt-btn {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 10px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    transition: 0.2s;
}

.alt-btn:hover {
    border-color: rgba(59,130,246,0.5);
    color: #60a5fa;
}

.security {
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

@media (max-width: 480px) {
    .login-card { padding: 35px 25px; }
    .logo-text { font-size: 26px; }
}
</style>
</head>
<body>

<div class="login-card">
    <div class="logo-wrapper">💼</div>
    <div class="logo-text">Biz<span>Flow</span></div>
    <div class="badge">👨‍💼 Admin Portal</div>
    
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= $_SESSION['admin_csrf'] ?>">
        
        <div class="form-group">
            <label>📧 Email Address</label>
            <div class="input-wrapper">
                <span class="input-icon">📧</span>
                <input type="email" name="email" class="form-input" placeholder="owner@business.com" required autofocus>
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
        
        <button type="submit" class="btn-login">🚀 Sign In to Admin</button>
    </form>
    
    <div class="footer-links">
        <div style="margin-bottom:8px;">Other access:</div>
        <div class="alt-options">
            <a href="pos_login.php" class="alt-btn">🛒 Cashier POS</a>
            <a href="super_login.php" class="alt-btn">👑 HQ Admin</a>
        </div>
    </div>
    
    <div class="security">🛡️ Secure SSL Connection</div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
