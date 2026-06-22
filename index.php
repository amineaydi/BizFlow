<?php
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db = getenv('DB_NAME');
$port = getenv('DB_PORT');

echo "<!DOCTYPE html>
<html>
<head>
    <title>BizFlow - Setup Test</title>
    <style>
        body {
            background: linear-gradient(135deg, #0a0e1a, #1a1f33);
            color: white;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .box {
            background: rgba(255,255,255,0.05);
            padding: 50px;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            max-width: 500px;
            backdrop-filter: blur(20px);
        }
        h1 {
            font-size: 64px;
            margin: 0 0 10px;
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle {
            color: #94a3b8;
            font-size: 16px;
            margin-bottom: 30px;
        }
        .check {
            padding: 12px 16px;
            margin: 8px 0;
            border-radius: 12px;
            font-size: 14px;
            text-align: left;
            font-family: monospace;
        }
        .ok { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .fail { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .warn { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
        .footer {
            margin-top: 30px;
            color: #475569;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class='box'>
        <h1>💼</h1>
        <h2 style='margin:0;color:#3b82f6;'>BizFlow</h2>
        <div class='subtitle'>Small Business Manager</div>";

// Test 1: PHP working
echo "<div class='check ok'>✅ PHP " . phpversion() . " is running</div>";

// Test 2: Env variables
if ($host && $user && $db) {
    echo "<div class='check ok'>✅ Environment variables loaded</div>";
} else {
    echo "<div class='check fail'>❌ Environment variables MISSING</div>";
}

// Test 3: Database connection
if ($host) {
    try {
        $conn = @new mysqli($host, $user, $pass, $db, $port);
        
        if ($conn->connect_error) {
            echo "<div class='check fail'>❌ DB Error: " . htmlspecialchars($conn->connect_error) . "</div>";
        } else {
            echo "<div class='check ok'>✅ Database connected!</div>";
            
            // Test 4: Count tables
            $result = $conn->query("SHOW TABLES");
            $count = $result->num_rows;
            
            if ($count > 0) {
                echo "<div class='check ok'>✅ Found $count tables in database</div>";
            } else {
                echo "<div class='check warn'>⚠️ Database empty - import SQL!</div>";
            }
            
            $conn->close();
        }
    } catch (Exception $e) {
        echo "<div class='check fail'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='check warn'>⚠️ No DB credentials</div>";
}

echo "
        <div class='footer'>
            🚀 Deployment successful on Render<br>
            ⏰ " . date('Y-m-d H:i:s') . "
        </div>
    </div>
</body>
</html>";
?>
