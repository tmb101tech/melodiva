<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Get user orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, 'x)') SEPARATOR ', ') as items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Get cart count
$cart_count = 0;
$stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch();
$cart_count = $result['count'] ?? 0;

// Get user data for affiliate status
$stmt = $pdo->prepare("SELECT is_affiliate FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Melodiva Skin Care</title>
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
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item active" href="my-orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                            <?php if ($user['is_affiliate']): ?>
                                <li><a class="dropdown-item" href="affiliate-dashboard.php"><i class="fas fa-chart-line"></i> Affiliate Dashboard</a></li>
                            <?php endif; ?>
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
                    <h1 class="text-primary"><i class="fas fa-box"></i> My Orders</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active">My Orders</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <!-- Orders Content -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h3 class="text-muted">No orders yet</h3>
                    <p>You haven't placed any orders. Start shopping to see your orders here!</p>
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-store"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($orders as $order): ?>
                        <div class="col-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <h6 class="mb-0">Order #<?php echo htmlspecialchars($order['order_number'] ?? ''); ?></h6>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total: <?php echo formatPrice($order['total']); ?></strong>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#order-<?php echo $order['id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="collapse" id="order-<?php echo $order['id']; ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-list"></i> Order Items</h6>
                                                <p><?php echo htmlspecialchars($order['items'] ?? ''); ?></p>
                                                
                                                <h6><i class="fas fa-credit-card"></i> Payment Method</h6>
                                                <p class="text-capitalize"><?php echo str_replace('_', ' ', $order['payment_method']); ?></p>
                                                
                                                <?php if ($order['affiliate_code']): ?>
                                                    <h6><i class="fas fa-tag"></i> Affiliate Code Used</h6>
                                                    <p><span class="badge bg-success"><?php echo htmlspecialchars($order['affiliate_code'] ?? ''); ?></span></p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-map-marker-alt"></i> Delivery Address</h6>
                                                <p><?php echo nl2br(htmlspecialchars($order['delivery_address'] ?? '')); ?></p>
                                                
                                                <h6><i class="fas fa-calculator"></i> Order Summary</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <tr>
                                                            <td>Subtotal:</td>
                                                            <td class="text-end"><?php echo formatPrice($order['subtotal']); ?></td>
                                                        </tr>
                                                        <?php if ($order['discount'] > 0): ?>
                                                            <tr class="text-success">
                                                                <td>Discount:</td>
                                                                <td class="text-end">-<?php echo formatPrice($order['discount']); ?></td>
                                                            </tr>
                                                        <?php endif; ?>
                                                        <tr>
                                                            <td>Delivery Fee:</td>
                                                            <td class="text-end"><?php echo formatPrice($order['delivery_fee']); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Interest (2%):</td>
                                                            <td class="text-end"><?php echo formatPrice($order['interest_fee']); ?></td>
                                                        </tr>
                                                        <tr class="fw-bold">
                                                            <td>Total:</td>
                                                            <td class="text-end"><?php echo formatPrice($order['total']); ?></td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($order['notes']): ?>
                                            <div class="mt-3">
                                                <h6><i class="fas fa-sticky-note"></i> Order Notes</h6>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['notes'] ?? '')); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Order Status Timeline -->
                                        <div class="mt-4">
                                            <h6><i class="fas fa-clock"></i> Order Status</h6>
                                            <div class="progress mb-2" style="height: 8px;">
                                                <?php
                                                $status_progress = [
                                                    'pending_verification' => 25,
                                                    'confirmed' => 50,
                                                    'shipped' => 75,
                                                    'delivered' => 100,
                                                    'cancelled' => 0
                                                ];
                                                $progress = $status_progress[$order['status']] ?? 0;
                                                ?>
                                                <div class="progress-bar bg-<?php echo $order['status'] === 'cancelled' ? 'danger' : 'success'; ?>" 
                                                     style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                            <div class="d-flex justify-content-between small text-muted">
                                                <span>Pending</span>
                                                <span>Confirmed</span>
                                                <span>Shipped</span>
                                                <span>Delivered</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="mt-3">
                                            <?php if ($order['status'] === 'pending_verification' && $order['payment_method'] === 'bank_transfer'): ?>
                                                <button class="btn btn-warning btn-sm" onclick="uploadPaymentProof(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-upload"></i> Upload Payment Proof
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['status'] === 'delivered'): ?>
                                                <button class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Order Completed
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-outline-primary btn-sm" onclick="contactSupport('<?php echo $order['order_number']; ?>')">
                                                <i class="fas fa-headset"></i> Contact Support
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Order Statistics -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-chart-pie"></i> Order Statistics</h5>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h4 class="text-primary"><?php echo count($orders); ?></h4>
                                        <small class="text-muted">Total Orders</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4 class="text-success">
                                            <?php echo count(array_filter($orders, fn($o) => $o['status'] === 'delivered')); ?>
                                        </h4>
                                        <small class="text-muted">Completed</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4 class="text-warning">
                                            <?php echo count(array_filter($orders, fn($o) => in_array($o['status'], ['pending_verification', 'confirmed', 'shipped']))); ?>
                                        </h4>
                                        <small class="text-muted">In Progress</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4 class="text-info">
                                            <?php echo formatPrice(array_sum(array_column($orders, 'total'))); ?>
                                        </h4>
                                        <small class="text-muted">Total Spent</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
        function uploadPaymentProof(orderId) {
            // This would open a modal or redirect to upload payment proof
            alert('Payment proof upload feature will be implemented in the checkout system.');
        }
        
        function contactSupport(orderNumber) {
            const message = `Hello, I need help with my order #${orderNumber}`;
            const whatsappUrl = `https://wa.me/2341234567890?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }
    </script>
</body>
</html>
