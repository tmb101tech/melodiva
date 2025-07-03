<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$order_number = $_GET['order'] ?? '';
if (empty($order_number)) {
    redirect('index.php');
}

// Get order details with better error handling
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.order_number = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_number, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        // Order not found, redirect to orders page
        redirect('my-orders.php');
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.type, p.size, p.image 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $order_items = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    redirect('my-orders.php');
}

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
    <title>Order Placed Successfully - Melodiva Skin Care</title>
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

    <!-- Success Content -->
    <section class="py-5 mt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Success Message -->
                    <div class="text-center mb-5">
                        <div class="success-icon mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                        </div>
                        <h1 class="text-success mb-3">Order Placed Successfully!</h1>
                        <p class="lead text-muted">Thank you for your order. We'll process it shortly and keep you updated.</p>
                    </div>

                    <!-- Order Details Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-receipt"></i> Order Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Order Number</h6>
                                    <p class="fw-bold">#<?php echo htmlspecialchars($order['order_number']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Order Date</h6>
                                    <p><?php echo date('F d, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Payment Method</h6>
                                    <p><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Status</h6>
                                    <span class="badge bg-warning">Pending Verification</span>
                                </div>
                            </div>

                            <!-- Delivery Address -->
                            <?php if (!empty($order['delivery_street_address'])): ?>
                            <div class="mb-4">
                                <h6 class="text-muted">Delivery Address</h6>
                                <p class="mb-0">
                                    <?php echo htmlspecialchars($order['delivery_street_address']); ?><br>
                                    <?php echo htmlspecialchars($order['delivery_city'] . ', ' . $order['delivery_state']); ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <!-- Order Items -->
                            <h6 class="text-muted mb-3">Items Ordered</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                             class="img-thumbnail me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars($item['type'] . ' - ' . $item['size']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td><?php echo formatPrice($item['price']); ?></td>
                                                <td><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Order Summary -->
                            <div class="row">
                                <div class="col-md-6 offset-md-6">
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
                                        <?php if (isset($order['shopping_credit']) && $order['shopping_credit'] > 0): ?>
                                            <tr class="text-info">
                                                <td>Shopping Credit:</td>
                                                <td class="text-end">-<?php echo formatPrice($order['shopping_credit']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td>Delivery Fee:</td>
                                            <td class="text-end"><?php echo formatPrice($order['delivery_fee']); ?></td>
                                        </tr>
                                        <?php if (isset($order['interest_fee']) && $order['interest_fee'] > 0): ?>
                                        <tr>
                                            <td>Interest (2%):</td>
                                            <td class="text-end"><?php echo formatPrice($order['interest_fee']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr class="fw-bold border-top">
                                            <td>Total:</td>
                                            <td class="text-end text-success"><?php echo formatPrice($order['total']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> What's Next?</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($order['payment_method'] === 'bank_transfer'): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-clock"></i>
                                    <strong>Payment Verification:</strong> We're verifying your payment proof. This usually takes 2-4 hours during business hours.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check"></i>
                                    <strong>Payment Confirmed:</strong> Your payment has been processed successfully.
                                </div>
                            <?php endif; ?>
                            
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-envelope text-primary"></i> You'll receive an email confirmation shortly</li>
                                <li class="mb-2"><i class="fas fa-truck text-primary"></i> We'll notify you when your order ships</li>
                                <li class="mb-2"><i class="fas fa-phone text-primary"></i> Contact us at +234 807 872 5283 for any questions</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center">
                        <a href="index.php" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-home"></i> Continue Shopping
                        </a>
                        <a href="my-orders.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-list"></i> View My Orders
                        </a>
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
        // Auto-refresh cart count
        document.addEventListener('DOMContentLoaded', function() {
            // Update cart count to 0 since order was placed
            const cartBadges = document.querySelectorAll('.cart-count');
            cartBadges.forEach(badge => {
                badge.textContent = '0';
            });
        });
    </script>
</body>
</html>
