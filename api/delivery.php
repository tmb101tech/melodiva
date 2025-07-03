<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $state = $input['state'] ?? '';
    $city = $input['city'] ?? '';
    
    try {
        if (strtolower($state) === 'lagos') {
            // Get Lagos city pricing
            $stmt = $pdo->prepare("SELECT price, delivery_time FROM lagos_delivery_prices WHERE city_name = ?");
            $stmt->execute([$city]);
            $result = $stmt->fetch();
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'price' => (float)$result['price'],
                    'delivery_time' => $result['delivery_time'],
                    'location_type' => 'lagos_city'
                ]);
            } else {
                // Default Lagos pricing if city not found
                echo json_encode([
                    'success' => true,
                    'price' => 2500,
                    'delivery_time' => 'Within 48 hours',
                    'location_type' => 'lagos_default'
                ]);
            }
        } else {
            // Get state pricing
            $stmt = $pdo->prepare("SELECT price, delivery_time FROM state_delivery_prices WHERE state_name = ?");
            $stmt->execute([$state]);
            $result = $stmt->fetch();
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'price' => (float)$result['price'],
                    'delivery_time' => $result['delivery_time'],
                    'location_type' => 'state'
                ]);
            } else {
                // Default pricing if state not found
                echo json_encode([
                    'success' => true,
                    'price' => 4000,
                    'delivery_time' => '2-3 business days',
                    'location_type' => 'default'
                ]);
            }
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching delivery pricing'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
