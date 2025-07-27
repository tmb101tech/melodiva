<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'melodiva_skincare');

// Site configuration
define('SITE_URL', 'http://localhost/melodiva-skincare');
define('SITE_NAME', 'Melodiva Skincare');
define('UPLOAD_PATH', 'uploads/');

// Email configuration (Update with your SMTP settings)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'melodivaproducts@gmail.com');
define('SMTP_PASS', 'qhnk tjff rwhl ggdd');

// Paystack configuration (Update with your keys)
define('PAYSTACK_PUBLIC_KEY', 'pk_test_4ed198d0f27bc0bb7c56d6cb484a96139091bc7f'); 
define('PAYSTACK_SECRET_KEY', 'sk_test_75693451ebf71fea3bf782e9fcf467ae11f77a80');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'melodiva_skincare';
$username = 'root';
$password = '';

// Create PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If database doesn't exist, create it
    if ($e->getCode() == 1049) {
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE $dbname");
            
            // Create tables
            createTables($pdo);
            
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Create tables if they don't exist
function createTables($pdo) {
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            city VARCHAR(50),
            state VARCHAR(50),
            street_address TEXT,
            is_verified BOOLEAN DEFAULT 0,
            verification_token VARCHAR(100),
            affiliate_status ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none',
            referral_code VARCHAR(20) UNIQUE,
            wallet_balance DECIMAL(10,2) DEFAULT 0,
            bank_name VARCHAR(100),
            account_number VARCHAR(20),
            account_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Products table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            type VARCHAR(50),
            size VARCHAR(20),
            price DECIMAL(10,2) NOT NULL,
            sale_price DECIMAL(10,2),
            category VARCHAR(50),
            image VARCHAR(255),
            stock INT DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Cart table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )
    ");
    
    // Orders table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            order_number VARCHAR(20) UNIQUE NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0,
            shopping_credit DECIMAL(10,2) DEFAULT 0,
            delivery_fee DECIMAL(10,2) NOT NULL,
            interest_fee DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            status ENUM('pending_verification', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending_verification',
            payment_method VARCHAR(50),
            payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
            delivery_state VARCHAR(50),
            delivery_city VARCHAR(50),
            delivery_street_address TEXT,
            notes TEXT,
            payment_proof VARCHAR(255),
            affiliate_code VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Order items table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT,
            product_name VARCHAR(100) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        )
    ");
    
    // Admin users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('admin', 'manager') DEFAULT 'admin',
            last_login TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Affiliate clicks table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS affiliate_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referral_code VARCHAR(20) NOT NULL,
            user_id INT,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Affiliate commissions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS affiliate_commissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            affiliate_id INT NOT NULL,
            order_id INT,
            commission_amount DECIMAL(10,2) NOT NULL,
            commission_type VARCHAR(50) NOT NULL,
            description TEXT,
            status ENUM('pending', 'approved', 'paid') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (affiliate_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        )
    ");
    
    // Settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
}

// Helper functions
function redirect($location) {
    header("Location: $location");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}

function generateReferralCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Check if the function exists before declaring it
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return '₦' . number_format($price, 2);
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generateOrderNumber() {
    return 'ORD' . date('Ymd') . rand(1000, 9999);
}

// Insert default admin user if none exists
$stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
if ($stmt->fetchColumn() == 0) {
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $password, 'admin@melodiva.com', 'admin']);
}

// Insert default products if none exist
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
if ($stmt->fetchColumn() == 0) {
    $products = [
        [
            'name' => 'African Black Soap (250g)',
            'description' => 'Natural African black soap made with traditional ingredients.',
            'price' => 2500,
            'category' => 'Black Soap',
            'stock' => 50
        ],
        [
            'name' => 'African Black Soap (500g)',
            'description' => 'Natural African black soap made with traditional ingredients.',
            'price' => 4500,
            'category' => 'Black Soap',
            'stock' => 40
        ],
        [
            'name' => 'African Black Soap (1kg)',
            'description' => 'Natural African black soap made with traditional ingredients.',
            'price' => 8000,
            'category' => 'Black Soap',
            'stock' => 30
        ],
        [
            'name' => 'African Black Soap (2kg)',
            'description' => 'Natural African black soap made with traditional ingredients.',
            'price' => 15000,
            'category' => 'Black Soap',
            'stock' => 20
        ],
        [
            'name' => 'Palm Kernel Oil (250ml)',
            'description' => 'Pure palm kernel oil for skin and hair care.',
            'price' => 2000,
            'category' => 'Kernel Oil',
            'stock' => 50
        ],
        [
            'name' => 'Palm Kernel Oil (500ml)',
            'description' => 'Pure palm kernel oil for skin and hair care.',
            'price' => 3500,
            'category' => 'Kernel Oil',
            'stock' => 40
        ],
        [
            'name' => 'Palm Kernel Oil (1L)',
            'description' => 'Pure palm kernel oil for skin and hair care.',
            'price' => 6500,
            'category' => 'Kernel Oil',
            'stock' => 30
        ]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, stock) VALUES (?, ?, ?, ?, ?)");
    foreach ($products as $product) {
        $stmt->execute([
            $product['name'],
            $product['description'],
            $product['price'],
            $product['category'],
            $product['stock']
        ]);
    }
}

function calculateDeliveryFee($state) {
    global $pdo;
    
    // Get delivery fees from settings
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    
    if (strtolower($state) === 'lagos') {
        $stmt->execute(['delivery_fee_lagos']);
        $fee = $stmt->fetchColumn();
        return $fee ? floatval($fee) : 2000;
    } else {
        $stmt->execute(['delivery_fee_other']);
        $fee = $stmt->fetchColumn();
        return $fee ? floatval($fee) : 3000;
    }
}

function getSetting($key, $default = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function logActivity($user_id, $action, $details = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        // Log error but don't break the application
        error_log("Activity log error: " . $e->getMessage());
    }
}

function sendEmail($to, $subject, $message, $from_name = 'Melodiva Skin Care') {
    $smtp_host = getSetting('smtp_host');
    $smtp_port = getSetting('smtp_port', 587);
    $smtp_username = getSetting('smtp_username');
    $smtp_password = getSetting('smtp_password');
    $from_email = getSetting('site_email', 'melodivaproducts@gmail.com');
    
    // If SMTP is not configured, use PHP mail() function
    if (empty($smtp_host) || empty($smtp_username)) {
        $headers = "From: $from_name <$from_email>\r\n";
        $headers .= "Reply-To: $from_email\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    // TODO: Implement SMTP sending with PHPMailer or similar
    // For now, fallback to PHP mail()
    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function trackAffiliateClick($referral_code) {
    global $pdo;
    
    if (empty($referral_code)) return false;
    
    try {
        // Check if referral code exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND affiliate_status = 'approved'");
        $stmt->execute([$referral_code]);
        $affiliate = $stmt->fetch();
        
        if (!$affiliate) return false;
        
        // Record the click
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_clicks (referral_code, ip_address) 
            VALUES (?, ?)
        ");
        $stmt->execute([$referral_code, $_SERVER['REMOTE_ADDR'] ?? '']);
        
        // Add click commission
        $click_commission = getSetting('click_commission', 100);
        if ($click_commission > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO affiliate_commissions (user_id, commission_amount, type, description) 
                VALUES (?, ?, 'click', 'Click commission')
            ");
            $stmt->execute([$affiliate['id'], $click_commission]);
            
            // Update wallet balance
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$click_commission, $affiliate['id']]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Affiliate click tracking error: " . $e->getMessage());
        return false;
    }
}

function calculateReferralDiscount($total) {
    $discount_percentage = getSetting('referral_discount', 5);
    return ($total * $discount_percentage) / 100;
}

// Create necessary tables if they don't exist
try {
    // Activity logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (created_at)
        )
    ");
    
    // Affiliate commissions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS affiliate_commissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            commission_amount DECIMAL(10,2) NOT NULL,
            type ENUM('click', 'sale', 'manual') NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id)
        )
    ");
    
    // Affiliate clicks table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS affiliate_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referral_code VARCHAR(20) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (referral_code)
        )
    ");
    
} catch (PDOException $e) {
    // Tables might already exist, continue
}
?>
