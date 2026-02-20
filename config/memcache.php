<?php
$MEMCACHE = [
    'host' => getenv('MEMCACHE_HOST') ?: '127.0.0.1',
    'port' => getenv('MEMCACHE_PORT') ?: 11211,
    'prefix' => getenv('MEMCACHE_PREFIX') ?: 'hrpauth_',
    'code_ttl' => 600,
    'storage_dir' => __DIR__ . '/../cache/verification_codes',
];

function getMemcached() {
    static $memcached = null;
    
    if ($memcached === null && class_exists('Memcached')) {
        $memcached = new Memcached();
        $memcached->addServer($GLOBALS['MEMCACHE']['host'], $GLOBALS['MEMCACHE']['port']);
    }
    
    return $memcached;
}

function storeVerificationCode($email, $code) {
    $memcached = getMemcached();
    
    if ($memcached !== null) {
        $key = $GLOBALS['MEMCACHE']['prefix'] . 'verify_' . md5($email);
        $ttl = $GLOBALS['MEMCACHE']['code_ttl'];
        return $memcached->set($key, $code, $ttl);
    }
    
    $storageDir = $GLOBALS['MEMCACHE']['storage_dir'];
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    
    $filename = $storageDir . '/' . md5($email) . '.json';
    $data = [
        'code' => $code,
        'expires_at' => time() + $GLOBALS['MEMCACHE']['code_ttl']
    ];
    
    return file_put_contents($filename, json_encode($data)) !== false;
}

function getVerificationCode($email) {
    $memcached = getMemcached();
    
    if ($memcached !== null) {
        $key = $GLOBALS['MEMCACHE']['prefix'] . 'verify_' . md5($email);
        return $memcached->get($key);
    }
    
    $storageDir = $GLOBALS['MEMCACHE']['storage_dir'];
    $filename = $storageDir . '/' . md5($email) . '.json';
    
    if (!file_exists($filename)) {
        return false;
    }
    
    $data = json_decode(file_get_contents($filename), true);
    
    if ($data['expires_at'] < time()) {
        @unlink($filename);
        return false;
    }
    
    return $data['code'];
}

function deleteVerificationCode($email) {
    $memcached = getMemcached();
    
    if ($memcached !== null) {
        $key = $GLOBALS['MEMCACHE']['prefix'] . 'verify_' . md5($email);
        return $memcached->delete($key);
    }
    
    $storageDir = $GLOBALS['MEMCACHE']['storage_dir'];
    $filename = $storageDir . '/' . md5($email) . '.json';
    
    if (file_exists($filename)) {
        return unlink($filename);
    }
    
    return true;
}
