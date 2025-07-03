<?php
require_once 'config/database.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["is_active = 1"];
$params = [];

if ($category) {
    // Fix category filter to match product names correctly
    if ($category == 'black-soap') {
        $where_conditions[] = "name LIKE ?";
        $params[] = 'Black Soap%';
    } elseif ($category == 'kernel-oil') {
        $where_conditions[] = "name LIKE ?";
        $params[] = 'Kernel Oil%';
    } else {
        $where_conditions[] = "name LIKE ?";
        $params[] = '%' . $category . '%';
    }
}

if ($type) {
    $where_conditions[] = "type = ?";
    $params[] = $type;
}

if ($search) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("SELECT * FROM products WHERE $where_clause ORDER BY name, type, size");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get cart count
$cart_count = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cart_count = $result['count'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Melodiva Skin Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation (same as index.php) -->
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
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
                                <i class="fas fa-shopping-cart"></i> Cart 
                                <span class="badge bg-primary cart-count"><?php echo $cart_count; ?></span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> My Account
                            </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="my-orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                            <?php if (isset($user['affiliate_status']) && $user['affiliate_status'] === 'approved'): ?>
                                <li><a class="dropdown-item" href="affiliate-dashboard.php"><i class="fas fa-chart-line"></i> Affiliate Dashboard</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-primary me-2 px-3" href="auth/login.php" style="border-radius: 25px;">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary px-3" href="auth/register.php" style="border-radius: 25px; color: white;">
                                <i class="fas fa-user-plus"></i> Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="py-5 mt-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="text-primary">Our Products</h1>
                    <p class="lead">Discover our range of natural Skin Care products</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters and Search -->
    <section class="py-4 border-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <form method="GET" class="d-flex">
                        <input type="text" class="form-control me-2" name="search" 
                               placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <div class="col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="shop.php" class="btn btn-outline-primary <?php echo empty($category) && empty($type) ? 'active' : ''; ?>">
                            All Products
                        </a>
                        <a href="shop.php?category=black-soap" class="btn btn-outline-primary <?php echo $category === 'black-soap' ? 'active' : ''; ?>">
                            Black Soap
                        </a>
                        <a href="shop.php?category=kernel-oil" class="btn btn-outline-primary <?php echo $category === 'kernel-oil' ? 'active' : ''; ?>">
                            Kernel Oil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Grid -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h3 class="text-muted">No products found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                    <a href="shop.php" class="btn btn-primary">View All Products</a>
                </div>
            <?php else: ?>
                <div class="row" id="searchResults">
                    <?php foreach ($products as $product): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="product-card">
                                <img src="images/<?php echo htmlspecialchars($product['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="product-image">
                                <div class="product-info">
                                    <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="product-type"><?php echo htmlspecialchars($product['type'] . ' - ' . $product['size']); ?></p>
                                    <p class="text-muted small"><?php echo htmlspecialchars($product['description']); ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="product-price"><?php echo formatPrice($product['price']); ?></span>
                                        <small class="text-muted">
                                            <i class="fas fa-box"></i> <?php echo $product['stock']; ?> in stock
                                        </small>
                                    </div>
                                    
                                    <?php if (isLoggedIn()): ?>
                                        <?php if ($product['stock'] > 0): ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <label class="form-label me-2 mb-0">Qty:</label>
                                                <div class="quantity-controls">
                                                    <button type="button" class="quantity-btn decrement">-</button>
                                                    <input type="number" class="form-control quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>" style="width: 60px;">
                                                    <button type="button" class="quantity-btn increment">+</button>
                                                </div>
                                            </div>
                                            
                                            <button class="btn btn-primary w-100 add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                                <i class="fas fa-cart-plus"></i> Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary w-100" disabled>
                                                <i class="fas fa-times"></i> Out of Stock
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="auth/login.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-sign-in-alt"></i> Login to Purchase
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer (same as index.php) -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5><i class="fas fa-leaf"></i> Melodiva Skin Care</h5>
                    <p>Your trusted source for pure natural Skin Care products. We believe in the power of nature to enhance your beauty.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/share/1DtvKgX3Qs/?mibextid=wwXIfr" class="me-3"><i class="fab fa-facebook fa-2x"></i></a>
                        <a href="#" class="me-3"><i class="fab fa-instagram fa-2x"></i></a>
                        <a href="https://www.tiktok.com/@melodivaproducts?_t=ZM-8v0fmEeGvhf&_r=1" class="me-3"><i class="fab fa-tiktok fa-2x"></i></a>
                        <a href="https://wa.me/2348078725283" class="me-3"><i class="fab fa-whatsapp fa-2x"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="shop.php">Shop</a></li>
                        <li><a href="affiliate.php">Affiliate</a></li>
                        <li><a href="terms.html">Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Products</h5>
                    <ul class="list-unstyled">
                        <li><a href="shop.php?category=black-soap">Black Soap</a></li>
                        <li><a href="shop.php?category=kernel-oil">Kernel Oil</a></li>
                        <li><a href="shop.php?type=exquisite">Exquisite Collection</a></li>
                        <li><a href="shop.php?type=natural">Natural Collection</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Contact Info</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone"></i> +234 807 872 5283</li>
                        <li style="font-size: 15px;"><i class="fas fa-envelope"></i> melodivaproducts@gmail.com</li>
                        <li><i class="fas fa-map-marker-alt"></i> Lagos, Nigeria</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row">
                <div class="col-12 text-center">
                    <p>&copy; 2025 Melodiva Skin Care. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
