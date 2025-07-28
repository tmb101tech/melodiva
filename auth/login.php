<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }
        
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required']);
            exit;
        }
        
        // Check if user exists and is verified
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
            exit;
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
            exit;
        }
        
        // Login successful - set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_affiliate'] = $user['is_affiliate'];
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful! Welcome back, ' . $user['name'],
            'redirect' => '../index.php'
        ]);
        
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
    } catch (Exception $e) {
        // Log any other errors
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Melodiva Skin Care</title>
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
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card glass-effect">
                    <div class="auth-header text-center mb-4">
                        <div class="logo-container mb-3">
                            <i class="fas fa-leaf logo-icon"></i>
                        </div>
                        <h2 class="auth-title">Welcome Back</h2>
                        <p class="auth-subtitle">Sign in to your account to continue</p>
                    </div>
                    
                    <!-- Alert Container -->
                    <div id="alertContainer"></div>
                    
                    <form id="loginForm" class="auth-form">
                        <div class="form-group mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="password_icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="auth-link">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-animated w-100 mb-3">
                            <span class="btn-text">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Sign In
                            </span>
                            <div class="btn-loader">
                                <div class="spinner"></div>
                            </div>
                        </button>
                    </form>
                    
                    <div class="auth-footer text-center">
                        <p class="mb-0">Don't have an account? 
                            <a href="register.php" class="auth-link">Sign up here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/enhanced-auth.js"></script>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Enhanced login form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoader = submitBtn.querySelector('.btn-loader');
            
            // Show loading state
            submitBtn.disabled = true;
            btnText.style.opacity = '0';
            btnLoader.style.opacity = '1';
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                // Reset button state
                submitBtn.disabled = false;
                btnText.style.opacity = '1';
                btnLoader.style.opacity = '0';
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Response is not valid JSON:', text);
                    showAlert('Server error: Invalid response format', 'danger');
                    return;
                }
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                } else {
                    showAlert(data.message || 'Login failed', 'danger');
                }
            })
            .catch(error => {
                // Reset button state
                submitBtn.disabled = false;
                btnText.style.opacity = '1';
                btnLoader.style.opacity = '0';
                
                console.error('Fetch error:', error);
                showAlert('Network error: Please check your connection and try again.', 'danger');
            });
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
                            </h2>
                            <p class="text-muted">Sign in to your account</p>
                        </div>
                        
                        <!-- Alert Container -->
                        <div id="alertContainer"></div>
                        
                        <form id="loginForm">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <a href="forgot-password.php" class="text-primary">Forgot password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? 
                                <a href="register.php" class="text-primary">Sign up here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Login form submission with proper path handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear previous alerts
            document.getElementById('alertContainer').innerHTML = '';
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            
            // Submit to the same page (login.php handles both GET and POST)
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Response is not valid JSON:', text);
                    showAlert('Server error: Invalid response format', 'danger');
                    return;
                }
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                } else {
                    showAlert(data.message || 'Login failed', 'danger');
                }
            })
            .catch(error => {
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                console.error('Fetch error:', error);
                showAlert('Network error: Please check your connection and try again.', 'danger');
            });
        });
        
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>
</body>
</html>
