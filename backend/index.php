<?php

/**
 * Book Cafe API - Laravel-style Entry Point
 * 
 * This is a simplified Laravel-like router for shared hosting (Hostinger).
 * Routes requests to appropriate controllers.
 */

// FORCE CORS HEADERS IMMEDIATELY
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");

// Handle preflight CORS immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/bootstrap/app.php';

// Get the request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$path = parse_url($requestUri, PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME']; // e.g., /backend/index.php or /index.php
$baseDir = dirname($scriptName);       // e.g., /backend or /

// Normalize baseDir (ensure it doesn't end with slash unless it's just /)
if ($baseDir !== '/' && substr($baseDir, -1) === '/') {
    $baseDir = rtrim($baseDir, '/');
}

// Remove baseDir from path to get the route
if (strpos($path, $baseDir) === 0) {
    $path = substr($path, strlen($baseDir));
}
$path = rtrim($path, '/');
// If path is empty, make it / (though our API routes usually start with /api)
if ($path === '') {
    $path = '/';
}

// Handle preflight CORS
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple router
$routes = require __DIR__ . '/routes/api.php';

// Find matching route
$matched = false;
foreach ($routes as $route) {
    $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route['path']);
    $pattern = '#^' . $pattern . '$#';
    
    if ($route['method'] === $requestMethod && preg_match($pattern, $path, $matches)) {
        array_shift($matches); // Remove full match
        
        // Check if route requires auth
        if (!empty($route['middleware']) && in_array('auth', $route['middleware'])) {
            $user = authenticate();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => true, 'message' => 'Unauthorized']);
                exit;
            }
            // Store authenticated user globally
            $GLOBALS['auth_user'] = $user;
            
            // Check role middleware
            foreach ($route['middleware'] as $mw) {
                if (strpos($mw, 'role:') === 0) {
                    $allowedRoles = explode(',', substr($mw, 5));
                    if (!in_array($user['role'], $allowedRoles)) {
                        http_response_code(403);
                        echo json_encode(['error' => true, 'message' => 'Forbidden']);
                        exit;
                    }
                }
            }
        }
        
        // Call controller method
        list($controller, $method) = explode('@', $route['action']);
        $controllerFile = __DIR__ . '/app/Http/Controllers/' . $controller . '.php';
        
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controllerInstance = new $controller();
            call_user_func_array([$controllerInstance, $method], $matches);
            $matched = true;
        }
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode(['error' => true, 'message' => 'Route not found', 'path' => $path]);
}
