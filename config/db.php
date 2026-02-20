<?php
$DB = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'dbname' => getenv('DB_NAME') ?: 'hademo',
    'user' => getenv('DB_USER') ?: 'hademo',
    'pass' => getenv('DB_PASS') ?: 'hademo',
    'charset' => 'utf8mb4',
];

function getPDO()
{
    global $DB;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $DB['host'], $DB['dbname'], $DB['charset']);
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, $DB['user'], $DB['pass'], $opts);
    } catch (PDOException $e) {
        $msg = 'Database connection failed. Check DB_HOST/DB_NAME/DB_USER/DB_PASS in environment or edit config/db.php.';
        error_log('PDO connection error: ' . $e->getMessage());
        throw new PDOException($msg, (int)$e->getCode());
    }
}
