<?php

// Meta endpoint
// GET /

$config = require __DIR__ . '/../../../config/zggdrasilapi.php';

// Prepare response
$response = [
    'meta' => [
        'serverName' => $config['server']['name'],
        'implementationName' => $config['server']['implementation'],
        'implementationVersion' => $config['server']['version'],
        'links' => $config['server']['links'],
        'feature.non_email_login' => $config['feature_flags']['non_email_login'],
        'feature.legacy_skin_api' => $config['feature_flags']['legacy_skin_api'],
        'feature.no_mojang_namespace' => $config['feature_flags']['no_mojang_namespace'],
        'feature.enable_mojang_anti_features' => $config['feature_flags']['enable_mojang_anti_features'],
        'feature.enable_profile_key' => $config['feature_flags']['enable_profile_key'],
        'feature.username_check' => $config['feature_flags']['username_check']
    ],
    'skinDomains' => $config['server']['skin_domains'],
    'signaturePublickey' => $config['server']['signature_public_key']
];

sendJsonResponse($response);