<?php
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'apply_code':
            applyAffiliateCode($pdo, $input);
            break;
        case 'remove_code':
            removeAffiliateCode();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Affiliate API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
}

function applyAffiliateCode($pdo, $input) {
    $code = trim($input['code']);
    $user_id = $_SESSION['user_id'];
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Please enter an affiliate code']);
        return;
    }
    
    try {
        // First, ensure the users table has the required columns
        $required_columns = [
            'referral_code' => 'VARCHAR(20)',
            'affiliate_status' => 'VARCHAR(20) DEFAULT "pending"'
        ];

        foreach ($required_columns as $column => $definition) {
            try {
                // Check if column exists
                $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
                $stmt->execute([$column]);
                
                if ($stmt->rowCount() == 0) {
                    // Column doesn't exist, add it
                    $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
                    error_log("Added column $column to users table");
                }
            } catch (PDOException $e) {
                error_log("Error adding column $column: " . $e->getMessage());
            }
        }
        
        // Check if affiliate code exists and is approved
        $stmt = $pdo->prepare("
            SELECT id, name, referral_code, affiliate_status 
            FROM users 
            WHERE referral_code = ? AND affiliate_status = 'approved'
        ");
        $stmt->execute([$code]);
        $affiliate = $stmt->fetch();
        
        if (!$affiliate) {
            echo json_encode(['success' => false, 'message' => 'Invalid or inactive affiliate code']);
            return;
        }
        
        // Create affiliate_clicks table if it doesn't exist
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS affiliate_clicks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    referral_code VARCHAR(20) NOT NULL,
                    user_id INT,
                    ip_address VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_referral_code (referral_code)
                )
            ");
        } catch (PDOException $e) {
            error_log("Error creating affiliate_clicks table: " . $e->getMessage());
        }
        
        // Record the click for commission tracking
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_clicks (referral_code, user_id, ip_address) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$code, $user_id, $_SERVER['REMOTE_ADDR']]);
        
        // Apply the affiliate code to session
        $_SESSION['affiliate_code'] = $code;
        $_SESSION['affiliate_discount'] = 0.05; // 5% discount
        
        echo json_encode([
            'success' => true, 
            'message' => 'Affiliate code applied successfully! 5% discount added.',
            'affiliate_name' => $affiliate['name']
        ]);
        
    } catch (PDOException $e) {
        error_log("Affiliate code error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function removeAffiliateCode() {
    unset($_SESSION['affiliate_code']);
    unset($_SESSION['affiliate_discount']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Affiliate code removed successfully'
    ]);
}
?>
