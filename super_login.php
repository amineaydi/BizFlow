<?php
session_start();
require_once 'db.php';

// Already logged in as super admin? Go to panel
if (isset($_SESSION['super_admin_id'])) {
    header("Location: super_admin.php");
    exit;
}

// CSRF token
if (empty($_SESSION['super_csrf'])) {
    $_SESSION['super_csrf'] = bin2hex(random_bytes(32));
}

// Rate limiting (anti-brute force)
if (!isset($_SESSION['super_attempts'])) {
    $_SESSION['super_attempts'] = 0;
    $_SESSION['super_last_attempt'] = time();
}

if (time() - $_SESSION['super_last_attempt'] > 900) {
    $_SESSION['super_attempts'] = 0;
}

$isBlocked = $_SESSION['super_attempts'] >= 5;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['super_csrf']) {
        $error = "⚠️ Security error.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (!$username || !$password) {
            $error = "⚠️ Fill all fields.";
        } else {
            $stmt = $conn->prepare("
                SELECT * FROM super_admins 
                WHERE username = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($admin = $result->fetch_assoc()) {
                $isValid = password_verify($password, $admin['password']) 
                        || hash_equals($admin['password'], $password);
                
                if ($isValid) {
                    session_regenerate_id(true);
                    
                    $_SESSION['super_admin_id'] = $admin['id'];
                    $_SESSION['super_admin_name'] = $admin['full_name'];
                    $_SESSION['super_admin_username'] = $admin['username'];
                    $_SESSION['super_login_time'] = time();
                    $_SESSION['super_attempts'] = 0;
                    
                    @$conn->query("UPDATE super_admins SET last_login=NOW() WHERE id=" . intval($admin['id']));
                    
                    header("Location: super_admin.php");
                    exit;
                } else {
                    $_SESSION['super_attempts']++;
                    $_SESSION['super_last_attempt'] = time();
                    $error = "❌ Wrong credentials. " . (5 - $_SESSION['super_attempts']) . " tries left.";
                }
            } else {
                $_SESSION['super_attempts']++;
                $_SESSION['super_last_attempt'] = time();
                $error = "❌ Wrong credentials.";
            }
        }
    }
}

if ($isBlocked) {
    $mins = ceil((900 - (time() - $_SESSION['super_last_attempt'])) / 60);
    $error = "🚫 Too many attempts. Try in $mins min.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>👑 Platform Admin · BizFlow HQ</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }

body {
    background: #0a0e1a;
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(168,85,247,0.2) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(236,72,153,0.15) 0%, transparent 50%);
    color: white;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.box {
    background: linear-gradient(135deg, #1a1f33, #151929);
    border: 2px solid rgba(168,85,247,0.3);
    border-radius: 28px;
    padding: 50px 40px;
    max-width: 440px;
    width: 100%;
    text-align: center;
    box-shadow: 0 25px 80px rgba(168,85,247,0.2);
}

.crown {
    font-size: 70px;
    margin-bottom: 15px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}

h1 {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    margin-bottom: 8px;
}
h1 span { 
    background: linear-gradient(135deg, #a855f7, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.sub { color: #94a3b8; font-size: 13px; margin-bottom: 25px; }

.badge {
    display: inline-block;
    background: rgba(168,85,247,0.15);
    color: #c084fc;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 30px;
    border: 1px solid rgba(168,85,247,0.3);
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

.alert {
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 13px;
}
.alert.error {
    background: rgba(239,68,68,0.15);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.3);
}

.form-group { text-align: left; margin-bottom: 18px; }

label {
    display: block;
    font-size: 11px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 700;
    margin-bottom: 8px;
}

.input-wrap {
    position: relative;
}

.input-wrap .icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.5;
}

input {
    width: 100%;
    padding: 15px 16px 15px 46px;
    background: #0a0e1a;
    border: 2px solid #2a3047;
    border-radius: 14px;
    color: white;
    font-size: 15px;
    transition: 0.2s;
}

input:focus {
    outline: none;
    border-color: #a855f7;
    background: #0f1424;
}

button {
    width: 100%;
    background: linear-gradient(135deg, #a855f7, #ec4899);
    color: white;
    border: none;
    padding: 18px;
    border-radius: 14px;
    font-weight: 800;
    font-size: 14px;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-top: 10px;
    box-shadow: 0 10px 30px rgba(168,85,247,0.3);
    transition: 0.2s;
}

button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(168,85,247,0.5);
}

button:disabled { opacity: 0.5; cursor: not-allowed; }

.alt {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
    font-size: 12px;
    color: #6b7280;
}

.alt a {
    color: #a855f7;
    text-decoration: none;
    font-weight: 700;
}

.security {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    margin-top: 15px;
}
</style>
</head>
<body>

<div class="box">
    <div class="crown">👑</div>
    <h1>Bizflow <span>HQ</span></h1>
    <div class="badge">🛡️ Platform Owner Access</div>
    <div class="sub">Restricted area • Authorized personnel only</div>
    
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= $_SESSION['super_csrf'] ?>">
        
        <div class="form-group">
            <label>👤 HQ Username</label>
            <div class="input-wrap">
                <span class="icon">👤</span>
                <input type="text" name="username" placeholder="amine" autofocus required <?= $isBlocked ? 'disabled' : '' ?>>
            </div>
        </div>
        
        <div class="form-group">
            <label>🔒 HQ Password</label>
            <div class="input-wrap">
                <span class="icon">🔒</span>
                <input type="password" name="password" placeholder="••••••••" required <?= $isBlocked ? 'disabled' : '' ?>>
            </div>
        </div>
        
        <button type="submit" <?= $isBlocked ? 'disabled' : '' ?>>
            🚀 Enter HQ
        </button>
    </form>
    
    <div class="alt">
        Business owner? <a href="login.php">Regular Login →</a>
    </div>
    
    <div class="security">🔒 256-bit Encrypted</div>
</div>

</body>
</html>
