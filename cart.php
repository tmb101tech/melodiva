<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
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

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Check for applied affiliate code from session only (bypass database)
$discount = 0;
$affiliate_code = $_SESSION['affiliate_code'] ?? '';
$affiliate_name = $_SESSION['affiliate_name'] ?? '';

if ($affiliate_code) {
    $discount = $subtotal * 0.05; // 5% discount
}

// Check for shopping credit from session
$shopping_credit = $_SESSION['shopping_credit'] ?? 0;

$delivery_fee = 2500; // Default, will be calculated based on location
$interest_fee = ($subtotal - $discount - $shopping_credit) * 0.02; // 2% interest
$total = $subtotal - $discount - $shopping_credit + $delivery_fee + $interest_fee;

// Ensure total is not negative
if ($total < 0) {
    $total = 0;
}

// Get cart count for navigation
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
    <title>Shopping Cart - Melodiva Skin Care</title>
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
                        <a class="nav-link active" href="cart.php">
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
                    <h1 class="text-primary"><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
                            <li class="breadcrumb-item active">Cart</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <!-- Cart Content -->
    <section class="py-5">
        <div class="container">
            <!-- Alert Container -->
            <div id="alertContainer"></div>
            
            <?php if (empty($cart_items)): ?>
                <!-- Empty Cart -->
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
                    <h3 class="text-muted">Your cart is empty</h3>
                    <p class="text-muted mb-4">Add some products to your cart to continue shopping</p>
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-store"></i> Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list"></i> Cart Items (<?php echo count($cart_items); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="row align-items-center mb-4 cart-item" data-item-id="<?php echo $item['id']; ?>">
                                        <div class="col-md-2">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="img-fluid rounded">
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($item['size']); ?></p>
                                            <small class="text-success">In Stock: <?php echo $item['stock']; ?></small>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="input-group">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="form-control form-control-sm text-center quantity-input" 
                                                       value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>"
                                                       onchange="updateQuantity(<?php echo $item['id']; ?>, 0, this.value)">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <span class="fw-bold"><?php echo formatPrice($item['price']); ?></span>
                                        </div>
                                        <div class="col-md-2">
                                            <span class="fw-bold item-total"><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                            <button class="btn btn-outline-danger btn-sm d-block mt-1" onclick="removeItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                    <hr>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <!-- Affiliate Code Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-tag"></i> Affiliate Code</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($affiliate_code): ?>
                                    <div class="alert alert-success">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($affiliate_code); ?></strong>
                                                <?php if ($affiliate_name): ?>
                                                    <br><small>by <?php echo htmlspecialchars($affiliate_name); ?></small>
                                                <?php endif; ?>
                                                <br><small class="text-success">5% discount applied!</small>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removeAffiliateCode()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="affiliateCodeInput" 
                                               placeholder="Enter affiliate code">
                                        <button class="btn btn-primary" type="button" onclick="applyAffiliateCode()">
                                            Apply
                                        </button>
                                    </div>
                                    <small class="text-muted">Get 5% discount with a valid affiliate code</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-receipt"></i> Order Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span id="subtotalDisplay"><?php echo formatPrice($subtotal); ?></span>
                                </div>
                                
                                <?php if ($discount > 0): ?>
                                    <div class="d-flex justify-content-between mb-2 text-success">
                                        <span>Affiliate Discount (5%):</span>
                                        <span id="discountDisplay">-<?php echo formatPrice($discount); ?></span>
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
                                    <span class="text-muted">Calculated at checkout</span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Interest Fee (2%):</span>
                                    <span class="text-muted">Calculated at checkout</span>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between fw-bold h6">
                                    <span>Estimated Total:</span>
                                    <span id="totalDisplay"><?php echo formatPrice($subtotal - $discount - $shopping_credit); ?></span>
                                </div>
                                
                                <small class="text-muted">*Final total will include delivery and interest fees</small>
                                
                                <div class="d-grid mt-3">
                                    <a href="checkout.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                                    </a>
                                </div>
                                
                                <div class="d-grid mt-2">
                                    <a href="shop.php" class="btn btn-outline-primary">
                                        <i class="fas fa-store"></i> Continue Shopping
                                    </a>
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

        function updateQuantity(itemId, change, newValue = null) {
            const quantityInput = document.querySelector(`[data-item-id="${itemId}"] .quantity-input`);
            let quantity = newValue !== null ? parseInt(newValue) : parseInt(quantityInput.value) + change;
            
            if (quantity < 1) quantity = 1;
            
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update',
                    item_id: itemId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while updating quantity', 'danger');
            });
        }

        function removeItem(itemId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove',
                    item_id: itemId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while removing item', 'danger');
            });
        }

        function applyAffiliateCode() {
            const codeInput = document.getElementById('affiliateCodeInput');
            const code = codeInput.value.trim();
            
            if (!code) {
                showAlert('Please enter an affiliate code', 'warning');
                return;
            }
            
            // Use the simplified affiliate API
            fetch('api/affiliate-simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'apply_code',
                    code: code
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while applying affiliate code', 'danger');
            });
        }

        function removeAffiliateCode() {
            fetch('api/affiliate-simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove_code'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while removing affiliate code', 'danger');
            });
        }

        // Allow Enter key to apply affiliate code
        document.getElementById('affiliateCodeInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyAffiliateCode();
            }
        });
    </script>
</body>
</html>
