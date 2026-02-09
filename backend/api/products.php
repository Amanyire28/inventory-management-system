<?php
/**
 * TOPINV - Products API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../classes/Product.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

try {
    $conn = Database::getInstance()->getConnection();
    $product = new Product($conn);
    
    switch ($action) {
        case 'list':
            handleList($product);
            break;
            
        case 'get':
            handleGet($product, $id);
            break;
            
        case 'create':
            handleCreate($product);
            break;
            
        case 'update':
            handleUpdate($product, $id);
            break;
            
        case 'deactivate':
            handleDeactivate($product, $id);
            break;
            
        case 'low_stock':
            handleLowStock($product);
            break;
            
        case 'out_of_stock':
            handleOutOfStock($product);
            break;
            
        case 'search':
            handleSearch($product);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleList($product) {
    $active_only = $_GET['active_only'] ?? true;
    $products = $product->getAll($active_only);
    
    echo json_encode([
        'success' => true,
        'count' => count($products),
        'data' => $products
    ]);
}

function handleGet($product, $id) {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    $result = $product->getById($id);
    
    if (!$result) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function handleCreate($product) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'selling_price', 'cost_price'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' required"]);
            return;
        }
    }
    
    if ($product->create(
        $data['name'],
        $data['selling_price'],
        $data['cost_price'],
        $data['opening_stock'] ?? 0,
        $data['reorder_level'] ?? 50,
        $data['category'] ?? null
    )) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Product created']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to create product']);
    }
}

function handleUpdate($product, $id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($product->update(
        $id,
        $data['name'] ?? null,
        $data['selling_price'] ?? null,
        $data['cost_price'] ?? null,
        $data['reorder_level'] ?? null,
        $data['category'] ?? null
    )) {
        echo json_encode(['success' => true, 'message' => 'Product updated']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to update product']);
    }
}

function handleDeactivate($product, $id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    if ($product->deactivate($id)) {
        echo json_encode(['success' => true, 'message' => 'Product deactivated']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to deactivate product']);
    }
}

function handleLowStock($product) {
    $products = $product->getLowStock();
    
    echo json_encode([
        'success' => true,
        'count' => count($products),
        'data' => $products
    ]);
}

function handleOutOfStock($product) {
    $products = $product->getOutOfStock();
    
    echo json_encode([
        'success' => true,
        'count' => count($products),
        'data' => $products
    ]);
}

function handleSearch($product) {
    $searchTerm = $_GET['q'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $active_only = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
    
    if (empty($searchTerm)) {
        echo json_encode([
            'success' => true,
            'count' => 0,
            'data' => []
        ]);
        return;
    }
    
    $products = $product->search($searchTerm, $limit, $active_only);
    
    echo json_encode([
        'success' => true,
        'count' => count($products),
        'data' => $products
    ]);
}
?>
