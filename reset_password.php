<?php
// ⚠️ DELETE THIS FILE AFTER USING IT!
require_once 'db.php';

$newPassword = 'admin123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Reset super admin
$result = $conn->query("SELECT id, username FROM super_admins LIMIT 1");

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    
    $stmt = $conn->prepare("UPDATE super_admins SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hash, $admin['id']);
    
    if ($stmt->execute()) {
        echo "<h1>✅ Password Reset!</h1>";
        echo "<p><b>Username:</b> " . htmlspecialchars($admin['username']) . "</p>";
        echo "<p><b>New Password:</b> $newPassword</p>";
        echo "<p style='color:red;'>⚠️ DELETE THIS FILE NOW!</p>";
        echo "<a href='super_login.php'>→ Go to Login</a>";
    } else {
        echo "❌ Error: " . $conn->error;
    }
} else {
    // No super admin exists, create one
    $stmt = $conn->prepare("
        INSERT INTO super_admins (username, email, password, full_name, is_active, created_at)
        VALUES ('amine', 'amine@bizflow.com', ?, 'Amine Aydi', 1, NOW())
    ");
    $stmt->bind_param("s", $hash);
    
    if ($stmt->execute()) {
        echo "<h1>✅ Super Admin Created!</h1>";
        echo "<p><b>Username:</b> amine</p>";
        echo "<p><b>Password:</b> $newPassword</p>";
        echo "<p style='color:red;'>⚠️ DELETE THIS FILE NOW!</p>";
        echo "<a href='super_login.php'>→ Go to Login</a>";
    } else {
        echo "❌ Error: " . $conn->error;
    }
}
?>
