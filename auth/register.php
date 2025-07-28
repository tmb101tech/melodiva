<?php
require_once '../config/database.php';

// Load states and cities data
$states_cities = json_decode(file_get_contents('../states_cities.json'), true);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $state = trim($_POST['state']);
    $city = trim($_POST['city']);
    $address = trim($_POST['address']);
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($state) || empty($city)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Generate referral code
                $referral_code = 'MEL' . strtoupper(substr(md5($email . time()), 0, 6));
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Check if users table has new columns
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'state'");
                $hasNewColumns = $stmt->rowCount() > 0;
                
                if ($hasNewColumns) {
                    // Insert with new column structure
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password, state, city, street_address, referral_code, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$name, $email, $phone, $hashed_password, $state, $city, $address, $referral_code]);
                } else {
                    // Insert with old column structure
                    $full_address = $address . ", " . $city . ", " . $state;
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password, address, referral_code, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$name, $email, $phone, $hashed_password, $full_address, $referral_code]);
                }
                
                $success = 'Registration successful! Your referral code is: ' . $referral_code . '. Please wait for admin approval.';
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Melodiva Skin Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/enhanced-style.css" rel="stylesheet">
</head>
<body class="auth-body">
    <!-- Animated Background -->
    <div class="auth-background">
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
            <div class="shape shape-5"></div>
        </div>
    </div>

    <div class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="auth-card glass-effect">
                        <div class="auth-header text-center mb-4">
                            <div class="logo-container mb-3">
                                <i class="fas fa-leaf logo-icon"></i>
                            </div>
                            <h2 class="auth-title">Create Account</h2>
                            <p class="auth-subtitle">Join Melodiva Skin Care family today</p>
                        </div>
                        
                        <!-- Alert Container -->
                        <div id="alertContainer">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-modern slide-in">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-modern slide-in">
                                <i class="fas fa-check-circle me-2"></i>
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-primary btn-animated">
                                        <span class="btn-text">Login Now</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        </div>
                        
                        <form method="POST" id="registerForm" class="auth-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="phone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="password" class="form-label">Password *</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="state" class="form-label">State *</label>
                                        <select class="form-select" id="state" name="state" required>
                                            <option value="">Select State</option>
                                            <?php foreach ($states_cities as $state_data): ?>
                                                <option value="<?php echo htmlspecialchars($state_data['name']); ?>" 
                                                        <?php echo (($_POST['state'] ?? '') === $state_data['name']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($state_data['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="city" class="form-label">City/LGA *</label>
                                        <select class="form-select" id="city" name="city" required>
                                            <option value="">Select City/LGA</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="address" class="form-label">Street Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" 
                                          placeholder="Enter your street address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-animated">
                                    <span class="btn-text">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Create Account
                                    </span>
                                    <div class="btn-loader">
                                        <div class="spinner"></div>
                                    </div>
                                </button>
                            </div>
                        </form>
                        
                        <div class="auth-footer text-center mt-4">
                            <p class="mb-0">Already have an account? 
                                <a href="login.php" class="auth-link">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/enhanced-auth.js"></script>
    
    <script>
        // States and cities data
        const statesCities = <?php echo json_encode($states_cities); ?>;
        
        // Handle state change
        document.getElementById('state').addEventListener('change', function() {
            const selectedState = this.value;
            const citySelect = document.getElementById('city');
            
            // Clear existing options
            citySelect.innerHTML = '<option value="">Select City/LGA</option>';
            
            if (selectedState) {
                // Find the selected state data
                const stateData = statesCities.find(state => state.name === selectedState);
                
                if (stateData && stateData.cities) {
                    // Add cities to the select
                    stateData.cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city;
                        option.textContent = city;
                        citySelect.appendChild(option);
                    });
                }
            }
        });
        
        // Trigger change event if state is already selected (for form validation errors)
        if (document.getElementById('state').value) {
            document.getElementById('state').dispatchEvent(new Event('change'));
            
            // Set the previously selected city
            const selectedCity = '<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>';
            if (selectedCity) {
                setTimeout(() => {
                    document.getElementById('city').value = selectedCity;
                }, 100);
            }
        }
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoader = submitBtn.querySelector('.btn-loader');
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('Passwords do not match!', 'danger');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showAlert('Password must be at least 6 characters long!', 'danger');
                return false;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            btnText.style.opacity = '0';
            btnLoader.style.opacity = '1';
        });
        
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-modern slide-in`;
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            alert.innerHTML = `
                <i class="fas fa-${icon} me-2"></i>
                ${message}
            `;
            alertContainer.appendChild(alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alert.classList.add('slide-out');
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>
