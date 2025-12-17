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

$laravelPublicIndex = __DIR__ . '/laravel/public/index.php';
if (!file_exists($laravelPublicIndex)) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Laravel backend is not installed']);
    exit;
}

require $laravelPublicIndex;
