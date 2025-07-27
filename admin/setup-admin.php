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

    // Create all necessary tables
    createTables($pdo);
    
    // Insert default products if none exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        $products = [
            ['African Black Soap (250g)', 'Natural African black soap made with traditional ingredients.', 'black_soap', '250g', 2500, 50],
            ['African Black Soap (500g)', 'Natural African black soap made with traditional ingredients.', 'black_soap', '500g', 4500, 40],
            ['African Black Soap (1kg)', 'Natural African black soap made with traditional ingredients.', 'black_soap', '1kg', 8000, 30],
            ['African Black Soap (2kg)', 'Natural African black soap made with traditional ingredients.', 'black_soap', '2kg', 15000, 20],
            ['Palm Kernel Oil (250ml)', 'Pure palm kernel oil for skin and hair care.', 'kernel_oil', '250ml', 2000, 50],
            ['Palm Kernel Oil (500ml)', 'Pure palm kernel oil for skin and hair care.', 'kernel_oil', '500ml', 3500, 40],
            ['Palm Kernel Oil (1L)', 'Pure palm kernel oil for skin and hair care.', 'kernel_oil', '1L', 6500, 30]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO products (name, description, type, size, price, stock, image) VALUES (?, ?, ?, ?, ?, ?, 'images/placeholder.jpg')");
        foreach ($products as $product) {
            $stmt->execute($product);
        }
    }
    echo "Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "Database tables created successfully!<br>";
    echo "<a href='login.php'>Go to Admin Login</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
