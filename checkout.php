<?php
require_once 'config/database.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Path to PHPMailer

function sendTemplatedEmail($conn, $templateName, $toEmail, $variables = []) {
    // Get the email template
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE name = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $templateName);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result->fetch_assoc();

    if (!$template) {
        return false;
    }

    // Replace variables in subject and body
    $subject = $template['subject'];
    $body = $template['body'];
    foreach ($variables as $key => $value) {
        $body = str_replace('{{' . $key . '}}', $value, $body);
        $subject = str_replace('{{' . $key . '}}', $value, $subject);
    }

    // Set up PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT;

        $mail->setFrom($smtp_user, 'Melodiva Skin Care');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        return $mail->send();

    } catch (Exception $e) {
        error_log("Email failed to send: {$mail->ErrorInfo}");
        return false;
    }
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Helper functions
function calculateAffiliateCommission($order_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.type, p.size 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    $total_commission = 0;
    
    foreach ($order_items as $item) {
        $quantity = $item['quantity'];
        $size = strtolower($item['size']);
        $type = strtolower($item['type']);
        
        $size_value = (float) preg_replace('/[^0-9.]/', '', $size);
        
        if (strpos($type, 'black soap') !== false) {
            $grams = strpos($size, 'g') !== false ? $size_value * $quantity : $size_value * 1000 * $quantity;
            $total_commission += ($grams / 2000) * 1000;
        } elseif (strpos($type, 'kernel oil') !== false) {
            $ml = strpos($size, 'ml') !== false ? $size_value * $quantity : $size_value * 1000 * $quantity;
            $total_commission += ($ml / 1000) * 1000;
        }
    }
    
    return round($total_commission, 2);
}

// First, ensure all required database tables and columns exist
try {
    // Create orders table with all required columns
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0,
            shopping_credit DECIMAL(10,2) DEFAULT 0,
            delivery_fee DECIMAL(10,2) NOT NULL,
            interest_fee DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_verified BOOLEAN DEFAULT 0,
            affiliate_code VARCHAR(20),
            delivery_state VARCHAR(50),
            delivery_city VARCHAR(50),
            delivery_street_address TEXT,
            notes TEXT,
            payment_proof VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending_verification',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Create affiliate_commissions table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS affiliate_commissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            commission_amount DECIMAL(10,2) NOT NULL,
            type VARCHAR(50) NOT NULL,
            description TEXT,
            order_id INT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Add missing columns to existing orders table
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shopping_credit DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS affiliate_code VARCHAR(20)");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_state VARCHAR(50)");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_city VARCHAR(50)");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_street_address TEXT");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS notes TEXT");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(255)");
    
    // Create order_items table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    error_log("Database setup error: " . $e->getMessage());
}

// Get cart items
$stmt = $pdo->prepare("
    SELECT c.*, p.name, p.type, p.size, p.price, p.image, p.stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    redirect('cart.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if database has new columns
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'state'");
$hasNewColumns = $stmt->rowCount() > 0;

// Extract address components
if (!$hasNewColumns && !empty($user['address'])) {
    $address_parts = explode(',', $user['address']);
    $user_state = trim(end($address_parts));
    $user_city = (count($address_parts) > 1) ? trim($address_parts[count($address_parts) - 2]) : '';
    $user_street = (count($address_parts) > 2) ? trim(implode(',', array_slice($address_parts, 0, -2))) : trim($address_parts[0]);
} else {
    $user_state = $user['state'] ?? '';
    $user_city = $user['city'] ?? '';
    $user_street = $hasNewColumns ? ($user['street_address'] ?? '') : ($user['address'] ?? '');
}

// Load states and cities data
$states_cities_file = 'states_cities.json';
if (!file_exists($states_cities_file)) {
    $states_cities = [];
} else {
    $states_cities = json_decode(file_get_contents($states_cities_file), true) ?: [];
}

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Check for applied affiliate code
$discount = 0;
$affiliate_code = $_SESSION['affiliate_code'] ?? '';
if ($affiliate_code) {
    $discount = $subtotal * 0.05; // 5% discount
}

// Check for shopping credit
$shopping_credit = $_SESSION['shopping_credit'] ?? 0;

// Default delivery fee (will be updated via JavaScript)
$delivery_fee = 2500;
$delivery_time = 'Within 48 hours';
$interest_fee = ($subtotal - $discount - $shopping_credit) * 0.02; // 2% interest
$total = $subtotal - $discount - $shopping_credit + $delivery_fee + $interest_fee;

// Ensure total is not negative
if ($total < 0) {
    $total = 0;
}

// Define Paystack public key
// define('PAYSTACK_PUBLIC_KEY', 'pk_test_4df10a8a0db272f51a5f6a510a920f63cfffa868');

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $delivery_state = trim($_POST['delivery_state'] ?? '');
        $delivery_city = trim($_POST['delivery_city'] ?? $_POST['delivery_city_manual'] ?? '');
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $payment_method = $_POST['payment_method'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate required fields
        if (empty($delivery_state) || empty($delivery_city) || empty($delivery_address)) {
            echo json_encode(['success' => false, 'message' => 'All delivery address fields are required']);
            exit;
        }
        
        if (!in_array($payment_method, ['paystack', 'bank_transfer'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
            exit;
        }
        
        // Calculate final delivery fee based on state
        $final_delivery_fee = (strtolower($delivery_state) === 'lagos') ? 2000 : 3000;
        $final_total = $subtotal - $discount - $shopping_credit + $final_delivery_fee + $interest_fee;
        
        if ($final_total < 0) {
            $final_total = 0;
        }
        
        // Handle payment proof upload for bank transfer
        $payment_proof = '';
        if ($payment_method === 'bank_transfer') {
            if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Payment proof is required for bank transfer']);
                exit;
            }
            
            $upload_dir = 'uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload JPG, PNG, or PDF']);
                exit;
            }
            
            $payment_proof = uniqid() . '.' . $file_extension;
            if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_dir . $payment_proof)) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload payment proof']);
                exit;
            }
        }
           
        // Generate order number
        $order_number = 'MEL' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Start transaction
        $pdo->beginTransaction();
        
        // Create order with all required fields
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, order_number, subtotal, discount, shopping_credit, 
                delivery_fee, interest_fee, total, payment_method, affiliate_code, 
                delivery_state, delivery_city, delivery_street_address, notes, payment_proof
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $_SESSION['user_id'], 
            $order_number, 
            $subtotal, 
            $discount, 
            $shopping_credit, 
            $final_delivery_fee, 
            $interest_fee, 
            $final_total, 
            $payment_method, 
            $affiliate_code, 
            $delivery_state, 
            $delivery_city, 
            $delivery_address, 
            $notes, 
            $payment_proof
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create order");
        }
        
        $order_id = $pdo->lastInsertId();
        
        // Add order items
        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $item['product_id'], $item['name'], $item['quantity'], $item['price']]);
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Calculate and add affiliate commission if affiliate code was used
        if ($affiliate_code) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND affiliate_status = 'approved'");
            $stmt->execute([$affiliate_code]);
            $affiliate = $stmt->fetch();
            
            if ($affiliate) {
                $commission_amount = calculateAffiliateCommission($order_id, $pdo);
                
                if ($commission_amount > 0) {
                    // Record the commission
                    $stmt = $pdo->prepare("
                        INSERT INTO affiliate_commissions (
                            affiliate_id, order_id, commission_amount, 
                            commission_type, status, created_at
                        ) VALUES (?, ?, ?, 'sale', 'approved', NOW())
                    ");
                    $stmt->execute([
                        $affiliate['id'],
                        $order_id,
                        $commission_amount
                    ]);
                    
                    // Update wallet balance
                    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt->execute([$commission_amount, $affiliate['id']]);
                }
            }
        }
        
        // Clear cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Clear affiliate code and shopping credit from session
        unset($_SESSION['affiliate_code']);
        unset($_SESSION['shopping_credit']);
        
        $pdo->commit();
        
        if ($payment_method === 'paystack') {
            // Ensure accurate total calculation for Paystack
            $paystack_total = $subtotal - $discount - $shopping_credit + $final_delivery_fee + $interest_fee;
            
            // Ensure total is not negative
            if ($paystack_total < 0) {
                $paystack_total = 0;
            }
            
            echo json_encode([
                'success' => true,
                'payment_type' => 'paystack',
                'public_key' => PAYSTACK_PUBLIC_KEY,
                'email' => $user['email'],
                'amount' => $paystack_total, // This is the accurate total
                'reference' => $order_number,
                'order_id' => $order_id,
                'redirect' => 'order-success.php?order=' . $order_number
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'payment_type' => 'bank_transfer',
                'message' => 'Order placed successfully! Payment proof uploaded. We will verify and process your order.',
                'order_number' => $order_number,
                'redirect' => 'order-success.php?order=' . $order_number
            ]);
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Checkout database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Checkout error: " . $e->getMessage());
        // Send order confirmation email
        $orderDetails = [
            'order_number' => $order_number,
            'total' => formatPrice($final_total),
            'delivery_address' => "$delivery_address, $delivery_city, $delivery_state",
            'payment_method' => $payment_method === 'paystack' ? 'Automatic Payment' : 'Bank Transfer'
        ];

        sendTemplatedEmail(
            $conn, 
            'order_confirmation', 
            $user['email'], 
            $orderDetails
        );
        echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
    }
    exit;
}


// Get cart count
$cart_count = count($cart_items);

// Helper function to format price
function formatPrice($amount) {
    return '₦' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Melodiva Skin Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght;300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/melodiva-logo.png" alt="Melodiva Skin Care" height="50">Melodiva Skin Care
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php"><i class="fas fa-store"></i> Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="affiliate.php"><i class="fas fa-users"></i> Affiliate</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart 
                            <span class="badge bg-primary cart-count"><?php echo $cart_count; ?></span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="my-orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="py-5 mt-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="text-primary"><i class="fas fa-credit-card"></i> Checkout</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="cart.php">Cart</a></li>
                            <li class="breadcrumb-item active">Checkout</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <!-- Checkout Content -->
    <section class="py-5">
        <div class="container">
            <!-- Alert Container -->
            <div id="alertContainer"></div>
            
            <!-- Shopping Credit Alert -->
            <?php if ($shopping_credit > 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-gift"></i> You have <strong><?php echo formatPrice($shopping_credit); ?></strong> shopping credit applied to this order!
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Checkout Form -->
                <div class="col-lg-8">
                    <form id="checkoutForm" enctype="multipart/form-data">
                        <!-- Delivery Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-truck"></i> Delivery Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="delivery_state" class="form-label">State *</label>
                                            <select class="form-select" id="delivery_state" name="delivery_state" required>
                                                <option value="">Select State</option>
                                                <?php foreach ($states_cities as $state_data): ?>
                                                    <option value="<?php echo htmlspecialchars($state_data['name']); ?>" 
                                                            <?php echo (($user_state === $state_data['name']) ? 'selected' : ''); ?>>
                                                        <?php echo htmlspecialchars($state_data['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="delivery_city" class="form-label">City/LGA *</label>
                                            <select class="form-select" id="delivery_city_select" name="delivery_city" required>
                                                <option value="">Select City/LGA</option>
                                            </select>
                                            <input type="text" class="form-control" id="delivery_city_input" name="delivery_city_manual" 
                                                   value="<?php echo htmlspecialchars($user_city); ?>" style="display: none;" 
                                                   placeholder="Enter city/LGA name">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="delivery_address" class="form-label">Street Address *</label>
                                    <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" 
                                              placeholder="Enter your full street address" required><?php echo htmlspecialchars($user_street); ?></textarea>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="notes" class="form-label">Order Notes (Optional)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" 
                                              placeholder="Any special instructions for your order"></textarea>
                                </div>
                                
                                <!-- Delivery Info Display -->
                                <div class="alert alert-info" id="deliveryInfo">
                                    <i class="fas fa-info-circle"></i> 
                                    <span id="deliveryText">Select your state to see delivery information</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="payment_method" id="paystack" value="paystack" checked>
                                            <label class="form-check-label" for="paystack">
                                                <i class="fas fa-credit-card text-primary"></i> Automatic Payment (Paystack)
                                                <small class="d-block text-muted">Secure payment with Card, Transfer, USSD, Bank</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="payment_method" id="bank_transfer" value="bank_transfer">
                                            <label class="form-check-label" for="bank_transfer">
                                                <i class="fas fa-university text-success"></i> Manual Payment (Bank Transfer)
                                                <small class="d-block text-muted">Transfer to our bank account</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bank Transfer Details -->
                                <div id="bankTransferDetails" style="display: none;">
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-university"></i> Bank Transfer Details</h6>
                                        <p><strong>Bank:</strong> Wema Bank</p>
                                        <p><strong>Account Name:</strong> Melodiva Skin Care</p>
                                        <p><strong>Account Number:</strong> 0126274934</p>
                                        <p class="mb-0"><strong>Amount:</strong> <span id="transferAmount"><?php echo formatPrice($total); ?></span></p>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="payment_proof" class="form-label">Upload Payment Proof *</label>
                                        <input type="file" class="form-control" id="payment_proof" name="payment_proof" 
                                               accept=".jpg,.jpeg,.png,.pdf">
                                        <small class="text-muted">Upload screenshot or receipt of your transfer (JPG, PNG, or PDF)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-lock"></i> <span id="submitText">Place Order</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-receipt"></i> Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <!-- Cart Items -->
                            <?php foreach ($cart_items as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['size']); ?> × <?php echo $item['quantity']; ?></small>
                                        </div>
                                    </div>
                                    <span class="fw-bold"><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            
                            <hr>
                            
                            <!-- Pricing Breakdown -->
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <?php if ($discount > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Affiliate Discount (5%):</span>
                                    <span>-<?php echo formatPrice($discount); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($shopping_credit > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-info">
                                    <span>Shopping Credit:</span>
                                    <span>-<?php echo formatPrice($shopping_credit); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Delivery Fee:</span>
                                <span id="deliveryFeeDisplay"><?php echo formatPrice($delivery_fee); ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2 text-warning">
                                <span>Interest Fee (2%):</span>
                                <span id="interestFeeDisplay"><?php echo formatPrice($interest_fee); ?></span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between fw-bold h5">
                                <span>Total:</span>
                                <span id="totalDisplay"><?php echo formatPrice($total); ?></span>
                            </div>
                            
                            <!-- Affiliate Code Display -->
                            <?php if ($affiliate_code): ?>
                                <div class="alert alert-success mt-3">
                                    <small><i class="fas fa-tag"></i> Affiliate Code: <strong><?php echo htmlspecialchars($affiliate_code); ?></strong></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; 2024 Melodiva Skin Care. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        const statesCities = <?php echo json_encode($states_cities); ?>;
        let currentSubtotal = <?php echo $subtotal; ?>;
        let currentDiscount = <?php echo $discount; ?>;
        let currentShoppingCredit = <?php echo $shopping_credit; ?>;
        let currentInterestRate = 0.02;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            const stateSelect = document.getElementById('delivery_state');
            const citySelect = document.getElementById('delivery_city_select');
            const cityInput = document.getElementById('delivery_city_input');
            
            // Load cities for pre-selected state
            if (stateSelect.value) {
                loadCities(stateSelect.value);
                updateDeliveryInfo(stateSelect.value);
            }
            
            // State change handler
            stateSelect.addEventListener('change', function() {
                const selectedState = this.value;
                loadCities(selectedState);
                updateDeliveryInfo(selectedState);
                updatePricing();
            });
            
            // Payment method change handler
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const bankTransferDetails = document.getElementById('bankTransferDetails');
                    const paymentProof = document.getElementById('payment_proof');
                    
                    if (this.value === 'bank_transfer') {
                        bankTransferDetails.style.display = 'block';
                        paymentProof.required = true;
                    } else {
                        bankTransferDetails.style.display = 'none';
                        paymentProof.required = false;
                    }
                });
            });
        });
        
        function loadCities(stateName) {
            const citySelect = document.getElementById('delivery_city_select');
            const cityInput = document.getElementById('delivery_city_input');
            
            citySelect.innerHTML = '<option value="">Select City/LGA</option>';
            
            if (!stateName) {
                citySelect.style.display = 'block';
                cityInput.style.display = 'none';
                return;
            }
            
            const stateData = statesCities.find(state => state.name === stateName);
            
            if (stateData && stateData.cities && stateData.cities.length > 0) {
                stateData.cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
                citySelect.style.display = 'block';
                cityInput.style.display = 'none';
                cityInput.required = false;
                citySelect.required = true;
            } else {
                citySelect.style.display = 'none';
                cityInput.style.display = 'block';
                cityInput.required = true;
                citySelect.required = false;
            }
        }
        
        function updateDeliveryInfo(stateName) {
            const deliveryInfo = document.getElementById('deliveryInfo');
            const deliveryText = document.getElementById('deliveryText');
            
            if (!stateName) {
                deliveryText.textContent = 'Select your state to see delivery information';
                return;
            }
            
            let fee, time;
            if (stateName.toLowerCase() === 'lagos') {
                fee = '₦2,000';
                time = 'Within 24 hours';
            } else {
                fee = '₦3,000';
                time = 'Within 48 hours';
            }
            
            deliveryText.innerHTML = `Delivery to ${stateName}: <strong>${fee}</strong> - <strong>${time}</strong>`;
        }
        
        function updatePricing() {
            const stateSelect = document.getElementById('delivery_state');
            const selectedState = stateSelect.value;
            
            let deliveryFee = 2500; // Default
            if (selectedState) {
                deliveryFee = (selectedState.toLowerCase() === 'lagos') ? 2000 : 3000;
            }
            
            const interestFee = (currentSubtotal - currentDiscount - currentShoppingCredit) * currentInterestRate;
            const total = Math.max(0, currentSubtotal - currentDiscount - currentShoppingCredit + deliveryFee + interestFee);
            
            // Update display
            document.getElementById('deliveryFeeDisplay').textContent = formatPrice(deliveryFee);
            document.getElementById('interestFeeDisplay').textContent = formatPrice(interestFee);
            document.getElementById('totalDisplay').textContent = formatPrice(total);
            document.getElementById('transferAmount').textContent = formatPrice(total);
        }
        
        function formatPrice(amount) {
            return '₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        function showAlert(message, type = 'danger') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alert);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
        
        // Form submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const originalText = submitText.textContent;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const formData = new FormData(this);
            
            fetch('checkout.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (data.payment_type === 'paystack') {
                        // Initialize Paystack payment
                        const handler = PaystackPop.setup({
                            key: data.public_key,
                            email: data.email,
                            amount: Math.round(data.amount * 100), // Convert to kobo and round to avoid decimal issues
                            ref: data.reference,
                            callback: function(response) {
                                showAlert('Payment successful! Redirecting...', 'success');
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 2000);
                            },
                            onClose: function() {
                                submitBtn.disabled = false;
                                submitText.textContent = originalText;
                            }
                        });
                        handler.openIframe();
                    } else {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
                } else {
                    showAlert(data.message, 'danger');
                    submitBtn.disabled = false;
                    submitText.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again: ' + error.message, 'danger');
                submitBtn.disabled = false;
                submitText.textContent = originalText;
            });
        });
    </script>
</body>
</html>