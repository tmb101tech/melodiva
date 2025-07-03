<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address']);
        
        if (empty($name) || empty($phone) || empty($address)) {
            $error = "All fields are required.";
        } else {
            try {
                // Check if database has new columns (state, city, street_address)
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'state'");
                $hasNewColumns = $stmt->rowCount() > 0;
                
                if ($hasNewColumns) {
                    // Use new database structure
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, state = ?, city = ?, street_address = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $state, $city, $address, $_SESSION['user_id']]);
                } else {
                    // Use old database structure - combine address fields
                    $full_address = $address . ($city ? ", $city" : "") . ($state ? ", $state" : "");
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $full_address, $_SESSION['user_id']]);
                }
                
                $_SESSION['user_name'] = $name;
                $success = "Profile updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating profile. Please try again.";
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_error = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $password_error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $password_error = "New password must be at least 6 characters long.";
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password'])) {
                    $password_error = "Current password is incorrect.";
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    $password_success = "Password changed successfully!";
                }
            } catch (PDOException $e) {
                $password_error = "Error changing password. Please try again.";
                error_log("Password change error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'update_bank_details') {
        $bank_name = trim($_POST['bank_name']);
        $account_number = trim($_POST['account_number']);
        $account_name = trim($_POST['account_name']);
        
        if (empty($bank_name) || empty($account_number) || empty($account_name)) {
            $bank_error = "All bank details are required.";
        } else {
            try {
                // Add bank details columns if they don't exist
                try {
                    $pdo->query("SELECT bank_name FROM users LIMIT 1");
                } catch (PDOException $e) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN bank_name VARCHAR(100)");
                    $pdo->exec("ALTER TABLE users ADD COLUMN account_number VARCHAR(20)");
                    $pdo->exec("ALTER TABLE users ADD COLUMN account_name VARCHAR(100)");
                }
                
                $stmt = $pdo->prepare("UPDATE users SET bank_name = ?, account_number = ?, account_name = ? WHERE id = ?");
                $stmt->execute([$bank_name, $account_number, $account_name, $_SESSION['user_id']]);
                
                $bank_success = "Bank details updated successfully!";
            } catch (PDOException $e) {
                $bank_error = "Error updating bank details. Please try again.";
                error_log("Bank details update error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'redeem_commission') {
        $redeem_type = $_POST['redeem_type'];
        $amount = floatval($_POST['amount']);
        
        if ($amount <= 0) {
            $redeem_error = "Invalid amount.";
        } else {
            try {
                // Get current wallet balance
                $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $current_balance = $stmt->fetchColumn();
                
                if ($amount > $current_balance) {
                    $redeem_error = "Insufficient balance.";
                } else {
                    if ($redeem_type === 'bank') {
                        // Create withdrawal request
                        $pdo->exec("
                            CREATE TABLE IF NOT EXISTS withdrawal_requests (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                user_id INT NOT NULL,
                                amount DECIMAL(10,2) NOT NULL,
                                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (user_id) REFERENCES users(id)
                            )
                        ");
                        
                        $stmt = $pdo->prepare("INSERT INTO withdrawal_requests (user_id, amount) VALUES (?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $amount]);
                        
                        // Deduct from wallet
                        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                        $stmt->execute([$amount, $_SESSION['user_id']]);
                        
                        $redeem_success = "Withdrawal request submitted successfully! You will receive payment within 24-48 hours.";
                    } elseif ($redeem_type === 'discount') {
                        // Apply as shopping credit
                        $_SESSION['shopping_credit'] = $amount;
                        
                        // Deduct from wallet
                        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                        $stmt->execute([$amount, $_SESSION['user_id']]);
                        
                        $redeem_success = "Shopping credit of " . formatPrice($amount) . " applied! Use it during checkout.";
                    }
                }
            } catch (PDOException $e) {
                $redeem_error = "Error processing redemption. Please try again.";
                error_log("Redemption error: " . $e->getMessage());
            }
        }
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if database has new columns
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'state'");
$hasNewColumns = $stmt->rowCount() > 0;

// Extract address components if using old structure
if (!$hasNewColumns && !empty($user['address'])) {
    $address_parts = explode(',', $user['address']);
    $state = trim(end($address_parts));
    $city = (count($address_parts) > 1) ? trim($address_parts[count($address_parts) - 2]) : '';
    $street_address = (count($address_parts) > 2) ? trim(implode(',', array_slice($address_parts, 0, -2))) : trim($address_parts[0]);
} else {
    $state = $user['state'] ?? '';
    $city = $user['city'] ?? '';
    $street_address = $hasNewColumns ? ($user['street_address'] ?? '') : ($user['address'] ?? '');
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
    <title>My Profile - Melodiva Skincare</title>
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
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
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
                    <h1 class="text-primary"><i class="fas fa-user-edit"></i> My Profile</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active">Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <!-- Profile Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Profile Info -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user"></i> Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" 
                                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                                            <small class="text-muted">Email cannot be changed</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="phone" class="form-label">Phone/WhatsApp Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="state" class="form-label">State</label>
                                            <select class="form-select" id="state" name="state">
                                                <option value="">Select State</option>
                                                <?php
                                                $states = [
                                                    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
                                                    'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo',
                                                    'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
                                                    'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
                                                    'Yobe', 'Zamfara'
                                                ];
                                                foreach ($states as $st) {
                                                    $selected = ($st === $state) ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($st) . "\" $selected>" . htmlspecialchars($st) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="city" name="city" 
                                                   value="<?php echo htmlspecialchars($city); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="address" class="form-label">Street Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($street_address); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Account Status -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Account Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Account Status:</strong>
                                <span class="badge <?php echo ($user['is_verified'] ?? false) ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ($user['is_verified'] ?? false) ? 'Verified' : 'Pending Verification'; ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Affiliate Status:</strong>
                                <?php if (isset($user['affiliate_status'])): ?>
                                    <?php if ($user['affiliate_status'] === 'approved'): ?>
                                        <span class="badge bg-success">Active Affiliate</span>
                                    <?php elseif ($user['affiliate_status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Pending Approval</span>
                                    <?php elseif ($user['affiliate_status'] === 'suspended'): ?>
                                        <span class="badge bg-danger">Suspended</span>
                                    <?php elseif ($user['affiliate_status'] === 'rejected'): ?>
                                        <span class="badge bg-secondary">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not an Affiliate</span>
                                        <br><small><a href="affiliate.php" class="text-primary">Apply here</a></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not an Affiliate</span>
                                    <br><small><a href="affiliate.php" class="text-primary">Apply here</a></small>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($user['affiliate_status']) && $user['affiliate_status'] === 'approved'): ?>
                                <div class="mb-3">
                                    <strong>Wallet Balance:</strong>
                                    <br><span class="h5 text-success"><?php echo formatPrice($user['wallet_balance'] ?? 0); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Referral Code:</strong>
                                    <br><code class="bg-light p-2 rounded"><?php echo htmlspecialchars($user['referral_code'] ?? ''); ?></code>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <strong>Member Since:</strong>
                                <br><?php echo isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'N/A'; ?>
                            </div>
                            
                            <div class="d-grid">
                                <a href="my-orders.php" class="btn btn-outline-primary">
                                    <i class="fas fa-box"></i> View My Orders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Affiliate Section -->
            <?php if (isset($user['affiliate_status']) && $user['affiliate_status'] === 'approved'): ?>
                <div class="row">
                    <!-- Bank Details -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-university"></i> Bank Details</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($bank_success)): ?>
                                    <div class="alert alert-success"><?php echo $bank_success; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($bank_error)): ?>
                                    <div class="alert alert-danger"><?php echo $bank_error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_bank_details">
                                    
                                    <div class="form-group mb-3">
                                        <label for="bank_name" class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                               value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="account_number" name="account_number" 
                                               value="<?php echo htmlspecialchars($user['account_number'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="account_name" class="form-label">Account Name</label>
                                        <input type="text" class="form-control" id="account_name" name="account_name" 
                                               value="<?php echo htmlspecialchars($user['account_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Bank Details
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Commission Redemption -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Redeem Commission</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($redeem_success)): ?>
                                    <div class="alert alert-success"><?php echo $redeem_success; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($redeem_error)): ?>
                                    <div class="alert alert-danger"><?php echo $redeem_error; ?></div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Available Balance:</strong>
                                    <span class="h5 text-success"><?php echo formatPrice($user['wallet_balance'] ?? 0); ?></span>
                                </div>
                                
                                <?php if (($user['wallet_balance'] ?? 0) > 0): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="redeem_commission">
                                        
                                        <div class="form-group mb-3">
                                            <label for="amount" class="form-label">Amount to Redeem</label>
                                            <input type="number" class="form-control" id="amount" name="amount" 
                                                   step="0.01" min="1" max="<?php echo $user['wallet_balance']; ?>" required>
                                        </div>
                                        
                                        <div class="form-group mb-3">
                                            <label class="form-label">Redemption Type</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="redeem_type" id="bank" value="bank" checked>
                                                <label class="form-check-label" for="bank">
                                                    Bank Transfer (Requires bank details)
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="redeem_type" id="discount" value="discount">
                                                <label class="form-check-label" for="discount">
                                                    Shopping Credit (Use as discount)
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-money-bill-wave"></i> Redeem Now
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <p class="text-muted">No commission available for redemption.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Change Password -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-lock"></i> Change Password</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($password_success)): ?>
                                <div class="alert alert-success"><?php echo $password_success; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($password_error)): ?>
                                <div class="alert alert-danger"><?php echo $password_error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
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
                    <p>&copy; 2024 Melodiva Skincare. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
