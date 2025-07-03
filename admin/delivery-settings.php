<?php
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_lagos_pricing'])) {
            // Update Lagos city pricing
            foreach ($_POST['lagos_cities'] as $city_id => $city_data) {
                $stmt = $pdo->prepare("
                    UPDATE lagos_delivery_prices 
                    SET price = ?, delivery_time = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $city_data['price'],
                    $city_data['delivery_time'],
                    $city_id
                ]);
            }
            $success = 'Lagos delivery pricing updated successfully!';
        }
        
        if (isset($_POST['update_state_pricing'])) {
            // Update state pricing
            foreach ($_POST['states'] as $state_id => $state_data) {
                $stmt = $pdo->prepare("
                    UPDATE state_delivery_prices 
                    SET price = ?, delivery_time = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $state_data['price'],
                    $state_data['delivery_time'],
                    $state_id
                ]);
            }
            $success = 'State delivery pricing updated successfully!';
        }
        
        if (isset($_POST['update_settings'])) {
            // Update delivery settings
            foreach ($_POST['settings'] as $setting_name => $setting_value) {
                $stmt = $pdo->prepare("
                    UPDATE delivery_settings 
                    SET setting_value = ? 
                    WHERE setting_name = ?
                ");
                $stmt->execute([$setting_value, $setting_name]);
            }
            $success = 'Delivery settings updated successfully!';
        }
        
    } catch (PDOException $e) {
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get Lagos cities pricing
$stmt = $pdo->query("SELECT * FROM lagos_delivery_prices ORDER BY city_name");
$lagos_cities = $stmt->fetchAll();

// Get states pricing
$stmt = $pdo->query("SELECT * FROM state_delivery_prices ORDER BY state_name");
$states = $stmt->fetchAll();

// Get delivery settings
$stmt = $pdo->query("SELECT * FROM delivery_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin-style.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
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
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-header">
                            <!-- Hamburger Menu -->
                            <button class="hamburger" id="hamburgerBtn">
                                <span></span>
                                <span></span>
                                <span></span>
                            </button>
                            <h1><i class="fas fa-truck"></i> Delivery Settings</h1>
                            <p class="text-muted">Manage delivery pricing and settings</p>
                        </div>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Delivery Settings -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-cog"></i> General Delivery Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Free Delivery Threshold (₦)</label>
                                                <input type="number" class="form-control" 
                                                       name="settings[free_delivery_threshold]" 
                                                       value="<?php echo htmlspecialchars($settings['free_delivery_threshold'] ?? '50000'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Same Day Delivery Cutoff Time</label>
                                                <input type="time" class="form-control" 
                                                       name="settings[same_day_delivery_cutoff]" 
                                                       value="<?php echo htmlspecialchars($settings['same_day_delivery_cutoff'] ?? '14:00'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Express Delivery Multiplier</label>
                                                <input type="number" step="0.1" class="form-control" 
                                                       name="settings[express_delivery_multiplier]" 
                                                       value="<?php echo htmlspecialchars($settings['express_delivery_multiplier'] ?? '1.5'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Delivery Service Status</label>
                                                <select class="form-select" name="settings[delivery_active]">
                                                    <option value="1" <?php echo ($settings['delivery_active'] ?? '1') == '1' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="0" <?php echo ($settings['delivery_active'] ?? '1') == '0' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" name="update_settings" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Settings
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Lagos Cities Pricing -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-map-marker-alt"></i> Lagos Cities Delivery Pricing</h5>
                                <small class="text-muted">Company is located in Ikorodu, Lagos</small>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>City/LGA</th>
                                                    <th>Price (₦)</th>
                                                    <th>Delivery Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($lagos_cities as $city): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($city['city_name']); ?></td>
                                                        <td>
                                                            <input type="number" class="form-control" 
                                                                   name="lagos_cities[<?php echo $city['id']; ?>][price]" 
                                                                   value="<?php echo $city['price']; ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control" 
                                                                   name="lagos_cities[<?php echo $city['id']; ?>][delivery_time]" 
                                                                   value="<?php echo htmlspecialchars($city['delivery_time']); ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="submit" name="update_lagos_pricing" class="btn btn-success">
                                        <i class="fas fa-save"></i> Update Lagos Pricing
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- States Pricing -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-map"></i> States Delivery Pricing</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>State</th>
                                                    <th>Price (₦)</th>
                                                    <th>Delivery Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($states as $state): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($state['state_name']); ?></td>
                                                        <td>
                                                            <input type="number" class="form-control" 
                                                                   name="states[<?php echo $state['id']; ?>][price]" 
                                                                   value="<?php echo $state['price']; ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control" 
                                                                   name="states[<?php echo $state['id']; ?>][delivery_time]" 
                                                                   value="<?php echo htmlspecialchars($state['delivery_time']); ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="submit" name="update_state_pricing" class="btn btn-success">
                                        <i class="fas fa-save"></i> Update State Pricing
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
