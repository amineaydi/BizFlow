<?php
session_start();

// Smart redirect based on session
if (isset($_SESSION['super_admin_id'])) {
    header("Location: super_admin.php");
} elseif (isset($_SESSION['admin_user_id'])) {
    header("Location: admin.php");
} elseif (isset($_SESSION['pos_user_id'])) {
    header("Location: pos.php");
} else {
    // Show selection page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BizFlow - Choose Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body {
            background: #0a0e1a;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(59,130,246,0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(16,185,129,0.1) 0%, transparent 50%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .logo {
            width: 90px; height: 90px;
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 20px;
            box-shadow: 0 15px 40px rgba(59,130,246,0.4);
        }
        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 40px;
            margin-bottom: 10px;
        }
        h1 span {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p { color: #94a3b8; margin-bottom: 35px; font-size: 15px; }
        .options { display: grid; gap: 14px; }
        .option {
            background: linear-gradient(135deg, #1a1f33, #151929);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 18px;
            padding: 24px;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: 0.2s;
            text-align: left;
        }
        .option:hover {
            transform: translateY(-3px);
            border-color: var(--c);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
        }
        .option-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        .option-title {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .option-sub { font-size: 12px; color: #94a3b8; }
        .arrow { margin-left: auto; font-size: 20px; opacity: 0.4; }
        .option:hover .arrow { opacity: 1; }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="logo">💼</div>
        <h1>Biz<span>Flow</span></h1>
        <p>Choose your access portal</p>
        
        <div class="options">
            <a href="admin_login.php" class="option" style="--c:#3b82f6;">
                <div class="option-icon" style="background:rgba(59,130,246,0.15);color:#60a5fa;">👨‍💼</div>
                <div>
                    <div class="option-title">Admin Portal</div>
                    <div class="option-sub">Manage products, sales, staff</div>
                </div>
                <div class="arrow">→</div>
            </a>
            
            <a href="pos_login.php" class="option" style="--c:#10b981;">
                <div class="option-icon" style="background:rgba(16,185,129,0.15);color:#34d399;">🛒</div>
                <div>
                    <div class="option-title">POS Terminal</div>
                    <div class="option-sub">Cashier sales with PIN</div>
                </div>
                <div class="arrow">→</div>
            </a>
            
            <a href="super_login.php" class="option" style="--c:#a855f7;">
                <div class="option-icon" style="background:rgba(168,85,247,0.15);color:#c084fc;">👑</div>
                <div>
                    <div class="option-title">HQ Admin</div>
                    <div class="option-sub">Platform owner only</div>
                </div>
                <div class="arrow">→</div>
            </a>
        </div>
    </div>
    </body>
    </html>
    <?php
}
exit;
?>
