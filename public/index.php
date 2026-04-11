<?php

// 前端控制器 - 主入口文件

// 定义应用根目录
define('APP_ROOT', dirname(__DIR__));

// 加载配置文件
require_once APP_ROOT . '/config/db.php';
$config = require_once APP_ROOT . '/config/preference.php';

// 全局配置
define('CONFIG', $config);

// 加载Composer自动加载
require_once APP_ROOT . '/vendor/autoload.php';

// 注册全局异常处理
use App\exceptions\ExceptionHandler;
set_exception_handler([ExceptionHandler::class, 'handle']);

// 处理请求
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// 移除查询字符串
$uri = explode('?', $uri)[0];

// 移除public前缀
$basePath = '/public';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// 加载配置文件
require_once APP_ROOT . '/config/preference.php';
$preference = include APP_ROOT . '/config/preference.php';

// 路由配置
$routes = [
    // 根路径重定向到前端
    'GET /' => function() use ($preference) {
        header('Location: ' . $preference['frontend']['url']);
        exit;
    },
    
    // 认证相关
    'POST /login' => 'controllers/AuthController@login',
    'POST /register' => 'controllers/AuthController@register',
    'GET /logout' => 'controllers/AuthController@logout',
    'GET /user' => 'controllers/UserController@getUser',
    
    // 邮件验证
    'POST /email-verification' => 'controllers/EmailVerificationController@handle',
    
    // TOTP生成
    'GET /totpgen' => 'controllers/TOTPController@generate',
];

// 匹配路由
$routeKey = strtoupper($method) . ' ' . $uri;

if (isset($routes[$routeKey])) {
    $handler = $routes[$routeKey];
    
    if (is_callable($handler)) {
        // 处理闭包函数
        call_user_func($handler);
    } else {
        // 解析控制器和方法
        list($controllerPath, $method) = explode('@', $handler);
        $controllerClass = 'App\\' . str_replace('/', '\\', $controllerPath);
        
        if (class_exists($controllerClass) && method_exists($controllerClass, $method)) {
            // 实例化控制器并调用方法
            $controller = new $controllerClass();
            $controller->$method();
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Controller or method not found']);
        }
    }
} else {
    // 检查是否是zggdrasilapi请求
    if (strpos($uri, '/zggdrasilapi') === 0) {
        require_once APP_ROOT . '/zggdrasilapi/index.php';
        exit;
    }
    
    // 404
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Route not found']);
}
