<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if user has applied for affiliate program
$has_applied = isset($user['affiliate_status']) && in_array($user['affiliate_status'], ['pending', 'approved', 'rejected', 'suspended']);

// Handle affiliate application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'apply_affiliate') {
        $why_join = trim($_POST['why_join']);
        $social_media = trim($_POST['social_media']);
        $experience = trim($_POST['experience']);
        
        if (empty($why_join) || empty($social_media)) {
            $error = "Please fill all required fields.";
        } else {
            try {
                // Check if affiliate_status column exists
                try {
                    $pdo->query("SELECT affiliate_status FROM users LIMIT 1");
                } catch (PDOException $e) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN affiliate_status VARCHAR(20) DEFAULT NULL");
                }
                
                // Check if referral_code column exists
                try {
                    $pdo->query("SELECT referral_code FROM users LIMIT 1");
                } catch (PDOException $e) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(20) UNIQUE");
                }
                
                // Check if wallet_balance column exists
                try {
                    $pdo->query("SELECT wallet_balance FROM users LIMIT 1");
                } catch (PDOException $e) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) DEFAULT 0");
                }
                
                // Generate unique referral code
                $base_code = 'MEL' . strtoupper(substr(md5($user['email'] . time()), 0, 6));
                $referral_code = $base_code;
                $counter = 1;
                
                // Ensure referral code is unique
                while (true) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referral_code = ?");
                    $stmt->execute([$referral_code]);
                    if ($stmt->fetchColumn() == 0) {
                        break;
                    }
                    $referral_code = $base_code . $counter;
                    $counter++;
                }
                
                // Update user record
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET affiliate_status = 'pending', 
                        referral_code = ?,
                        affiliate_application_date = NOW(),
                        affiliate_why_join = ?,
                        affiliate_social_media = ?,
                        affiliate_experience = ?
                    WHERE id = ?
                ");
                $stmt->execute([$referral_code, $why_join, $social_media, $experience, $_SESSION['user_id']]);
                
                $success = "Your affiliate application has been submitted successfully! We'll review it shortly.";
                $has_applied = true;
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                $error = "Error submitting application. Please try again.";
                error_log("Affiliate application error: " . $e->getMessage());
            }
        }
    }
}

// Get cart count
$cart_count = 0;
$stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch();
$cart_count = $result['count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Program - Melodiva Skin Care</title>
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
                            <?php if (isset($user['affiliate_status']) && $user['affiliate_status'] === 'approved'): ?>
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
                    <h1 class="text-primary"><i class="fas fa-users"></i> Affiliate Program</h1>
                    <p class="text-muted">Join our affiliate program and earn commissions</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Affiliate Content -->
    <section class="py-5">
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($user['affiliate_status']) && $user['affiliate_status'] === 'approved'): ?>
                <!-- Approved Affiliate -->
                <div class="card mb-4">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <span class="badge bg-success p-2 mb-3">Approved Affiliate</span>
                            <h2 class="mb-3">Welcome to the Melodiva Affiliate Program!</h2>
                            <p class="lead">Your referral code is: <strong class="text-success"><?php echo htmlspecialchars($user['referral_code']); ?></strong></p>
                            <p>Share this code with customers and earn commissions on their purchases.</p>
                        </div>
                        
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="d-grid gap-3 d-md-flex justify-content-md-center">
                                    <a href="affiliate-dashboard.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-chart-line"></i> View Dashboard
                                    </a>
                                    <a href="shop.php" class="btn btn-outline-primary btn-lg">
                                        <i class="fas fa-store"></i> Shop Products
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-mouse-pointer fa-3x text-primary mb-3"></i>
                                <h4>₦100 Per Click</h4>
                                <p>Earn ₦100 for every click on your referral link</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-percentage fa-3x text-success mb-3"></i>
                                <h4>5% Discount</h4>
                                <p>Your customers get 5% off their purchase</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-money-bill-wave fa-3x text-warning mb-3"></i>
                                <h4>Product Commission</h4>
                                <p>₦1000 per 2kg Black Soap<br>₦1000 per 1L Kernel Oil</p>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif (isset($user['affiliate_status']) && $user['affiliate_status'] === 'pending'): ?>
                <!-- Pending Application -->
                <div class="card mb-4">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <span class="badge bg-warning p-2 mb-3">Application Pending</span>
                            <h2 class="mb-3">Your Application is Under Review</h2>
                            <p class="lead">Thank you for applying to our affiliate program!</p>
                            <p>We're currently reviewing your application and will notify you once it's approved.</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Your referral code will be activated once your application is approved.
                        </div>
                    </div>
                </div>
                
            <?php elseif (isset($user['affiliate_status']) && $user['affiliate_status'] === 'rejected'): ?>
                <!-- Rejected Application -->
                <div class="card mb-4">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <span class="badge bg-danger p-2 mb-3">Application Rejected</span>
                            <h2 class="mb-3">Your Application Was Not Approved</h2>
                            <p class="lead">Unfortunately, we couldn't approve your affiliate application at this time.</p>
                            <p>Please review our requirements and consider reapplying in the future.</p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> If you believe this was a mistake, please contact our support team.
                        </div>
                    </div>
                </div>
                
            <?php elseif (isset($user['affiliate_status']) && $user['affiliate_status'] === 'suspended'): ?>
                <!-- Suspended Account -->
                <div class="card mb-4">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <span class="badge bg-danger p-2 mb-3">Account Suspended</span>
                            <h2 class="mb-3">Your Affiliate Account is Suspended</h2>
                            <p class="lead">Your affiliate account has been temporarily suspended.</p>
                            <p>Please contact our support team for more information.</p>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Your referral code is currently inactive and cannot be used.
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- New Application -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> About Our Affiliate Program</h5>
                            </div>
                            <div class="card-body">
                                <h4>Why Join Our Affiliate Program?</h4>
                                <ul class="mb-4">
                                    <li>Earn ₦100 for every click on your referral link</li>
                                    <li>Earn ₦1000 commission per 2kg of Black Soap sold</li>
                                    <li>Earn ₦1000 commission per 1L of Kernel Oil sold</li>
                                    <li>Proportional commission for smaller quantities</li>
                                    <li>Your customers get 5% discount on all purchases</li>
                                    <li>Redeem earnings as cash or shopping credit</li>
                                </ul>
                                
                                <h4>Requirements</h4>
                                <ul>
                                    <li>Active social media presence</li>
                                    <li>Genuine interest in skincare products</li>
                                    <li>Commitment to ethical promotion</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-plus"></i> Apply to Become an Affiliate</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="apply_affiliate">
                                    
                                    <div class="form-group mb-3">
                                        <label for="why_join" class="form-label">Why do you want to join our affiliate program? *</label>
                                        <textarea class="form-control" id="why_join" name="why_join" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="social_media" class="form-label">What social media platforms do you use? *</label>
                                        <textarea class="form-control" id="social_media" name="social_media" rows="2" 
                                                  placeholder="e.g., Instagram: @username, Facebook: facebook.com/username" required></textarea>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="experience" class="form-label">Do you have any previous affiliate experience?</label>
                                        <textarea class="form-control" id="experience" name="experience" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit Application
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- How It Works -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-question-circle"></i> How It Works</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-4 mb-md-0">
                            <div class="text-center">
                                <div class="step-circle mb-3">1</div>
                                <h5>Apply</h5>
                                <p>Submit your application to join our affiliate program</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4 mb-md-0">
                            <div class="text-center">
                                <div class="step-circle mb-3">2</div>
                                <h5>Get Approved</h5>
                                <p>We'll review and approve your application</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4 mb-md-0">
                            <div class="text-center">
                                <div class="step-circle mb-3">3</div>
                                <h5>Share Your Code</h5>
                                <p>Share your unique referral code with customers</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="step-circle mb-3">4</div>
                                <h5>Earn Commission</h5>
                                <p>Earn commission on clicks and sales</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Affiliate Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h5>1. Eligibility</h5>
                    <p>To be eligible for the Melodiva Skin Care Affiliate Program, you must:</p>
                    <ul>
                        <li>Be at least 18 years of age</li>
                        <li>Have an active social media presence</li>
                        <li>Comply with all applicable laws and regulations</li>
                    </ul>
                    
                    <h5>2. Commission Structure</h5>
                    <p>Affiliates will earn:</p>
                    <ul>
                        <li>₦100 per click on referral links</li>
                        <li>₦1000 per 2kg of Black Soap sold</li>
                        <li>₦1000 per 1L of Kernel Oil sold</li>
                        <li>Proportional commission for smaller quantities</li>
                    </ul>
                    
                    <h5>3. Payment Terms</h5>
                    <p>Commissions will be credited to your affiliate wallet. You can redeem your earnings as:</p>
                    <ul>
                        <li>Bank transfer (minimum ₦5000)</li>
                        <li>Shopping credit for Melodiva products</li>
                    </ul>
                    
                    <h5>4. Prohibited Activities</h5>
                    <p>Affiliates must not:</p>
                    <ul>
                        <li>Make false or misleading claims about Melodiva products</li>
                        <li>Engage in spamming or any form of unethical marketing</li>
                        <li>Use the Melodiva brand in a way that damages its reputation</li>
                    </ul>
                    
                    <h5>5. Termination</h5>
                    <p>Melodiva reserves the right to terminate any affiliate account for violation of these terms or for any other reason at its sole discretion.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

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
</body>
</html>
