<?php

namespace App\exceptions;

class ExceptionHandler {
    public static function handle($exception) {
        // 设置响应头
        header('Content-Type: application/json; charset=utf-8');
        
        if ($exception instanceof ApiException) {
            // 处理自定义API异常
            http_response_code($exception->getStatusCode());
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $exception->getData()
            ]);
        } else {
            // 处理其他异常
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error',
                'data' => null
            ]);
            
            // 记录错误日志
            error_log('Unhandled exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        }
        
        exit;
    }
}