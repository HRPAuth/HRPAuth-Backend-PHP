<?php

namespace App\exceptions;

class ExceptionHandler {
    public static function handle($exception) {
        // Set response headers
        header('Content-Type: application/json; charset=utf-8');
        
        if ($exception instanceof ApiException) {
            // Handle custom API exceptions
            http_response_code($exception->getStatusCode());
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $exception->getData()
            ]);
        } else {
            // Handle other exceptions
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error',
                'data' => null
            ]);
            
            // Log error
            error_log('Unhandled exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        }
        
        exit;
    }
}