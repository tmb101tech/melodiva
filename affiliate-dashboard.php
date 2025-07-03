<?php
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Initialize variables
$referral_code = '';
$wallet_balance = 0;
$click_count = 0;
$sales_count = 0;
$total_sales = 0;
$recent_clicks = [];
$recent_orders = [];
$error_message = '';

try {
    // First, ensure all required columns exist with proper error handling
    $required_columns = [
        'referral_code' => 'VARCHAR(20)',
        'affiliate_status' => 'VARCHAR(20) DEFAULT "pending"',
        'wallet_balance' => 'DECIMAL(10,2) DEFAULT 0.00'
    ];

    foreach ($required_columns as $column => $definition) {
        try {
            // Check if column exists
            $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
            $stmt->execute([$column]);
            
            if ($stmt->rowCount() == 0) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
                error_log("Added column $column to users table");
            }
        } catch (PDOException $e) {
            error_log("Error adding column $column: " . $e->getMessage());
        }
    }

    // Get user data with error handling
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('auth/login.php');
    }

    // Generate referral code if missing (same format as affiliate.php)
    if (empty($user['referral_code'])) {
        $referral_code = 'REF' . str_pad($user['id'], 6, '0', STR_PAD_LEFT);
        try {
            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $stmt->execute([$referral_code, $user['id']]);
            $user['referral_code'] = $referral_code;
        } catch (PDOException $e) {
            error_log("Error updating referral code: " . $e->getMessage());
            $error_message = "Error generating referral code. Please refresh the page.";
        }
    } else {
        $referral_code = $user['referral_code'];
    }

    // Check if user is an approved affiliate
    $affiliate_status = $user['affiliate_status'] ?? 'pending';
    if ($affiliate_status !== 'approved') {
        redirect('affiliate.php');
    }

    // Get wallet balance
    $wallet_balance = $user['wallet_balance'] ?? 0;

    // Create affiliate_clicks table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS affiliate_clicks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                referral_code VARCHAR(20) NOT NULL,
                user_id INT,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_referral_code (referral_code)
            )
        ");
    } catch (PDOException $e) {
        error_log("Error creating affiliate_clicks table: " . $e->getMessage());
    }

    // Get click count with error handling
    if (!empty($referral_code)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as click_count FROM affiliate_clicks WHERE referral_code = ?");
            $stmt->execute([$referral_code]);
            $result = $stmt->fetch();
            $click_count = $result['click_count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error getting click count: " . $e->getMessage());
            $click_count = 0;
        }
    }

    // Create orders table if it doesn't exist
    try {
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
    } catch (PDOException $e) {
        error_log("Error creating orders table: " . $e->getMessage());
    }

    // Get sales count and commission with error handling
    if (!empty($referral_code)) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as sales_count, COALESCE(SUM(total), 0) as total_sales 
                FROM orders 
                WHERE affiliate_code = ? AND status != 'cancelled'
            ");
            $stmt->execute([$referral_code]);
            $sales_data = $stmt->fetch();
            $sales_count = $sales_data['sales_count'] ?? 0;
            $total_sales = $sales_data['total_sales'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error getting sales data: " . $e->getMessage());
            $sales_count = 0;
            $total_sales = 0;
        }
    }

    // Get recent clicks with error handling
    if (!empty($referral_code)) {
        try {
            $stmt = $pdo->prepare("
                SELECT ac.*, u.name as customer_name 
                FROM affiliate_clicks ac 
                LEFT JOIN users u ON ac.user_id = u.id 
                WHERE ac.referral_code = ? 
                ORDER BY ac.created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$referral_code]);
            $recent_clicks = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting recent clicks: " . $e->getMessage());
            $recent_clicks = [];
        }
    }

    // Get recent orders with error handling
    if (!empty($referral_code)) {
        try {
            $stmt = $pdo->prepare("
                SELECT o.*, u.name as customer_name 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.affiliate_code = ? 
                ORDER BY o.created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$referral_code]);
            $recent_orders = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting recent orders: " . $e->getMessage());
            $recent_orders = [];
        }
    }

} catch (PDOException $e) {
    error_log("Database error in affiliate dashboard: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later.";
} catch (Exception $e) {
    error_log("General error in affiliate dashboard: " . $e->getMessage());
    $error_message = "An unexpected error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Dashboard - Melodiva Skin Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
                        <a class="nav-link active" href="affiliate.php"><i class="fas fa-users"></i> Affiliate</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart 
                            <span class="badge bg-primary cart-count">0</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="my-orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                            <li><a class="dropdown-item" href="affiliate-dashboard.php"><i class="fas fa-chart-line"></i> Affiliate Dashboard</a></li>
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
                    <h1 class="text-primary"><i class="fas fa-chart-line"></i> Affiliate Dashboard</h1>
                    <p class="text-muted">Track your performance and earnings</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard Content -->
    <section class="py-5">
        <div class="container">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <br><small>Please contact support if this error persists.</small>
                </div>
            <?php endif; ?>

            <!-- Commission Redemption Alert -->
            <?php if ($wallet_balance > 0): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-money-bill-wave"></i>
                    <strong>You have ₦<?php echo number_format($wallet_balance, 2); ?> available for redemption!</strong>
                    <br>Visit your <a href="profile.php" class="alert-link">Profile Page</a> to redeem your commission as cash or shopping credit.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0">₦<?php echo number_format($wallet_balance, 2); ?></h4>
                                    <p class="mb-0">Wallet Balance</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-wallet fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($click_count); ?></h4>
                                    <p class="mb-0">Total Clicks</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-mouse-pointer fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($sales_count); ?></h4>
                                    <p class="mb-0">Total Sales</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-cart fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0">₦<?php echo number_format($total_sales, 2); ?></h4>
                                    <p class="mb-0">Sales Volume</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-bar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Referral Code -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-code"></i> Your Referral Code</h5>
                        </div>
                        <div class="card-body">
                            <div class="input-group">
                                <input type="text" class="form-control" id="referralCode" 
                                       value="<?php echo htmlspecialchars($referral_code); ?>" readonly>
                                <button class="btn btn-primary" type="button" onclick="copyReferralCode()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block">Share this code with customers to earn commission</small>
                            
                            <div class="mt-3">
                                <h6>Commission Rates:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> ₦100 per click</li>
                                    <li><i class="fas fa-check text-success"></i> ₦1000 per 2kg Black Soap</li>
                                    <li><i class="fas fa-check text-success"></i> ₦1000 per 1L Kernel Oil</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="profile.php" class="btn btn-success">
                                    <i class="fas fa-money-bill-wave"></i> Redeem Commission
                                </a>
                                <a href="shop.php" class="btn btn-primary">
                                    <i class="fas fa-store"></i> Shop Products
                                </a>
                                <button class="btn btn-info" onclick="shareReferralLink()">
                                    <i class="fas fa-share"></i> Share Referral Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-mouse-pointer"></i> Recent Clicks</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_clicks)): ?>
                                <p class="text-muted">No clicks yet. Start sharing your referral code!</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Date</th>
                                                <th>Commission</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_clicks as $click): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($click['customer_name'] ?? 'Anonymous'); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($click['created_at'])); ?></td>
                                                    <td>₦100</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Recent Sales</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_orders)): ?>
                                <p class="text-muted">No sales yet. Keep promoting!</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                                    <td>₦<?php echo number_format($order['total'], 2); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
        function copyReferralCode() {
            const codeInput = document.getElementById('referralCode');
            codeInput.select();
            document.execCommand('copy');
            
            // Show success message
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 2000);
        }
        
        function shareReferralLink() {
            const referralLink = `${window.location.origin}/shop.php?ref=<?php echo $referral_code; ?>`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Melodiva Skin Care',
                    text: 'Check out these amazing skincare products!',
                    url: referralLink
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(referralLink).then(() => {
                    alert('Referral link copied to clipboard!');
                });
            }
        }
    </script>
</body>
</html>
