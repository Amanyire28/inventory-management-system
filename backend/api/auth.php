<?php
/**
 * TOPINV - Authentication API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../classes/User.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

try {
    $conn = Database::getInstance()->getConnection();
    $user = new User($conn);
    
    switch ($action) {
        case 'login':
            handleLogin($user);
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        case 'register':
            handleRegister($user);
            break;
            
        case 'verify':
            handleVerifyToken();
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleLogin($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        return;
    }
    
    if ($user->authenticate($data['username'], $data['password'])) {
        $token = generateJWT($user->id, $user->role);
        
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
}

function handleLogout() {
    session_start();
    session_unset();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out']);
}

function handleRegister($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['username', 'name', 'password', 'email'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' required"]);
            return;
        }
    }
    
    $role = $data['role'] ?? 'cashier';
    
    if ($user->register($data['username'], $data['email'], $data['name'], $data['password'], $role)) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'User registered']);
    } else {
        http_response_code(409);
        echo json_encode(['error' => 'Registration failed']);
    }
}

function handleVerifyToken() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Token required']);
        return;
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    
    if (verifyJWT($token)) {
        echo json_encode(['success' => true, 'message' => 'Token valid']);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
}

/**
 * Generate JWT token
 */
function generateJWT($userId, $role) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'user_id' => $userId,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60)
    ]);
    
    $base64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64Payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    
    $signature = hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true);
    $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return "$base64Header.$base64Payload.$base64Signature";
}

/**
 * Verify JWT token
 */
function verifyJWT($token) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return false;
    }
    
    list($header, $payload, $signature) = $parts;
    
    $valid_signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)), '+/', '-_'), '=');
    
    if ($signature !== $valid_signature) {
        return false;
    }
    
    $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    
    if ($decoded['exp'] < time()) {
        return false;
    }
    
    return true;
}
?>
