<?php
require_once '../config/database.php';

if (!isAdmin()) {
    redirect('login.php');
}

// First, check if the settings table exists and has the correct structure
try {
    $pdo->query("SELECT setting_key FROM settings LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
}

// Check if the description column exists
try {
    $pdo->query("SELECT description FROM settings LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE settings ADD COLUMN description TEXT AFTER setting_value");
}

// Default settings
$default_settings = [
    'site_name' => ['value' => 'Melodiva Skin Care', 'description' => 'Website name'],
    'site_email' => ['value' => 'melodivaproducts@gmail.com', 'description' => 'Contact email'],
    'site_phone' => ['value' => '+234 807 872 5283', 'description' => 'Contact phone'],
    'click_commission' => ['value' => '100', 'description' => 'Commission per click (₦)'],
    'black_soap_commission' => ['value' => '1000', 'description' => 'Commission per 2kg Black Soap (₦)'],
    'kernel_oil_commission' => ['value' => '1000', 'description' => 'Commission per 1L Kernel Oil (₦)'],
    'referral_discount' => ['value' => '5', 'description' => 'Referral discount percentage'],
    'delivery_fee_lagos' => ['value' => '2000', 'description' => 'Delivery fee for Lagos (₦)'],
    'delivery_fee_other' => ['value' => '3000', 'description' => 'Delivery fee for other states (₦)'],
    'whatsapp_number' => ['value' => '2348078725283', 'description' => 'WhatsApp business number'],
    'bank_name' => ['value' => 'Wema Bank', 'description' => 'Bank name for transfers'],
    'account_number' => ['value' => '0126274934', 'description' => 'Account number'],
    'account_name' => ['value' => 'Melodiva Skin Care', 'description' => 'Account name'],
    'smtp_host' => ['value' => '', 'description' => 'SMTP server host'],
    'smtp_port' => ['value' => '587', 'description' => 'SMTP server port'],
    'smtp_username' => ['value' => '', 'description' => 'SMTP username'],
    'smtp_password' => ['value' => '', 'description' => 'SMTP password'],
    'facebook_url' => ['value' => 'https://www.facebook.com/share/1DtvKgX3Qs/?mibextid=wwXIfr', 'description' => 'Facebook page URL'],
    'instagram_url' => ['value' => '', 'description' => 'Instagram page URL'],
    'tiktok_url' => ['value' => 'https://www.tiktok.com/@melodivaproducts?_t=ZM-8v0fmEeGvhf&_r=1', 'description' => 'TikTok page URL']
];

// Insert default settings if they don't exist
foreach ($default_settings as $key => $data) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $exists = $stmt->fetchColumn();
    
    if (!$exists) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        $stmt->execute([$key, $data['value'], $data['description']]);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        try {
            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && strpos($key, 'setting_') === 0) {
                    $setting_key = substr($key, 8);
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $setting_key]);
                }
            }
            $_SESSION['success'] = "Settings updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
        }
        redirect('settings.php');
    }
}

// Get all settings
$stmt = $pdo->query("SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key");
$settings_data = $stmt->fetchAll();

$settings = [];
foreach ($settings_data as $setting) {
    $settings[$setting['setting_key']] = [
        'value' => $setting['setting_value'],
        'description' => $setting['description']
    ];
}

// Get system info
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit')
];

// Get database stats
$db_stats = [];
$tables = ['users', 'products', 'orders', 'affiliate_clicks'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $db_stats[$table] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $db_stats[$table] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Melodiva Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/admin-style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <i class="fas fa-leaf"></i>
                <span>Melodiva Admin</span>
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="products.php" class="nav-link">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="affiliates.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Affiliates</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="customers.php" class="nav-link">
                    <i class="fas fa-user-friends"></i>
                    <span>Customers</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <!-- Hamburger Menu -->
            <button class="hamburger" id="hamburgerBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="ms-3">
                <div>
                    <h4 class="mb-0 fw-bold">System Settings</h4>
                    <small class="text-muted">Configure your website settings and preferences</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="../index.php" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-external-link-alt"></i> View Site
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Quick Settings Access -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                            <h5>Delivery Settings</h5>
                            <p class="text-muted">Manage delivery pricing and zones</p>
                            <a href="delivery-settings.php" class="btn btn-primary">
                                <i class="fas fa-cog"></i> Configure Delivery
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-percentage fa-3x text-success mb-3"></i>
                            <h5>Commission Settings</h5>
                            <p class="text-muted">Configure affiliate commissions</p>
                            <button class="btn btn-success" onclick="document.getElementById('commission-tab').click()">
                                <i class="fas fa-edit"></i> Edit Commissions
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-credit-card fa-3x text-info mb-3"></i>
                            <h5>Payment Settings</h5>
                            <p class="text-muted">Bank details and payment config</p>
                            <button class="btn btn-info" onclick="document.getElementById('payment-tab').click()">
                                <i class="fas fa-edit"></i> Edit Payment
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tabs -->
            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="fas fa-cog"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="commission-tab" data-bs-toggle="tab" data-bs-target="#commission" type="button" role="tab">
                        <i class="fas fa-percentage"></i> Commission
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
                        <i class="fas fa-credit-card"></i> Payment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
                        <i class="fas fa-envelope"></i> Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab">
                        <i class="fas fa-share-alt"></i> Social Media
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                        <i class="fas fa-server"></i> System Info
                    </button>
                </li>
            </ul>

            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="tab-content" id="settingsTabContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-cog"></i> General Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="site_name" class="form-label">Site Name</label>
                                        <input type="text" class="form-control" name="setting_site_name" 
                                               value="<?php echo htmlspecialchars($settings['site_name']['value'] ?? ''); ?>" required>
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['site_name']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="site_email" class="form-label">Contact Email</label>
                                        <input type="email" class="form-control" name="setting_site_email" 
                                               value="<?php echo htmlspecialchars($settings['site_email']['value'] ?? ''); ?>" required>
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['site_email']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="site_phone" class="form-label">Contact Phone</label>
                                        <input type="text" class="form-control" name="setting_site_phone" 
                                               value="<?php echo htmlspecialchars($settings['site_phone']['value'] ?? ''); ?>" required>
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['site_phone']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                        <input type="text" class="form-control" name="setting_whatsapp_number" 
                                               value="<?php echo htmlspecialchars($settings['whatsapp_number']['value'] ?? ''); ?>" 
                                               placeholder="2348078725283">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['whatsapp_number']['description'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Commission Settings -->
                    <div class="tab-pane fade" id="commission" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-percentage"></i> Commission Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="click_commission" class="form-label">Click Commission (₦)</label>
                                        <input type="number" class="form-control" name="setting_click_commission" 
                                               value="<?php echo htmlspecialchars($settings['click_commission']['value'] ?? ''); ?>" min="0">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['click_commission']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="referral_discount" class="form-label">Referral Discount (%)</label>
                                        <input type="number" class="form-control" name="setting_referral_discount" 
                                               value="<?php echo htmlspecialchars($settings['referral_discount']['value'] ?? ''); ?>" min="0" max="100">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['referral_discount']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="black_soap_commission" class="form-label">Black Soap Commission (₦)</label>
                                        <input type="number" class="form-control" name="setting_black_soap_commission" 
                                               value="<?php echo htmlspecialchars($settings['black_soap_commission']['value'] ?? ''); ?>" min="0">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['black_soap_commission']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="kernel_oil_commission" class="form-label">Kernel Oil Commission (₦)</label>
                                        <input type="number" class="form-control" name="setting_kernel_oil_commission" 
                                               value="<?php echo htmlspecialchars($settings['kernel_oil_commission']['value'] ?? ''); ?>" min="0">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['kernel_oil_commission']['description'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Settings -->
                    <div class="tab-pane fade" id="payment" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="bank_name" class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" name="setting_bank_name" 
                                               value="<?php echo htmlspecialchars($settings['bank_name']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['bank_name']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" name="setting_account_number" 
                                               value="<?php echo htmlspecialchars($settings['account_number']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['account_number']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="account_name" class="form-label">Account Name</label>
                                        <input type="text" class="form-control" name="setting_account_name" 
                                               value="<?php echo htmlspecialchars($settings['account_name']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['account_name']['description'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-envelope"></i> Email Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="setting_smtp_host" 
                                               value="<?php echo htmlspecialchars($settings['smtp_host']['value'] ?? ''); ?>" 
                                               placeholder="smtp.gmail.com">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['smtp_host']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" name="setting_smtp_port" 
                                               value="<?php echo htmlspecialchars($settings['smtp_port']['value'] ?? ''); ?>" 
                                               placeholder="587">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['smtp_port']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" name="setting_smtp_username" 
                                               value="<?php echo htmlspecialchars($settings['smtp_username']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['smtp_username']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" name="setting_smtp_password" 
                                               value="<?php echo htmlspecialchars($settings['smtp_password']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['smtp_password']['description'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Settings -->
                    <div class="tab-pane fade" id="social" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-share-alt"></i> Social Media Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="facebook_url" class="form-label">Facebook Page URL</label>
                                        <input type="url" class="form-control" name="setting_facebook_url" 
                                               value="<?php echo htmlspecialchars($settings['facebook_url']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['facebook_url']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="instagram_url" class="form-label">Instagram Page URL</label>
                                        <input type="url" class="form-control" name="setting_instagram_url" 
                                               value="<?php echo htmlspecialchars($settings['instagram_url']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['instagram_url']['description'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="tiktok_url" class="form-label">TikTok Page URL</label>
                                        <input type="url" class="form-control" name="setting_tiktok_url" 
                                               value="<?php echo htmlspecialchars($settings['tiktok_url']['value'] ?? ''); ?>">
                                        <small class="text-muted"><?php echo htmlspecialchars($settings['tiktok_url']['description'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="tab-pane fade" id="system" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-server"></i> System Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>PHP Version</span>
                                            <strong><?php echo $system_info['php_version']; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>MySQL Version</span>
                                            <strong><?php echo $system_info['mysql_version']; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Server Software</span>
                                            <strong><?php echo $system_info['server_software']; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Upload Max Size</span>
                                            <strong><?php echo $system_info['upload_max_filesize']; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Post Max Size</span>
                                            <strong><?php echo $system_info['post_max_size']; ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Memory Limit</span>
                                            <strong><?php echo $system_info['memory_limit']; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-database"></i> Database Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Users</span>
                                            <strong><?php echo number_format($db_stats['users']); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Products</span>
                                            <strong><?php echo number_format($db_stats['products']); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Orders</span>
                                            <strong><?php echo number_format($db_stats['orders']); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Affiliate Clicks</span>
                                            <strong><?php echo number_format($db_stats['affiliate_clicks']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                <script>
        // Hamburger Menu Functionality
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            hamburgerBtn.classList.toggle('active');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        function closeSidebar() {
            hamburgerBtn.classList.remove('active');
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        hamburgerBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // Close sidebar when clicking on nav links on mobile
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>
