<?php
require_once 'config/database.php';

// Get featured products
$stmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$featured_products = $stmt->fetchAll();

// Get cart count if user is logged in
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
    <title>Melodiva Skin Care - Natural Beauty Products</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Open+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="hero-title">Natural Beauty, Naturally Yours</h1>
                        <p class="hero-subtitle">
                            Discover the power of nature with our premium black soap and kernel oil products. 
                            Handcrafted with love for your skin's natural glow.
                        </p>
                        <div class="hero-buttons">
                            <a href="shop.php" class="btn btn-light btn-lg me-3">
                                <i class="fas fa-store"></i> Shop Now
                            </a>
                            <a href="affiliate.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-users"></i> Join Affiliate
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image text-center">
                        <img src="images/products.jpg?height=400&width=400" alt="Natural Skin Care Products" class="img-fluid rounded-circle" style="max-width: 400px;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="text-primary">Featured Products</h2>
                    <p class="lead">Discover our most popular natural Skin Care products</p>
                </div>
            </div>
            
            <div class="row">
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="product-card">
                            <img src="images/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-image">
                            <div class="product-info">
                                <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="product-type"><?php echo htmlspecialchars($product['type'] . ' - ' . $product['size']); ?></p>
                                <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="product-price"><?php echo formatPrice($product['price']); ?></span>
                                    <?php if (isLoggedIn()): ?>
                                        <button class="btn btn-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-primary" onclick="promptLogin()">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="shop.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-store"></i> View All Products
                </a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="text-primary">Why Choose Melodiva?</h2>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="fas fa-leaf fa-3x text-primary"></i>
                        </div>
                        <h4>100% Natural</h4>
                        <p>All our products are made from natural ingredients with no harmful chemicals.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="fas fa-shipping-fast fa-3x text-primary"></i>
                        </div>
                        <h4>Fast Delivery</h4>
                        <p>Quick and reliable delivery across Nigeria with tracking support.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="fas fa-users fa-3x text-primary"></i>
                        </div>
                        <h4>Affiliate Program</h4>
                        <p>Earn money by promoting our products with our generous affiliate program.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Melodiva -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4">
                    <h2 class="text-primary">About Melodiva Skin Care</h2>
                     <p class="lead">Melodiva Skin Care was registered to do business in Nigeria on 17th November, 2023. Our business is the manufacturing and sales of organic cosmetic products.</p>
                    <p>We manufacture soaps and oils that takes care of the skin, which is the largest organ of the human body. We believe that everyone is naturally beautiful, hence our soaps and oils are made from organic plants, that enhance the natural beauty.</p>
                    <p>Our Products are suitable for people of all ages, race, skin type and color.</p>
                    <div class="row mt-4">
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fas fa-award fa-2x text-primary mb-2"></i>
                                <h6>Premium Quality</h6>
                                <small class="text-muted">Handcrafted with care</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fas fa-heart fa-2x text-primary mb-2"></i>
                                <h6>Customer Love</h6>
                                <small class="text-muted">Trusted by many</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <img src="images/logo-bold.jpg?height=400&width=500" alt="About Melodiva" class="img-fluid rounded shadow">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    <script>
        function promptLogin() {
            if (confirm('You need to sign in to add items to cart. Would you like to sign in now?')) {
                window.location.href = 'auth/login.php';
            }
        }
    </script>
</body>
</html>
