<?php
require_once '../config/database.php';

$error = '';
$success = '';
$step = 'email'; // email, verify, reset

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_user') {
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        if (empty($email) || empty($phone)) {
            $error = 'Please fill in all fields.';
        } else {
            try {
                // Check if user exists with matching email and phone
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND phone = ?");
                $stmt->execute([$email, $phone]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_user_email'] = $email;
                    $step = 'reset';
                    $success = 'User verified! You can now reset your password.';
                } else {
                    $error = 'No account found with the provided email and phone number.';
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
                error_log("Forgot password error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'reset_password') {
        if (!isset($_SESSION['reset_user_id'])) {
            $error = 'Session expired. Please start over.';
            $step = 'email';
        } else {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill in all fields.';
                $step = 'reset';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
                $step = 'reset';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long.';
                $step = 'reset';
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);
                    
                    // Clear session
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_user_email']);
                    
                    $success = 'Password reset successfully! You can now login with your new password.';
                    $step = 'complete';
                } catch (PDOException $e) {
                    $error = 'Error updating password. Please try again.';
                    error_log("Password reset error: " . $e->getMessage());
                    $step = 'reset';
                }
            }
        }
    }
}

// Check if we're in reset step from session
if (isset($_SESSION['reset_user_id']) && $step === 'email') {
    $step = 'reset';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Melodiva Skin Care</title>
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
                            <h2 class="auth-title">
                                <?php if ($step === 'email'): ?>
                                    Reset Password
                                <?php elseif ($step === 'reset'): ?>
                                    Create New Password
                                <?php else: ?>
                                    Password Reset Complete
                                <?php endif; ?>
                            </h2>
                            <p class="auth-subtitle">
                                <?php if ($step === 'email'): ?>
                                    Enter your email and phone number to verify your identity
                                <?php elseif ($step === 'reset'): ?>
                                    Enter your new password below
                                <?php else: ?>
                                    Your password has been successfully reset
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <!-- Progress Indicator -->
                        <div class="progress-indicator mb-4">
                            <div class="progress-step <?php echo $step === 'email' ? 'active' : ($step !== 'email' ? 'completed' : ''); ?>">
                                <div class="step-circle">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <span class="step-label">Verify</span>
                            </div>
                            <div class="progress-line <?php echo $step === 'reset' || $step === 'complete' ? 'completed' : ''; ?>"></div>
                            <div class="progress-step <?php echo $step === 'reset' ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>">
                                <div class="step-circle">
                                    <i class="fas fa-key"></i>
                                </div>
                                <span class="step-label">Reset</span>
                            </div>
                            <div class="progress-line <?php echo $step === 'complete' ? 'completed' : ''; ?>"></div>
                            <div class="progress-step <?php echo $step === 'complete' ? 'completed' : ''; ?>">
                                <div class="step-circle">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span class="step-label">Complete</span>
                            </div>
                        </div>
                        
                        <!-- Alert Container -->
                        <div id="alertContainer">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-modern slide-in">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-modern slide-in">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($step === 'email'): ?>
                            <!-- Step 1: Email and Phone Verification -->
                            <form method="POST" class="auth-form">
                                <input type="hidden" name="action" value="verify_user">
                                
                                <div class="form-group mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-4">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-animated w-100 mb-3">
                                    <span class="btn-text">
                                        <i class="fas fa-search me-2"></i>
                                        Verify Account
                                    </span>
                                    <div class="btn-loader">
                                        <div class="spinner"></div>
                                    </div>
                                </button>
                            </form>
                            
                        <?php elseif ($step === 'reset'): ?>
                            <!-- Step 2: Password Reset -->
                            <form method="POST" class="auth-form">
                                <input type="hidden" name="action" value="reset_password">
                                
                                <div class="form-group mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye" id="new_password_icon"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                
                                <div class="form-group mb-4">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye" id="confirm_password_icon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-animated w-100 mb-3">
                                    <span class="btn-text">
                                        <i class="fas fa-key me-2"></i>
                                        Reset Password
                                    </span>
                                    <div class="btn-loader">
                                        <div class="spinner"></div>
                                    </div>
                                </button>
                            </form>
                            
                        <?php else: ?>
                            <!-- Step 3: Success -->
                            <div class="text-center">
                                <div class="success-animation mb-4">
                                    <div class="checkmark-circle">
                                        <div class="checkmark"></div>
                                    </div>
                                </div>
                                
                                <p class="mb-4">Your password has been successfully reset. You can now login with your new password.</p>
                                
                                <a href="login.php" class="btn btn-primary btn-animated w-100">
                                    <span class="btn-text">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Go to Login
                                    </span>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="auth-footer text-center mt-4">
                            <p class="mb-0">
                                Remember your password? 
                                <a href="login.php" class="auth-link">Sign in here</a>
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
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.auth-form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const btnText = submitBtn.querySelector('.btn-text');
                    const btnLoader = submitBtn.querySelector('.btn-loader');
                    
                    // Show loading state
                    submitBtn.disabled = true;
                    btnText.style.opacity = '0';
                    btnLoader.style.opacity = '1';
                    
                    // If it's password reset, validate passwords match
                    if (this.querySelector('input[name="action"]').value === 'reset_password') {
                        const newPassword = this.querySelector('#new_password').value;
                        const confirmPassword = this.querySelector('#confirm_password').value;
                        
                        if (newPassword !== confirmPassword) {
                            e.preventDefault();
                            showAlert('Passwords do not match!', 'danger');
                            
                            // Reset button state
                            submitBtn.disabled = false;
                            btnText.style.opacity = '1';
                            btnLoader.style.opacity = '0';
                            return;
                        }
                        
                        if (newPassword.length < 6) {
                            e.preventDefault();
                            showAlert('Password must be at least 6 characters long!', 'danger');
                            
                            // Reset button state
                            submitBtn.disabled = false;
                            btnText.style.opacity = '1';
                            btnLoader.style.opacity = '0';
                            return;
                        }
                    }
                });
            });
        });
        
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-modern slide-in`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
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