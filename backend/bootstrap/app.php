<?php

/**
 * Bootstrap the application
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");

// Load configuration
$config = require __DIR__ . '/../config/database.php';

// Database connection
try {
    $GLOBALS['pdo'] = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Database connection failed']);
    exit;
}

// Helper functions
function db() {
    return $GLOBALS['pdo'];
}

function auth() {
    return $GLOBALS['auth_user'] ?? null;
}

function request() {
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $data = $raw ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];
        $data = array_merge($_GET, $data);
    }
    return $data;
}

function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function validate($rules) {
    $data = request();
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $ruleList = explode('|', $rule);
        foreach ($ruleList as $r) {
            if ($r === 'required' && empty($data[$field])) {
                $errors[$field] = "$field is required";
            }
            if ($r === 'email' && !empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "$field must be a valid email";
            }
            if (strpos($r, 'min:') === 0) {
                $min = (int) substr($r, 4);
                if (!empty($data[$field]) && strlen($data[$field]) < $min) {
                    $errors[$field] = "$field must be at least $min characters";
                }
            }
        }
    }
    
    if (!empty($errors)) {
        response(['error' => true, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
    
    return $data;
}

function authenticate() {
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    if (!$token) return null;
    
    $stmt = db()->prepare('
        SELECT t.*, u.id as user_id, u.first_name, u.last_name, u.email, u.role, u.avatar_url
        FROM personal_access_tokens t
        JOIN users u ON u.id = t.tokenable_id
        WHERE t.token = :token AND (t.expires_at IS NULL OR t.expires_at > NOW())
    ');
    $stmt->execute(['token' => hash('sha256', $token)]);
    $row = $stmt->fetch();
    
    if (!$row) return null;
    
    // Update last_used_at
    $update = db()->prepare('UPDATE personal_access_tokens SET last_used_at = NOW() WHERE id = :id');
    $update->execute(['id' => $row['id']]);
    
    return $row;
}

function generateToken() {
    return bin2hex(random_bytes(32));
}
