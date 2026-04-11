<?php

// Meta endpoint
// GET /

$config = require __DIR__ . '/../../config/zggdrasilapi.php';

// Prepare response
$response = [
    'meta' => [
        'serverName' => $config['server']['name'],
        'implementationName' => $config['server']['implementation'],
        'implementationVersion' => $config['server']['version'],
        'links' => $config['server']['links']
    ],
    'skinDomains' => $config['server']['skin_domains'],
    'signaturePublickey' => $config['server']['signature_public_key'],
    'featureFlags' => $config['feature_flags']
];

sendJsonResponse($response);
