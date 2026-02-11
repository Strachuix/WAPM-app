<?php
/**
 * Router for PHP built-in server
 * Handles routing for Railway deployment
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Backend API routing
if (preg_match('#^/backend/(.+\.php)$#', $uri, $matches)) {
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/backend/' . $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/backend/' . $matches[1];
    require $_SERVER['SCRIPT_FILENAME'];
    return true;
}

// Frontend assets routing
if (preg_match('#^/frontend/assets/(.+)$#', $uri, $matches)) {
    $file = __DIR__ . '/frontend/assets/' . $matches[1];
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimeTypes = [
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
        ];
        
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        
        readfile($file);
        return true;
    }
}

// Service Worker
if ($uri === '/service-worker.js') {
    $file = __DIR__ . '/frontend/service-worker.js';
    if (file_exists($file)) {
        header('Content-Type: application/javascript');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($file);
        return true;
    }
}

// Frontend routing - serve index.html
$file = __DIR__ . '/frontend' . $uri;

// If it's a file and exists, serve it
if (is_file($file)) {
    return false; // Let PHP built-in server handle it
}

// Otherwise serve index.html (SPA routing)
if (file_exists(__DIR__ . '/frontend/index.html')) {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/frontend/index.html');
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
return true;
