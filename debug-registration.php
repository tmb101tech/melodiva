<?php
// Create this file to test database connection and debug registration issues
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection successful<br>";
    
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if new columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'state'");
    if ($stmt->rowCount() > 0) {
        echo "<br>✅ New state/city columns exist";
    } else {
        echo "<br>⚠️ Old database structure detected - using combined address field";
    }
    
    // Test insert (you can remove this after testing)
    echo "<h3>Test Registration:</h3>";
    $test_email = "test_" . time() . "@example.com";
    
    // Check if database has new columns
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'state'");
    $hasNewColumns = $stmt->rowCount() > 0;
    
    if ($hasNewColumns) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, state, city, street_address, password, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $result = $stmt->execute(['Test User', $test_email, '08012345678', 'Lagos', 'Ikeja', 'Test Address', password_hash('test123', PASSWORD_DEFAULT)]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, address, password, is_verified) VALUES (?, ?, ?, ?, ?, 1)");
        $result = $stmt->execute(['Test User', $test_email, '08012345678', 'Test Address, Ikeja, Lagos', password_hash('test123', PASSWORD_DEFAULT)]);
    }
    
    if ($result) {
        echo "✅ Test registration successful<br>";
        echo "Test email: " . $test_email . "<br>";
        
        // Clean up test data
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$test_email]);
        echo "✅ Test data cleaned up<br>";
    } else {
        echo "❌ Test registration failed<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Check PHP error log
echo "<h3>Recent PHP Errors:</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $errors = file_get_contents($error_log);
    $recent_errors = array_slice(explode("\n", $errors), -10);
    foreach ($recent_errors as $error) {
        if (!empty($error)) {
            echo "<div style='background: #ffe6e6; padding: 5px; margin: 2px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($error) . "</div>";
        }
    }
} else {
    echo "Error log not found or not accessible";
}
?>
