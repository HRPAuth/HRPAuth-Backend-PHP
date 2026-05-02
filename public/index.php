<?php

// Front Controller - Main Entry Point

// Define app root directory
define('APP_ROOT', dirname(__DIR__));

// Load configuration
require_once APP_ROOT . '/config/db.php';
$config = require_once APP_ROOT . '/config/preference.php';

// Global config
define('CONFIG', $config);

// Load Composer autoloader
require_once APP_ROOT . '/vendor/autoload.php';

// Register global exception handler
use App\exceptions\ExceptionHandler;
set_exception_handler([ExceptionHandler::class, 'handle']);

// Process request
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string
$uri = explode('?', $uri)[0];

// Remove public prefix
$basePath = '/public';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Load preference configuration
require_once APP_ROOT . '/config/preference.php';
$preference = include APP_ROOT . '/config/preference.php';

// Route configuration
$routes = [
    // Root path logic
    'GET /' => function() use ($preference) {
        $mode = isset($preference['portal']['mode']) ? $preference['portal']['mode'] : 'redirect';
        
        if ($mode === 'metadata') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'online',
                'backend' => [
                    'name' => $preference['site']['name'],
                    'url' => $preference['site']['url'],
                    'version' => $preference['site']['version'] ?? 'unknown',
                    'php_version' => PHP_VERSION,
                    'server_time' => date('Y-m-d H:i:s'),
                ],
                'message' => 'HRPAuth Backend is running.'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        // Default redirect to frontend
        header('Location: ' . $preference['frontend']['url']);
        exit;
    },
    
    // Auth related
    'POST /login' => 'controllers/AuthController@login',
    'POST /register' => 'controllers/AuthController@register',
    'GET /logout' => 'controllers/AuthController@logout',
    'POST /user' => 'controllers/UserController@getUser',
    'GET /test-user' => function() {
        error_log('Testing UserController');
        require_once APP_ROOT . '/app/controllers/UserController.php';
        $controller = new App\controllers\UserController();
        error_log('UserController instantiated');
        $controller->getUser();
    },
    
    // Email verification
    'POST /email-verification' => 'controllers/EmailVerificationController@handle',
    
    // TOTP generation
    'GET /totpgen' => 'controllers/TOTPController@generate',
];

// Match route
$routeKey = strtoupper($method) . ' ' . $uri;

// Debug info
error_log('Request URI: ' . $uri);
error_log('Request Method: ' . $method);
error_log('Route Key: ' . $routeKey);
error_log('Available routes: ' . print_r(array_keys($routes), true));

if (isset($routes[$routeKey])) {
    $handler = $routes[$routeKey];
    
    if (is_callable($handler)) {
        // Handle closure
        call_user_func($handler);
    } else {
        // Parse controller and method
        list($controllerPath, $method) = explode('@', $handler);
        $controllerFile = APP_ROOT . '/app/' . $controllerPath . '.php';
        $controllerClass = 'App\\' . str_replace('/', '\\', $controllerPath);
        
        error_log('Controller File: ' . $controllerFile);
        error_log('Controller Class: ' . $controllerClass);
        error_log('Method: ' . $method);
        
        // Explicitly include the controller file
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            error_log('Controller file included successfully');
        } else {
            error_log('Controller file not found: ' . $controllerFile);
        }
        
        error_log('Class exists: ' . var_export(class_exists($controllerClass), true));
        error_log('Method exists: ' . var_export(method_exists($controllerClass, $method), true));
        
        if (class_exists($controllerClass) && method_exists($controllerClass, $method)) {
            // Instantiate controller and call method
            $controller = new $controllerClass();
            error_log('Controller instantiated successfully');
            $controller->$method();
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Controller or method not found']);
        }
    }
} else {
    // Check if it's a zggdrasilapi request
    error_log('Route not found, passing to zggdrasilapi');
    require_once APP_ROOT . '/modules/zggdrasilapi/index.php';
    exit;
}
