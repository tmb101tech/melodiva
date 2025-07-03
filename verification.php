// verification.php
require_once '../config/database.php';

$stmt = $pdo->query("
    SELECT u.id, u.name, u.referral_code, 
           SUM(ac.commission_amount) as total_commissions,
           COUNT(ac.id) as commission_count
    FROM users u
    LEFT JOIN affiliate_commissions ac ON u.id = ac.affiliate_id
    WHERE u.affiliate_status = 'approved'
    GROUP BY u.id
");
$results = $stmt->fetchAll();

echo "<pre>";
print_r($results);
echo "</pre>";