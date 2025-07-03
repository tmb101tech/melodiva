<?php
require_once '../config/database.php';

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
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

function applyAffiliateCode($pdo, $input) {
    $code = trim($input['code']);
    $user_id = $_SESSION['user_id'];
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Please enter an affiliate code']);
        return;
    }
    
    try {
        // First, ensure the referral_code column exists
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20)");
        
        // Update users without referral codes
        $pdo->exec("UPDATE users SET referral_code = CONCAT('REF', LPAD(id, 6, '0')) WHERE referral_code IS NULL OR referral_code = ''");
        
        // Check if the affiliate code exists and is valid
        $stmt = $pdo->prepare("
            SELECT id, name, affiliate_status, referral_code 
            FROM users 
            WHERE referral_code = ? 
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $affiliate = $stmt->fetch();
        
        if (!$affiliate) {
            echo json_encode(['success' => false, 'message' => 'Invalid affiliate code']);
            return;
        }
        
        // Check if affiliate is approved (if affiliate_status column exists)
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'affiliate_status'");
            if ($stmt->rowCount() > 0 && isset($affiliate['affiliate_status']) && $affiliate['affiliate_status'] !== 'approved') {
                echo json_encode(['success' => false, 'message' => 'This affiliate code is not active']);
                return;
            }
        } catch (PDOException $e) {
            // affiliate_status column doesn't exist, continue anyway
        }
        
        // Apply the affiliate code to session
        $_SESSION['affiliate_code'] = $code;
        $_SESSION['affiliate_name'] = $affiliate['name'];
        $_SESSION['affiliate_discount'] = 0.05; // 5% discount
        
        // Log the click (if table exists)
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS affiliate_clicks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    referral_code VARCHAR(20) NOT NULL,
                    user_id INT,
                    ip_address VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $stmt = $pdo->prepare("INSERT INTO affiliate_clicks (referral_code, user_id, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$code, $user_id, $_SERVER['REMOTE_ADDR']]);
        } catch (PDOException $e) {
            // Click logging failed, but continue
            error_log("Failed to log affiliate click: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Affiliate code applied successfully! 5% discount added.',
            'affiliate_name' => $affiliate['name']
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in affiliate code application: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

function removeAffiliateCode() {
    unset($_SESSION['affiliate_code']);
    unset($_SESSION['affiliate_name']);
    unset($_SESSION['affiliate_discount']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Affiliate code removed successfully'
    ]);
}
?>
