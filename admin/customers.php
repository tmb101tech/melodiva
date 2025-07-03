<?php
require_once '../config/database.php';

// Check if admin is logged in
if (!isAdmin()) {
    redirect('login.php');
}

// Get all customers
try {
    $stmt = $pdo->query("
        SELECT u.*, 
               COUNT(DISTINCT o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent,
               MAX(o.created_at) as last_order_date
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $customers = $stmt->fetchAll();
} catch(PDOException $e) {
    $error_message = "Error fetching customers: " . $e->getMessage();
    $customers = [];
}

// Calculate stats
$total_customers = count($customers);
$active_customers = count(array_filter($customers, function($c) { return $c['total_orders'] > 0; }));
$affiliate_customers = count(array_filter($customers, function($c) { return !empty($c['affiliate_status']); }));
$total_customer_value = array_sum(array_column($customers, 'total_spent'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Melodiva Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                Melodiva Admin
            </a>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="products.php" class="nav-link">
                    <i class="fas fa-box"></i>
                    Products
                </a>
            </div>
            <div class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    Orders
                </a>
            </div>
            <div class="nav-item">
                <a href="customers.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    Customers
                </a>
            </div>
            <div class="nav-item">
                <a href="affiliates.php" class="nav-link">
                    <i class="fas fa-user-friends"></i>
                    Affiliates
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Settings
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
        <div class="top-navbar">
            <!-- Hamburger Menu -->
            <button class="hamburger" id="hamburgerBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="ms-3">
                <h4 class="mb-0"><i class="fas fa-users"></i> Customer Management</h4>
                <div class="d-flex align-items-center gap-3">
                    <a href="../index.php" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-external-link-alt"></i> View Site
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #2e7d32, #4caf50);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-number"><?php echo $total_customers; ?></div>
                        <div class="stats-label">Total Customers</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stats-number"><?php echo $active_customers; ?></div>
                        <div class="stats-label">Active Customers</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stats-number"><?php echo $affiliate_customers; ?></div>
                        <div class="stats-label">Affiliates</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #6f42c1, #e83e8c);">
                            <i class="fas fa-naira-sign"></i>
                        </div>
                        <div class="stats-number">₦<?php echo number_format($total_customer_value, 0); ?></div>
                        <div class="stats-label">Total Customer Value</div>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        All Customers
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Orders</th>
                                    <th>Total Spent</th>
                                    <th>Affiliate Status</th>
                                    <th>Last Order</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No customers found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td><?php echo $customer['id']; ?></td>
                                            <td>
                                                <div class="avatar-circle me-2" style="background: linear-gradient(135deg, #2e7d32, #4caf50); width: 32px; height: 32px; font-size: 0.8rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                                        <?php 
                                                        $name = $customer['full_name'] ?? 'U';
                                                        echo strtoupper(substr($name, 0, 1)); 
                                                        ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($name); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo $customer['total_orders']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong>₦<?php echo number_format($customer['total_spent'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($customer['affiliate_status'])): ?>
                                                    <?php
                                                    $status = $customer['affiliate_status'];
                                                    $badge_class = '';
                                                    switch($status) {
                                                        case 'approved':
                                                            $badge_class = 'status-delivered';
                                                            break;
                                                        case 'pending':
                                                            $badge_class = 'status-pending';
                                                            break;
                                                        case 'rejected':
                                                            $badge_class = 'bg-danger text-white';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="status-badge <?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Regular</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($customer['last_order_date']): ?>
                                                    <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
