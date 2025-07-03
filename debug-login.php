<?php
// Create this file to debug login issues
require_once 'config/database.php';

echo "<h2>Login Debug Tool</h2>";

// Test database connection
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection successful<br>";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Check users table
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✅ Users table accessible. Total users: " . $result['count'] . "<br>";
} catch (PDOException $e) {
    echo "❌ Users table error: " . $e->getMessage() . "<br>";
}

// Show recent users (without passwords)
try {
    $stmt = $pdo->query("SELECT id, name, email, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $users = $stmt->fetchAll();
    
    echo "<h3>Recent Users:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Verified</th><th>Created</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . ($user['is_verified'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "❌ Error fetching users: " . $e->getMessage() . "<br>";
}

// Test login form
echo "<h3>Test Login:</h3>";
echo "<form method='POST' action='auth/login.php'>";
echo "<input type='email' name='email' placeholder='Email' required><br><br>";
echo "<input type='password' name='password' placeholder='Password' required><br><br>";
echo "<button type='submit'>Test Login</button>";
echo "</form>";

// Check PHP error log
echo "<h3>Recent PHP Errors:</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $errors = file_get_contents($error_log);
    $recent_errors = array_slice(explode("\n", $errors), -10);
    foreach ($recent_errors as $error) {
        if (!empty($error) && strpos($error, 'login') !== false) {
            echo "<div style='background: #ffe6e6; padding: 5px; margin: 2px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($error) . "</div>";
        }
    }
} else {
    echo "Error log not found or not accessible";
}

// Session test
echo "<h3>Session Test:</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session ID: " . session_id() . "<br>";
echo "Session data: " . print_r($_SESSION, true) . "<br>";
?>
