<?php
require_once '../config/database.php';

if (!isAdmin()) {
    redirect('login.php');
}

// Add referral_code column to users table if it doesn't exist
try {
    $pdo->query("SELECT referral_code FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(20) UNIQUE");
}

// Add wallet_balance column to users table if it doesn't exist
try {
    $pdo->query("SELECT wallet_balance FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) DEFAULT 0");
}

// Create affiliate_clicks table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS affiliate_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referral_code VARCHAR(20) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Get dashboard statistics
$stats = [];

// Total products
$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
$stats['products'] = $stmt->fetch()['count'];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_verified = 1");
$stats['users'] = $stmt->fetch()['count'];

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
$stats['orders'] = $stmt->fetch()['count'];

// Total revenue
$stmt = $pdo->query("SELECT SUM(total) as revenue FROM orders WHERE status != 'cancelled'");
$stats['revenue'] = $stmt->fetch()['revenue'] ?? 0;

// Pending orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending_verification'");
$stats['pending_orders'] = $stmt->fetch()['count'];

// Pending affiliates
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE affiliate_status = 'pending'");
$stats['pending_affiliates'] = $stmt->fetch()['count'];

// Recent orders
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Top affiliates - simplified query to avoid errors
$stmt = $pdo->prepare("
    SELECT u.name, u.referral_code, u.wallet_balance,
           COUNT(DISTINCT o.id) as total_sales
    FROM users u 
    LEFT JOIN orders o ON u.referral_code = o.affiliate_code
    WHERE u.affiliate_status = 'approved'
    GROUP BY u.id
    ORDER BY u.wallet_balance DESC
    LIMIT 5
");
$stmt->execute();
$top_affiliates = $stmt->fetchAll();

// Monthly sales data for chart - last 6 months only
$stmt = $pdo->query("
    SELECT 
        MONTH(created_at) as month,
        YEAR(created_at) as year,
        COUNT(*) as orders,
        SUM(total) as revenue
    FROM orders 
    WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY year, month
");
$monthly_sales = $stmt->fetchAll();

// Recent activities
$activities = [];

// Recent orders
$stmt = $pdo->prepare("
    SELECT 'order' as type, o.order_number as reference, u.name as user_name, o.created_at
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 3
");
$stmt->execute();
$recent_order_activities = $stmt->fetchAll();

// Recent registrations
$stmt = $pdo->prepare("
    SELECT 'registration' as type, name as user_name, created_at
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute();
$recent_registrations = $stmt->fetchAll();

$activities = array_merge($recent_order_activities, $recent_registrations);
usort($activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$activities = array_slice($activities, 0, 5);

// Helper function for time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'just now';
    }
}

// We'll use the formatPrice function from database.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Melodiva Skincare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="dashboard.php" class="nav-link active">
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
                    <?php if ($stats['pending_orders'] > 0): ?>
                        <span class="badge bg-warning ms-auto"><?php echo $stats['pending_orders']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="affiliates.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Affiliates</span>
                    <?php if ($stats['pending_affiliates'] > 0): ?>
                        <span class="badge bg-info ms-auto"><?php echo $stats['pending_affiliates']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="customers.php" class="nav-link">
                    <i class="fas fa-user-friends"></i>
                    <span>Customers</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link">
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
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <!-- Hamburger Menu -->
                    <button class="hamburger" id="hamburgerBtn">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="ms-3">
                        <h4 class="mb-0 fw-bold">Dashboard Overview</h4>
                        <small class="text-muted">Welcome back, Admin</small>
                    </div>
                </div>
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

        <!-- Content Area -->
        <div class="content-area">
            <!-- Alerts -->
            <?php if ($stats['pending_orders'] > 0 || $stats['pending_affiliates'] > 0): ?>
                <div class="alert alert-modern alert-warning">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">Attention Required</h5>
                            <div class="d-flex gap-4">
                                <?php if ($stats['pending_orders'] > 0): ?>
                                    <span><i class="fas fa-shopping-cart"></i> <?php echo $stats['pending_orders']; ?> orders pending verification</span>
                                <?php endif; ?>
                                <?php if ($stats['pending_affiliates'] > 0): ?>
                                    <span><i class="fas fa-users"></i> <?php echo $stats['pending_affiliates']; ?> affiliate applications</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($stats['products']); ?></div>
                        <div class="stats-label">Active Products</div>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-up"></i> 12% from last month
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($stats['users']); ?></div>
                        <div class="stats-label">Total Customers</div>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-up"></i> 8% from last month
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($stats['orders']); ?></div>
                        <div class="stats-label">Total Orders</div>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-up"></i> 15% from last month
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                            <i class="fas fa-naira-sign"></i>
                        </div>
                        <div class="stats-number"><?php echo formatPrice($stats['revenue']); ?></div>
                        <div class="stats-label">Total Revenue</div>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-up"></i> 23% from last month
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content Row -->
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Sales Chart -->
                    <div class="chart-card mb-4">
                        <div class="chart-header">
                            <h5 class="chart-title">Sales Overview</h5>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary active">6 Months</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary">1 Year</button>
                            </div>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="card mb-4">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">Recent Orders</h5>
                                <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        </div>
                        <div class="modern-table">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="orders.php?id=<?php echo $order['id']; ?>" class="fw-bold text-decoration-none">
                                                    #<?php echo $order['order_number']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td><?php echo formatPrice($order['total']); ?></td>
                                            <td>
                                                <?php 
                                                    $status_class = '';
                                                    switch ($order['status']) {
                                                        case 'pending_verification':
                                                            $status_class = 'status-pending';
                                                            break;
                                                        case 'processing':
                                                            $status_class = 'status-processing';
                                                            break;
                                                        case 'delivered':
                                                            $status_class = 'status-delivered';
                                                            break;
                                                    }
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo str_replace('_', ' ', $order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <a href="products.php?action=add" class="quick-action-btn">
                                        <i class="fas fa-plus-circle quick-action-icon"></i>
                                        <span>Add Product</span>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="orders.php?status=pending_verification" class="quick-action-btn">
                                        <i class="fas fa-clipboard-check quick-action-icon"></i>
                                        <span>Verify Orders</span>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="affiliates.php?status=pending" class="quick-action-btn">
                                        <i class="fas fa-user-check quick-action-icon"></i>
                                        <span>Approve Affiliates</span>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="settings.php" class="quick-action-btn">
                                        <i class="fas fa-cog quick-action-icon"></i>
                                        <span>Settings</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Affiliates -->
                    <div class="card mb-4">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">Top Affiliates</h5>
                                <a href="affiliates.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($top_affiliates as $index => $affiliate): ?>
                                <div class="d-flex align-items-center p-3 border-bottom">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-circle" style="background-color: <?php echo ['#4facfe', '#43e97b', '#f093fb', '#667eea', '#ff9a9e'][$index % 5]; ?>">
                                            <?php echo strtoupper(substr($affiliate['name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($affiliate['name']); ?></h6>
                                        <small class="text-muted">Code: <?php echo htmlspecialchars($affiliate['referral_code']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?php echo formatPrice($affiliate['wallet_balance']); ?></div>
                                        <small class="text-muted"><?php echo $affiliate['total_sales']; ?> sales</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Recent Activities</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <?php if ($activity['type'] == 'order'): ?>
                                        <div class="activity-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">New order #<?php echo $activity['reference']; ?></div>
                                            <div class="activity-subtitle">by <?php echo htmlspecialchars($activity['user_name']); ?></div>
                                            <div class="activity-time"><?php echo timeAgo($activity['created_at']); ?></div>
                                        </div>
                                    <?php elseif ($activity['type'] == 'registration'): ?>
                                        <div class="activity-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">New user registered</div>
                                            <div class="activity-subtitle"><?php echo htmlspecialchars($activity['user_name']); ?></div>
                                            <div class="activity-time"><?php echo timeAgo($activity['created_at']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
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

        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Extract data from PHP
        const months = <?php 
            $labels = [];
            $revenue = [];
            $orders = [];
            
            foreach ($monthly_sales as $data) {
                $month_name = date('M', mktime(0, 0, 0, $data['month'], 1));
                $labels[] = $month_name;
                $revenue[] = $data['revenue'];
                $orders[] = $data['orders'];
            }
            
            echo json_encode($labels); 
        ?>;
        
        const revenueData = <?php echo json_encode($revenue); ?>;
        const ordersData = <?php echo json_encode($orders); ?>;
        
        const salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Revenue (₦)',
                        data: revenueData,
                        backgroundColor: 'rgba(76, 175, 80, 0.6)',
                        borderColor: '#4caf50',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: ordersData,
                        type: 'line',
                        backgroundColor: 'rgba(33, 150, 243, 0.2)',
                        borderColor: '#2196f3',
                        borderWidth: 2,
                        pointBackgroundColor: '#2196f3',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₦)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y') {
                                    label += '₦' + context.raw.toLocaleString();
                                } else {
                                    label += context.raw;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
