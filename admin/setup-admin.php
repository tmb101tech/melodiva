<?php
require_once '../config/database.php';

// Create admin user with proper password hashing
$username = 'admin';
$password = 'admin123';
$email = 'admin@melodivaSkin Care.com';

// Hash the password properly
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin table exists, create if not
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Delete existing admin user if exists
    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);

    // Insert new admin user
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
    $stmt->execute([$username, $hashed_password, $email]);

    echo "Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "<a href='login.php'>Go to Admin Login</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
