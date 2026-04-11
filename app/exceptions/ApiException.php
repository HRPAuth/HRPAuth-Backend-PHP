<?php

namespace App\exceptions;

class ApiException extends \Exception {
    private $statusCode;
    private $data;
    
    public function __construct($message, $statusCode = 500, $data = null) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->data = $data;
    }
    
    public function getStatusCode() {
        return $this->statusCode;
    }
    
    public function getData() {
        return $this->data;
    }
}