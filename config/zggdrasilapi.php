<?php

// Configuration for Zggdrasil API Server

$preference = require __DIR__ . '/preference.php';

// Extract domain from URL
if (!function_exists('extract_domain')) {
    function extract_domain($url) {
        $parsed = parse_url($url);
        return $parsed['host'];
    }
}

$frontend_url = $preference['frontend']['url'];
$callback_url = $preference['callback']['url'];
$frontend_domain = extract_domain($frontend_url);
$callback_domain = extract_domain($callback_url);

// Load public key from keys folder
$public_key_path = __DIR__ . '/../keys/public.pem';
$signature_public_key = file_get_contents($public_key_path);

return [
    'server' => [
        'name' => $preference['site']['name'],
        'implementation' => $preference['site']['implementation'],
        'version' => $preference['site']['version'],
        'links' => [
            'homepage' => $frontend_url,
            'register' => rtrim($frontend_url, '/') . '/register'
        ],
        'skin_domains' => [
            $callback_domain,
            '.' . $callback_domain
        ],
        'signature_public_key' => $signature_public_key
    ],
    'security' => [
        'token_expiry_days' => 15,
        'session_expiry_seconds' => 30,
        'password_cost' => 10
    ],
    'feature_flags' => [
        'non_email_login' => true,
        'legacy_skin_api' => true,
        'no_mojang_namespace' => false,
        'enable_mojang_anti_features' => false,
        'enable_profile_key' => false,
        'username_check' => true
    ]
];