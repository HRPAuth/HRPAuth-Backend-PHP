<?php

namespace App\controllers;

class KeyGenController {
    
    public function generate() {
        $preferenceFile = APP_ROOT . '/config/preference.php';
        
        if (!file_exists($preferenceFile)) {
            $this->sendResponse(500, false, 'Config file not found');
            return;
        }
        
        $preference = include $preferenceFile;
        
        if ($preference['keygen']['enable'] == 1) {
            $this->sendResponse(403, false, 'Key generation endpoint is disabled');
            return;
        }
        
        $keys = $this->generateKeyPair(2048);
        
        if (!$keys) {
            $this->sendResponse(500, false, 'Failed to generate key pair');
            return;
        }
        
        $saveSuccess = $this->saveKeys($keys);
        
        if ($saveSuccess) {
            $this->disableEndpoint($preferenceFile);
            $this->sendResponse(200, true, 'Key pair generated successfully', $keys);
        } else {
            $this->sendResponse(500, false, 'Failed to save keys');
        }
    }
    
    private function generateKeyPair($bits = 2048) {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        
        if (!$res) {
            error_log("Key generation error: " . openssl_error_string());
            return false;
        }

        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res)['key'];

        return [
            'private' => $privateKey,
            'public' => $publicKey,
        ];
    }
    
    private function saveKeys($keys) {
        $keysDir = APP_ROOT . '/keys';
        
        if (!file_exists($keysDir)) {
            mkdir($keysDir, 0700, true);
        }
        
        $publicSaved = file_put_contents($keysDir . '/public.pem', $keys['public']);
        $privateSaved = file_put_contents($keysDir . '/private.pem', $keys['private']);
        
        if ($publicSaved && $privateSaved) {
            chmod($keysDir . '/public.pem', 0600);
            chmod($keysDir . '/private.pem', 0600);
            return true;
        }
        
        return false;
    }
    
    private function disableEndpoint($configFile) {
        $content = file_get_contents($configFile);
        $content = str_replace("'enable' => 0", "'enable' => 1", $content);
        file_put_contents($configFile, $content);
    }
    
    private function sendResponse($statusCode, $success, $message, $data = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $success,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = [
                'public_key' => $data['public'],
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
?>