<?php
session_start();
require_once 'db.php';

// Already logged in as cashier? Go to POS
if (isset($_SESSION['pos_user_id'])) {
    header("Location: pos.php");
    exit;
}

if (empty($_SESSION['pos_csrf'])) {
    $_SESSION['pos_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['pos_csrf']) {
        $error = "⚠️ Security error.";
    } else {
        $pin = trim($_POST['pin'] ?? '');
        
        if (!$pin) {
            $error = "⚠️ Please enter your PIN.";
        } else {
            $stmt = $conn->prepare("
                SELECT u.*, b.name as business_name, b.is_active as biz_active, b.logo_emoji
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
                    $error = "🚫 Business suspended.";
                } else {
                    session_regenerate_id(true);
                    
                    // ✅ Use pos_* session keys (separate from admin)
                    $_SESSION['pos_user_id'] = $user['id'];
                    $_SESSION['pos_user_name'] = $user['full_name'];
                    $_SESSION['pos_user_role'] = $user['role'];
                    $_SESSION['pos_business_id'] = $user['business_id'];
                    $_SESSION['pos_business_name'] = $user['business_name'];
                    $_SESSION['pos_business_logo'] = $user['logo_emoji'];
                    $_SESSION['pos_login_time'] = time();
                    
                    // Also set generic for compatibility
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['business_id'] = $user['business_id'];
                    $_SESSION['business_name'] = $user['business_name'];
                    $_SESSION['business_logo'] = $user['logo_emoji'];
                    
                    @$conn->query("UPDATE users SET last_login=NOW() WHERE id=" . intval($user['id']));
                    
                    header("Location: pos.php");
                    exit;
                }
            } else {
                $error = "❌ Invalid PIN.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- 🛒 POS Specific Settings -->
<meta name="theme-color" content="#10b981">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="POS">
<meta name="mobile-web-app-capable" content="yes">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- 🟢 GREEN POS Icons -->
<link rel="apple-touch-icon" href="https://api.dicebear.com/7.x/shapes/png?seed=POSCart&backgroundColor=10b981&size=180">
<link rel="apple-touch-icon" sizes="120x120" href="https://api.dicebear.com/7.x/shapes/png?seed=POSCart&backgroundColor=10b981&size=120">
<link rel="apple-touch-icon" sizes="152x152" href="https://api.dicebear.com/7.x/shapes/png?seed=POSCart&backgroundColor=10b981&size=152">
<link rel="apple-touch-icon" sizes="180x180" href="https://api.dicebear.com/7.x/shapes/png?seed=POSCart&backgroundColor=10b981&size=180">

<link rel="icon" type="image/png" href="https://api.dicebear.com/7.x/shapes/png?seed=POSCart&backgroundColor=10b981&size=32">

<!-- POS Manifest -->
<link rel="manifest" href="manifest_pos.json">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; user-select:none; }

body {
    background: #0a0e1a;
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(16,185,129,0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(52,211,153,0.1) 0%, transparent 50%);
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
    padding: 40px 30px;
    text-align: center;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 25px 80px rgba(0,0,0,0.5);
}

.logo-wrapper {
    width: 90px; height: 90px;
    background: linear-gradient(135deg, #10b981, #34d399);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    margin: 0 auto 20px;
    box-shadow: 0 15px 40px rgba(16,185,129,0.4);
    animation: pulse 3s infinite;
}

@keyframes pulse {
    0%,100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.logo-text {
    font-family: 'Playfair Display', serif;
    font-size: 30px;
    font-weight: 700;
    margin-bottom: 4px;
}
.logo-text span { 
    background: linear-gradient(135deg, #10b981, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.badge {
    display: inline-block;
    background: rgba(16,185,129,0.15);
    color: #34d399;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    margin: 15px 0 30px;
    border: 1px solid rgba(16,185,129,0.3);
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 600;
    animation: shake 0.4s;
}
.alert.error {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    color: #fca5a5;
}

@keyframes shake {
    0%,100% { transform: translateX(0); }
    25% { transform: translateX(-8px); }
    75% { transform: translateX(8px); }
}

.pin-label {
    font-size: 14px;
    color: #94a3b8;
    margin-bottom: 16px;
    font-weight: 600;
}

.pin-display {
    background: #0a0e1a;
    border: 2px solid rgba(255,255,255,0.06);
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 20px;
    font-size: 42px;
    letter-spacing: 16px;
    font-weight: 900;
    color: #10b981;
    min-height: 90px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.pin-display.empty {
    color: rgba(255,255,255,0.1);
}

/* PIN PAD */
.pin-pad {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}

.pin-btn {
    background: #1a1f33;
    border: 1px solid rgba(255,255,255,0.06);
    color: white;
    padding: 22px;
    border-radius: 16px;
    font-size: 26px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: 0.1s;
    -webkit-tap-highlight-color: transparent;
}

.pin-btn:active {
    background: rgba(16,185,129,0.2);
    border-color: #10b981;
    transform: scale(0.95);
}

.pin-btn.clear {
    background: rgba(239,68,68,0.1);
    color: #fca5a5;
    font-size: 14px;
    font-weight: 800;
}

.pin-btn.enter {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
    font-size: 14px;
    font-weight: 800;
}

.pin-btn.enter:active {
    background: linear-gradient(135deg, #059669, #10b981);
}

.footer-links {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
    font-size: 12px;
    color: #475569;
}

.footer-links a {
    color: #10b981;
    text-decoration: none;
    font-weight: 700;
}

@media (max-width: 480px) {
    .login-card { padding: 30px 20px; }
    .pin-display { padding: 18px; font-size: 36px; min-height: 70px; }
    .pin-btn { padding: 18px; font-size: 24px; }
}
</style>
</head>
<body>

<div class="login-card">
    <div class="logo-wrapper">🛒</div>
    <div class="logo-text">POS <span>Terminal</span></div>
    <div class="badge">⚡ Cashier Quick Login</div>
    
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="pin-label">Enter your PIN</div>
    
    <div class="pin-display empty" id="pinDisplay">••••</div>
    
    <div class="pin-pad">
        <button class="pin-btn" onclick="addDigit('1')">1</button>
        <button class="pin-btn" onclick="addDigit('2')">2</button>
        <button class="pin-btn" onclick="addDigit('3')">3</button>
        <button class="pin-btn" onclick="addDigit('4')">4</button>
        <button class="pin-btn" onclick="addDigit('5')">5</button>
        <button class="pin-btn" onclick="addDigit('6')">6</button>
        <button class="pin-btn" onclick="addDigit('7')">7</button>
        <button class="pin-btn" onclick="addDigit('8')">8</button>
        <button class="pin-btn" onclick="addDigit('9')">9</button>
        <button class="pin-btn clear" onclick="clearPin()">CLR</button>
        <button class="pin-btn" onclick="addDigit('0')">0</button>
        <button class="pin-btn enter" onclick="submitPin()">✓</button>
    </div>
    
    <form method="POST" id="pinForm" style="display:none;">
        <input type="hidden" name="csrf" value="<?= $_SESSION['pos_csrf'] ?>">
        <input type="hidden" name="pin" id="pinInput">
    </form>
    
    <div class="footer-links">
        Owner/Manager? <a href="admin_login.php">Admin Login →</a>
    </div>
</div>

<script>
let currentPin = '';
const maxLength = 10;

function addDigit(d) {
    if (currentPin.length >= maxLength) return;
    currentPin += d;
    updateDisplay();
    
    // Haptic feedback
    if (navigator.vibrate) navigator.vibrate(30);
    
    // Auto-submit at 4 digits (most common)
    if (currentPin.length === 4) {
        setTimeout(() => submitPin(), 200);
    }
}

function clearPin() {
    currentPin = '';
    updateDisplay();
    if (navigator.vibrate) navigator.vibrate([30, 30, 30]);
}

function updateDisplay() {
    const display = document.getElementById('pinDisplay');
    if (currentPin.length === 0) {
        display.textContent = '••••';
        display.classList.add('empty');
    } else {
        display.textContent = '•'.repeat(currentPin.length);
        display.classList.remove('empty');
    }
}

function submitPin() {
    if (currentPin.length < 4) {
        alert('⚠️ PIN must be at least 4 digits');
        return;
    }
    
    document.getElementById('pinInput').value = currentPin;
    document.getElementById('pinForm').submit();
}

// Keyboard support
document.addEventListener('keydown', e => {
    if (e.key >= '0' && e.key <= '9') {
        addDigit(e.key);
    } else if (e.key === 'Backspace') {
        currentPin = currentPin.slice(0, -1);
        updateDisplay();
    } else if (e.key === 'Enter') {
        submitPin();
    } else if (e.key === 'Escape') {
        clearPin();
    }
});
</script>

</body>
</html>
