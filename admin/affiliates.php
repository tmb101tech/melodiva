<?php
require_once '../config/database.php';

if (!isAdmin()) {
    redirect('login.php');
}

// Create tables if they don't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS affiliate_commissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT NOT NULL,
        order_id INT,
        commission_amount DECIMAL(10,2) NOT NULL,
        commission_type VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_at TIMESTAMP NULL,
        paid_at TIMESTAMP NULL,
        INDEX (affiliate_id)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS affiliate_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referral_code VARCHAR(20) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (referral_code)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS affiliate_withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        account_details TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        processed_by INT NULL,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX (user_id),
        INDEX (status)
    )
");

// Add columns if they don't exist
try {
    $pdo->query("SELECT referral_code FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(20) UNIQUE");
}

try {
    $pdo->query("SELECT wallet_balance FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) DEFAULT 0");
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve':
            approveAffiliate($pdo);
            break;
        case 'reject':
            rejectAffiliate($pdo);
            break;
        case 'suspend':
            suspendAffiliate($pdo);
            break;
        case 'add_commission':
            addManualCommission($pdo);
            break;
    }
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE conditions
$where_conditions = ["u.affiliate_status IS NOT NULL"];
$params = [];

if ($status) {
    $where_conditions[] = "u.affiliate_status = ?";
    $params[] = $status;
}

if ($search) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.referral_code LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Main query to get affiliates
$query = "
    SELECT 
        u.*,
        IFNULL(ac.total_commissions, 0) as total_earnings,
        IFNULL(aw.total_withdrawals, 0) as total_withdrawals,
        (IFNULL(ac.total_commissions, 0) - IFNULL(aw.total_withdrawals, 0)) as wallet_balance,
        COUNT(DISTINCT o.id) as total_sales
    FROM users u
    LEFT JOIN (
        SELECT affiliate_id, SUM(commission_amount) as total_commissions
        FROM affiliate_commissions
        WHERE status = 'approved'
        GROUP BY affiliate_id
    ) ac ON u.id = ac.affiliate_id
    LEFT JOIN (
        SELECT user_id, SUM(amount) as total_withdrawals
        FROM affiliate_withdrawals
        WHERE status = 'approved'
        GROUP BY user_id
    ) aw ON u.id = aw.user_id
    LEFT JOIN orders o ON u.referral_code = o.affiliate_code AND o.status != 'cancelled'
    $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$affiliates = $stmt->fetchAll();

// Get statistics
$stats = [];
$stmt = $pdo->query("SELECT affiliate_status, COUNT(*) as count FROM users WHERE affiliate_status IS NOT NULL GROUP BY affiliate_status");
$status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stats['total'] = array_sum($status_counts);
$stats['pending'] = $status_counts['pending'] ?? 0;
$stats['approved'] = $status_counts['approved'] ?? 0;
$stats['rejected'] = $status_counts['rejected'] ?? 0;
$stats['suspended'] = $status_counts['suspended'] ?? 0;

function approveAffiliate($pdo) {
    $user_id = intval($_POST['user_id']);
    
    try {
        // Generate a unique referral code
        $referral_code = generateReferralCode($pdo);
        
        $stmt = $pdo->prepare("UPDATE users SET affiliate_status = 'approved', referral_code = ? WHERE id = ?");
        $stmt->execute([$referral_code, $user_id]);
        
        $_SESSION['success'] = "Affiliate approved successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error approving affiliate: " . $e->getMessage();
    }
    
    redirect('affiliates.php');
}

function rejectAffiliate($pdo) {
    $user_id = intval($_POST['user_id']);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET affiliate_status = 'rejected' WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = "Affiliate rejected!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error rejecting affiliate: " . $e->getMessage();
    }
    
    redirect('affiliates.php');
}

function suspendAffiliate($pdo) {
    $user_id = intval($_POST['user_id']);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET affiliate_status = 'suspended' WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = "Affiliate suspended!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error suspending affiliate: " . $e->getMessage();
    }
    
    redirect('affiliates.php');
}

function addManualCommission($pdo) {
    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    
    if ($amount <= 0) {
        $_SESSION['error'] = "Invalid commission amount";
        redirect('affiliates.php');
    }
    
    try {
        // Add commission record
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_commissions (user_id, commission_amount, type, description) 
            VALUES (?, ?, 'manual', ?)
        ");
        $stmt->execute([$user_id, $amount, $description]);
        
        // Update wallet balance
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        
        $_SESSION['success'] = "Manual commission added successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding commission: " . $e->getMessage();
    }
    
    redirect('affiliates.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliates - Melodiva Admin</title>
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
                <a href="customers.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Customers
                </a>
            </div>
            <div class="nav-item">
                <a href="affiliates.php" class="nav-link active">
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
                <h4 class="mb-0"><i class="fas fa-user-friends"></i> Affiliate Management</h4>
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
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
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
                        <div class="stats-number"><?php echo $stats['total']; ?></div>
                        <div class="stats-label">Total Affiliates</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['pending']; ?></div>
                        <div class="stats-label">Pending Approval</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['approved']; ?></div>
                        <div class="stats-label">Approved</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #dc3545, #e83e8c);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['rejected'] + $stats['suspended']; ?></div>
                        <div class="stats-label">Rejected/Suspended</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search affiliates..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="affiliates.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Affiliates Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-friends me-2"></i>
                        All Affiliates
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Affiliate</th>
                                    <th>Email</th>
                                    <th>Referral Code</th>
                                    <th>Sales</th>
                                    <th>Earnings</th>
                                    <th>Wallet</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($affiliates)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No affiliates found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($affiliates as $affiliate): ?>
                                        <tr>
                                            <td><?php echo $affiliate['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2" style="background: linear-gradient(135deg, #2e7d32, #4caf50); width: 32px; height: 32px; font-size: 0.8rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                                        <?php 
                                                        $name = $affiliate['name'] ?? 'A';
                                                        echo strtoupper(substr($name, 0, 1)); 
                                                        ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($name); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($affiliate['email']); ?></td>
                                            <td>
                                                <?php if (!empty($affiliate['referral_code'])): ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($affiliate['referral_code']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $affiliate['total_sales']; ?></td>
                                            <td>₦<?php echo number_format($affiliate['total_earnings'], 2); ?></td>
                                            <td>₦<?php echo number_format($affiliate['wallet_balance'] ?? 0, 2); ?></td>
                                            <td>
                                                <?php
                                                $status = $affiliate['affiliate_status'];
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
                                                    case 'suspended':
                                                        $badge_class = 'bg-secondary text-white';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($affiliate['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($affiliate['affiliate_status'] === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="approve">
                                                            <input type="hidden" name="user_id" value="<?php echo $affiliate['id']; ?>">
                                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this affiliate?')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="user_id" value="<?php echo $affiliate['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this affiliate?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($affiliate['affiliate_status'] === 'approved'): ?>
                                                        <button class="btn btn-primary btn-sm" onclick="addCommission(<?php echo $affiliate['id']; ?>, '<?php echo htmlspecialchars($affiliate['name']); ?>')">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="suspend">
                                                            <input type="hidden" name="user_id" value="<?php echo $affiliate['id']; ?>">
                                                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Suspend this affiliate?')">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
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

    <!-- Add Commission Modal -->
    <div class="modal fade" id="commissionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Manual Commission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_commission">
                        <input type="hidden" name="user_id" id="commission_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Affiliate</label>
                            <input type="text" class="form-control" id="commission_user_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Commission Amount (₦)</label>
                            <input type="number" class="form-control" name="amount" id="amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="3" 
                                      placeholder="Reason for manual commission" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Commission</button>
                    </div>
                </form>
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

        function addCommission(userId, userName) {
            document.getElementById('commission_user_id').value = userId;
            document.getElementById('commission_user_name').value = userName;
            document.getElementById('amount').value = '';
            document.getElementById('description').value = '';
            new bootstrap.Modal(document.getElementById('commissionModal')).show();
        }
    </script>
</body>
</html>