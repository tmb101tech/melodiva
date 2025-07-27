<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addToCart($pdo, $input);
        break;
    case 'update':
        updateCart($pdo, $input);
        break;
    case 'remove':
        removeFromCart($pdo, $input);
        break;
    case 'count':
        getCartCount($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function addToCart($pdo, $input) {
    $product_id = $input['product_id'];
    $quantity = $input['quantity'] ?? 1;
    $user_id = $_SESSION['user_id'];
    
    try {
        // Check if product exists and has stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        
        if ($product['stock'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
            return;
        }
        
        // Check if item already in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update quantity
            $new_quantity = $existing['quantity'] + $quantity;
            if ($new_quantity > $product['stock']) {
                echo json_encode(['success' => false, 'message' => 'Cannot add more items than available stock']);
                return;
            }
            
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $existing['id']]);
        } else {
            // Add new item
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $product_id, $quantity]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Product added to cart']);
        
    } catch (PDOException $e) {
        error_log("Cart error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function updateCart($pdo, $input) {
    $cart_id = $input['cart_id'] ?? $input['item_id'];
    $quantity = $input['quantity'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // Verify cart item belongs to user and get product info
        $stmt = $pdo->prepare("
            SELECT c.*, p.stock 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cart_id, $user_id]);
        $cart_item = $stmt->fetch();
        
        if (!$cart_item) {
            echo json_encode(['success' => false, 'message' => 'Cart item not found']);
            return;
        }
        
        if ($quantity > $cart_item['stock']) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$quantity, $cart_id]);
        
        echo json_encode(['success' => true, 'message' => 'Cart updated']);
        
    } catch (PDOException $e) {
        error_log("Cart update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function removeFromCart($pdo, $input) {
    $cart_id = $input['cart_id'] ?? $input['item_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
        
    } catch (PDOException $e) {
        error_log("Cart remove error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getCartCount($pdo) {
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        echo json_encode(['count' => $result['count'] ?? 0]);
        
    } catch (PDOException $e) {
        echo json_encode(['count' => 0]);
    }
}
?>
