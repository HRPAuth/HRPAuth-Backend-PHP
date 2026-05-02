<?php
header('Content-Type: text/plain; charset=utf-8');

// Get secret from GET
if (!isset($_GET['secret']) || empty($_GET['secret'])) {
    http_response_code(400);
    echo "Missing secret";
    exit;
}

$secret = $_GET['secret'];

/**
 * Generate TOTP
 * @param string $secret
 * @param int $digits
 * @param int $period
 * @return string
 */
function generate_totp($secret, $digits = 6, $period = 30)
{
    // Time counter
    $counter = floor(time() / $period);

    // Convert counter to 8-byte binary string
    $binary_counter = pack('N*', 0) . pack('N*', $counter);

    // Create HMAC-SHA1 hash
    $hash = hash_hmac('sha1', $binary_counter, $secret, true);

    // Dynamic truncation
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated_hash = substr($hash, $offset, 4);

    $value = unpack('N', $truncated_hash)[1];
    $value = $value & 0x7FFFFFFF;

    // Generate final code
    $modulo = pow(10, $digits);
    $otp = $value % $modulo;

    return str_pad($otp, $digits, '0', STR_PAD_LEFT);
}

echo generate_totp($secret);
