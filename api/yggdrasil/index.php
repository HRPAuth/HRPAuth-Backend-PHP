<?php
// index.php
// Minimal Yggdrasil-like server with strict response shapes for frontend.
// - Uses SQLite at ./data/yggdrasil.db (auto-created).
// - Public key: public_key.pem (optional for meta output).
// - Private key: private_key.pem (optional to sign textures).
// - Files saved to ./data/textures/
// Headers and bodies follow the JSON Schema you provided.

// --------- Basic settings ----------
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// helpers
function jsonResp($data, int $code = 200) {
    http_response_code($code);
    if ($code === 204) {
        // no content
        exit;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResp(string $error, string $errorMessage, int $httpCode = 400, ?string $cause = null) {
    $body = ['error' => $error, 'errorMessage' => $errorMessage];
    if ($cause) $body['cause'] = $cause;
    jsonResp($body, $httpCode);
}

function generateUUID32(): string {
    // 32 hex chars, unsigned UUID (no dashes)
    return bin2hex(random_bytes(16));
}

function nowMillis(): int {
    return (int)floor(microtime(true) * 1000);
}

// --------- DB (SQLite) ----------
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
$dbFile = $dataDir . '/yggdrasil.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// create tables if not exist
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    email TEXT UNIQUE,
    password_hash TEXT,
    properties TEXT DEFAULT '[]'
);
CREATE TABLE IF NOT EXISTS profiles (
    id TEXT PRIMARY KEY,
    user_id TEXT,
    name TEXT,
    model TEXT,
    properties TEXT DEFAULT '[]',
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS tokens (
    accessToken TEXT PRIMARY KEY,
    clientToken TEXT,
    user_id TEXT,
    selectedProfileId TEXT,
    issuedAt INTEGER,
    expiresInDays INTEGER,
    state TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(selectedProfileId) REFERENCES profiles(id)
);
CREATE TABLE IF NOT EXISTS joins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    serverId TEXT,
    ip TEXT,
    timestamp INTEGER
);
");

// create a demo user if not exist (username/email: demo@example.com password: demo123)
$demoId = '00000000000000000000000000000001';
$st = $pdo->prepare("SELECT COUNT(1) as c FROM users WHERE id = :id");
$st->execute([':id'=>$demoId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || (int)$row['c'] === 0) {
    $hash = password_hash('demo123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (id,email,password_hash,properties) VALUES (:id,:email,:ph, :props)");
    $stmt->execute([
        ':id' => $demoId,
        ':email' => 'demo@example.com',
        ':ph' => $hash,
        ':props' => json_encode([['name'=>'preferredLanguage','value'=>'en_US']])
    ]);
    // add a profile
    $pid = '00000000000000000000000000000001';
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO profiles (id,user_id,name,model,properties) VALUES (:id,:uid,:name,:model,:props)");
    $stmt->execute([
        ':id' => $pid,
        ':uid' => $demoId,
        ':name' => 'DemoPlayer',
        ':model' => 'default',
        ':props' => json_encode([])
    ]);
}

// load keys
$publicKey = file_exists(__DIR__.'/public_key.pem') ? file_get_contents(__DIR__.'/public_key.pem') : '';
$privateKey = file_exists(__DIR__.'/private_key.pem') ? file_get_contents(__DIR__.'/private_key.pem') : null;

// --------- Request routing ----------
$rawPath = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = $rawPath ?: '/';
$path = preg_replace('#^/api/yggdrasil#', '', $path); // support prefix if any
$method = $_SERVER['REQUEST_METHOD'];

// Utility: parse JSON body
function getJsonBody() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return $data;
}

// Utility: get Bearer token
function getBearerToken(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['Authorization'] ?? '');
    if (!$h) return null;
    if (stripos($h, 'Bearer ') === 0) return trim(substr($h,7));
    return null;
}

// Utility: get base url for textures
function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

// ---------- Endpoints ----------

// GET /  (meta)
if ($path === '/' && $method === 'GET') {
    $meta = [
        "meta" => [
            "serverName" => "HRPAUTH",
            "implementationName" => "Yggdrasil compatible auth (minimal)",
            "implementationVersion" => "1.0.0",
            "links" => [
                "homepage" => getBaseUrl() . "/",
                "register" => getBaseUrl() . "/register"
            ]
        ],
        "skinDomains" => [
            parse_url(getBaseUrl(), PHP_URL_HOST)
        ],
        "signaturePublickey" => $publicKey ?: ""
    ];
    jsonResp($meta, 200);
}

// POST /authserver/authenticate
if ($path === '/authserver/authenticate' && $method === 'POST') {
    $input = getJsonBody();
    if (!is_array($input)) errorResp('Invalid Request', 'Request body must be JSON', 400);
    if (empty($input['username']) || empty($input['password']) || empty($input['agent'])) {
        errorResp('Invalid Request', 'Required fields: username, password, agent', 400);
    }
    // check agent fields per spec
    if (!isset($input['agent']['name']) || !isset($input['agent']['version'])) {
        errorResp('Invalid Request', 'agent must include name and version', 400);
    }

    // find user by email or username (we treat username as email if contains @)
    $username = $input['username'];
    $password = $input['password'];
    $clientToken = $input['clientToken'] ?? generateUUID32();

    // find user record
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        errorResp('ForbiddenOperation', 'Invalid credentials', 403);
    }
    if (!password_verify($password, $user['password_hash'])) {
        errorResp('ForbiddenOperation', 'Invalid credentials', 403);
    }

    // pick available profiles for this user
    $stmt = $pdo->prepare("SELECT id,name,model,properties FROM profiles WHERE user_id = :uid");
    $stmt->execute([':uid' => $user['id']]);
    $profiles = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $profiles[] = [
            'id' => $r['id'],
            'name' => $r['name'],
            'model' => $r['model'],
            'properties' => json_decode($r['properties'], true) ?: []
        ];
    }
    $selectedProfile = $profiles[0] ?? null;

    // create access token record
    $accessToken = generateUUID32();
    $issuedAt = nowMillis();
    $expiresInDays = 15;
    $state = 'valid';
    $stmt = $pdo->prepare("INSERT INTO tokens (accessToken, clientToken, user_id, selectedProfileId, issuedAt, expiresInDays, state) VALUES (:a,:c,:u,:p,:i,:e,:s)");
    $stmt->execute([
        ':a'=>$accessToken,
        ':c'=>$clientToken,
        ':u'=>$user['id'],
        ':p'=>$selectedProfile ? $selectedProfile['id'] : null,
        ':i'=>$issuedAt,
        ':e'=>$expiresInDays,
        ':s'=>$state
    ]);

    $response = [
        'accessToken' => $accessToken,
        'clientToken' => $clientToken,
        'availableProfiles' => array_map(function($p){ return ['id'=>$p['id'],'name'=>$p['name']]; }, $profiles),
        'selectedProfile' => $selectedProfile ? ['id'=>$selectedProfile['id'],'name'=>$selectedProfile['name']] : null,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'properties' => json_decode($user['properties'], true) ?: []
        ]
    ];
    jsonResp($response, 200);
}

// POST /authserver/validate
if ($path === '/authserver/validate' && $method === 'POST') {
    $input = getJsonBody();
    if (!is_array($input) || empty($input['accessToken'])) {
        errorResp('Invalid Request', 'Required: accessToken', 400);
    }
    $accessToken = $input['accessToken'];
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE accessToken = :a LIMIT 1");
    $stmt->execute([':a'=>$accessToken]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        errorResp('ForbiddenOperation', 'Token not found', 403);
    }
    // Check expiry
    $expiryMs = $t['issuedAt'] + ((int)$t['expiresInDays']) * 86400 * 1000;
    if (nowMillis() > $expiryMs || $t['state'] !== 'valid') {
        errorResp('ForbiddenOperation', 'Token invalid or expired', 403);
    }
    // success -> 204 No Content
    http_response_code(204);
    exit;
}

// POST /authserver/refresh
if ($path === '/authserver/refresh' && $method === 'POST') {
    $input = getJsonBody();
    if (!is_array($input) || empty($input['accessToken'])) {
        errorResp('Invalid Request', 'Required: accessToken', 400);
    }
    $accessToken = $input['accessToken'];
    $clientToken = $input['clientToken'] ?? null;

    // find token
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE accessToken = :a LIMIT 1");
    $stmt->execute([':a'=>$accessToken]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) errorResp('ForbiddenOperation', 'Token not found', 403);
    if ($clientToken && $clientToken !== $t['clientToken']) {
        errorResp('ForbiddenOperation', 'clientToken mismatch', 403);
    }
    if ($t['state'] !== 'valid') errorResp('ForbiddenOperation', 'Token invalid', 403);

    // rotate token
    $newAccess = generateUUID32();
    $issuedAt = nowMillis();
    $stmt = $pdo->prepare("UPDATE tokens SET accessToken = :n, issuedAt = :i WHERE accessToken = :a");
    $stmt->execute([':n'=>$newAccess, ':i'=>$issuedAt, ':a'=>$accessToken]);

    // return new token
    $stmt = $pdo->prepare("SELECT t.*, u.email as user_email, u.id as user_id FROM tokens t JOIN users u ON u.id = t.user_id WHERE t.accessToken = :a LIMIT 1");
    $stmt->execute([':a'=>$newAccess]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $selectedProfile = null;
    if ($r['selectedProfileId']) {
        $s = $pdo->prepare("SELECT id,name,model,properties FROM profiles WHERE id = :id");
        $s->execute([':id'=>$r['selectedProfileId']]);
        $sp = $s->fetch(PDO::FETCH_ASSOC);
        if ($sp) $selectedProfile = ['id'=>$sp['id'],'name'=>$sp['name']];
    }
    $response = [
        'accessToken' => $r['accessToken'],
        'clientToken' => $r['clientToken'],
        'selectedProfile' => $selectedProfile,
        'user' => [
            'id' => $r['user_id'],
            'email' => $r['user_email'],
            'properties' => json_decode($pdo->query("SELECT properties FROM users WHERE id = '".$r['user_id']."'")->fetchColumn(), true) ?: []
        ]
    ];
    jsonResp($response, 200);
}

// POST /authserver/invalidate
if ($path === '/authserver/invalidate' && $method === 'POST') {
    $input = getJsonBody();
    if (!is_array($input) || empty($input['accessToken'])) {
        errorResp('Invalid Request', 'Required: accessToken', 400);
    }
    $accessToken = $input['accessToken'];
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE accessToken = :a LIMIT 1");
    $stmt->execute([':a'=>$accessToken]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        // per spec, returning 204 even if token missing is acceptable; we return 204 to be forgiving
        http_response_code(204);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE tokens SET state='invalid' WHERE accessToken = :a");
    $stmt->execute([':a'=>$accessToken]);
    http_response_code(204);
    exit;
}

// POST /authserver/signout
if ($path === '/authserver/signout' && $method === 'POST') {
    $input = getJsonBody();
    if (!is_array($input) || empty($input['username']) || empty($input['password'])) {
        errorResp('Invalid Request', 'Required: username and password', 400);
    }
    $username = $input['username'];
    $password = $input['password'];
    $stmt = $pdo->prepare("SELECT id,password_hash FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email'=>$username]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        errorResp('ForbiddenOperation', 'Invalid credentials', 403);
    }
    // invalidate all tokens for user
    $stmt = $pdo->prepare("UPDATE tokens SET state='invalid' WHERE user_id = :uid");
    $stmt->execute([':uid'=>$u['id']]);
    http_response_code(204);
    exit;
}

// GET /sessionserver/session/minecraft/profile/{uuid}?unsigned={unsigned}
if (preg_match('#^/sessionserver/session/minecraft/profile/([0-9a-fA-F]{32})$#', $path, $m) && $method === 'GET') {
    $uuid = $m[1];
    $unsigned = isset($_GET['unsigned']) ? $_GET['unsigned'] : 'false';
    // fetch profile
    $stmt = $pdo->prepare("SELECT id,name,model,properties FROM profiles WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$uuid]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) errorResp('Not Found', 'Profile not found', 404);
    $profile = [
        'id' => $p['id'],
        'name' => $p['name'],
        'properties' => []
    ];
    if ($unsigned !== 'true') {
        // we must populate properties if textures exist
        $props = json_decode($p['properties'], true) ?: [];
        // if textures not present, auto-generate textures property pointing to stored files if any
        $texProp = null;
        foreach ($props as $pp) {
            if (isset($pp['name']) && $pp['name']==='textures') { $texProp = $pp; break; }
        }
        if (!$texProp) {
            // try to build textures from saved files
            $base = getBaseUrl();
            $skinFile = $dataDir . '/textures/' . $p['id'] . '_skin.png';
            $capeFile = $dataDir . '/textures/' . $p['id'] . '_cape.png';
            $textures = [];
            if (file_exists($skinFile)) $textures['SKIN'] = ['url' => $base . '/data/textures/' . basename($skinFile)];
            if (file_exists($capeFile)) $textures['CAPE'] = ['url' => $base . '/data/textures/' . basename($capeFile)];
            if (!empty($textures)) {
                $payload = [
                    'timestamp' => nowMillis(),
                    'profileId' => $p['id'],
                    'profileName' => $p['name'],
                    'textures' => $textures
                ];
                $value = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
                $texProp = ['name' => 'textures', 'value' => $value];
                // sign if private key present
                if ($privateKey) {
                    $res = openssl_get_privatekey($privateKey);
                    if ($res !== false) {
                        openssl_sign(json_encode($payload, JSON_UNESCAPED_UNICODE), $sig, $res, OPENSSL_ALGO_SHA1);
                        openssl_free_key($res);
                        $texProp['signature'] = base64_encode($sig);
                    }
                }
            }
        }
        if ($texProp) {
            $profile['properties'][] = $texProp;
        }
        // also include stored properties other than textures
        foreach ($props as $pp) {
            if ($pp['name'] !== 'textures') $profile['properties'][] = $pp;
        }
    }
    jsonResp($profile, 200);
}

// GET /sessionserver/session/minecraft/hasJoined?username={username}&serverId={serverId}&ip={ip}
if ($path === '/sessionserver/session/minecraft/hasJoined' && $method === 'GET') {
    $username = $_GET['username'] ?? '';
    $serverId = $_GET['serverId'] ?? '';
    $ip = $_GET['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $unsigned = $_GET['unsigned'] ?? 'false';
    if (trim($username) === '' || trim($serverId) === '') {
        errorResp('Invalid Request', 'username and serverId required', 400);
    }
    // look up joins table for matching username and serverId within 30s
    $cut = nowMillis() - 30 * 1000;
    $stmt = $pdo->prepare("SELECT * FROM joins WHERE username = :u AND serverId = :s AND timestamp >= :cut ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([':u'=>$username, ':s'=>$serverId, ':cut'=>$cut]);
    $j = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$j) {
        http_response_code(204);
        exit;
    }
    // find profile by username -> assume username maps to profile name
    $stmt = $pdo->prepare("SELECT id,name,model,properties FROM profiles WHERE name = :n LIMIT 1");
    $stmt->execute([':n'=>$username]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        http_response_code(204);
        exit;
    }
    $profile = [
        'id' => $p['id'],
        'name' => $p['name']
    ];
    if ($unsigned !== 'true') {
        // include properties same as profile endpoint
        $props = json_decode($p['properties'], true) ?: [];
        $texProp = null;
        foreach ($props as $pp) if ($pp['name']==='textures') { $texProp = $pp; break; }
        if (!$texProp) {
            $base = getBaseUrl();
            $skinFile = $dataDir . '/textures/' . $p['id'] . '_skin.png';
            $textures = [];
            if (file_exists($skinFile)) $textures['SKIN'] = ['url' => $base . '/data/textures/' . basename($skinFile)];
            if (!empty($textures)) {
                $payload = [
                    'timestamp' => nowMillis(),
                    'profileId' => $p['id'],
                    'profileName' => $p['name'],
                    'textures' => $textures
                ];
                $value = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
                $texProp = ['name'=>'textures','value'=>$value];
                if ($privateKey) {
                    $res = openssl_get_privatekey($privateKey);
                    if ($res !== false) {
                        openssl_sign(json_encode($payload, JSON_UNESCAPED_UNICODE), $sig, $res, OPENSSL_ALGO_SHA1);
                        openssl_free_key($res);
                        $texProp['signature'] = base64_encode($sig);
                    }
                }
            }
        }
        if ($texProp) $profile['properties'] = [$texProp];
    }
    jsonResp($profile, 200);
}

// POST /sessionserver/session/minecraft/join
if ($path === '/sessionserver/session/minecraft/join' && $method === 'POST') {
    $input = getJsonBody();
    if (!is_array($input) || empty($input['accessToken']) || empty($input['selectedProfile']) || empty($input['serverId'])) {
        errorResp('Invalid Request', 'Required: accessToken, selectedProfile, serverId', 400);
    }
    $accessToken = $input['accessToken'];
    $selectedProfile = $input['selectedProfile'];
    $serverId = $input['serverId'];
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE accessToken = :a LIMIT 1");
    $stmt->execute([':a'=>$accessToken]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) errorResp('ForbiddenOperation', 'Token not found', 403);
    if ($t['state'] !== 'valid') errorResp('ForbiddenOperation', 'Token invalid', 403);
    // check selectedProfile id string (client may send object id or string)
    $profileId = is_array($selectedProfile) && isset($selectedProfile['id']) ? $selectedProfile['id'] : (is_string($selectedProfile) ? $selectedProfile : null);
    if (!$profileId || $profileId !== $t['selectedProfileId']) {
        errorResp('ForbiddenOperation', 'selectedProfile mismatch', 403);
    }
    $username = null;
    $stmt = $pdo->prepare("SELECT name FROM profiles WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$profileId]);
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pr) $username = $pr['name'];
    // store join record
    $stmt = $pdo->prepare("INSERT INTO joins (username, serverId, ip, timestamp) VALUES (:u,:s,:ip,:t)");
    $stmt->execute([':u'=>$username ?: '', ':s'=>$serverId, ':ip'=>$_SERVER['REMOTE_ADDR'] ?? '', ':t'=>nowMillis()]);
    http_response_code(204);
    exit;
}

// POST /api/profiles/minecraft  (batch profile query)
if ($path === '/api/profiles/minecraft' && $method === 'POST') {
    $input = getJsonBody();
    if (!is_array($input)) errorResp('Invalid Request', 'Request must be JSON array of names', 400);
    $result = [];
    foreach ($input as $name) {
        if (!is_string($name)) continue;
        $stmt = $pdo->prepare("SELECT id,name FROM profiles WHERE name = :n LIMIT 1");
        $stmt->execute([':n'=>$name]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p) $result[] = ['id'=>$p['id'],'name'=>$p['name']];
    }
    jsonResp($result, 200);
}

// PUT /api/user/profile/{uuid}/{skin|cape}
if (preg_match('#^/api/user/profile/([0-9a-fA-F]{32})/(skin|cape)$#', $path, $m) && $method === 'PUT') {
    $uuid = $m[1];
    $type = $m[2]; // skin or cape
    // authentication
    $token = getBearerToken();
    if (!$token) errorResp('Unauthorized', 'Missing Authorization header', 401);
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE accessToken = :a LIMIT 1");
    $stmt->execute([':a'=>$token]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t || $t['state'] !== 'valid') errorResp('ForbiddenOperation', 'Invalid token', 403);
    // check ownership: selectedProfileId must match uuid or user must own profile
    $owns = false;
    if ($t['selectedProfileId'] === $uuid) $owns = true;
    else {
        $st = $pdo->prepare("SELECT user_id FROM profiles WHERE id = :id LIMIT 1");
        $st->execute([':id'=>$uuid]);
        $pp = $st->fetch(PDO::FETCH_ASSOC);
        if ($pp && $pp['user_id'] === $t['user_id']) $owns = true;
    }
    if (!$owns) errorResp('ForbiddenOperation', 'Not allowed to modify this profile', 403);

    // handle uploaded file (multipart/form-data)
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        errorResp('Invalid Request', 'file is required (multipart/form-data name="file")', 400);
    }
    $file = $_FILES['file'];
    // basic validation: png mime and reasonable size
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'image/png') errorResp('Invalid Request', 'file must be image/png', 400);
    if ($file['size'] > 2 * 1024 * 1024) errorResp('Invalid Request', 'file too large', 400);

    // store file
    $texturesDir = $dataDir . '/textures';
    if (!is_dir($texturesDir)) mkdir($texturesDir, 0755, true);
    $target = $texturesDir . '/' . $uuid . '_' . $type . '.png';
    if (!move_uploaded_file($file['tmp_name'], $target)) errorResp('Internal Server Error', 'Failed to save file', 500);

    // update profile properties: set textures url
    $base = getBaseUrl();
    $skinUrl = file_exists($texturesDir . '/' . $uuid . '_skin.png') ? ($base . '/data/textures/' . basename($texturesDir . '/' . $uuid . '_skin.png')) : null;
    $capeUrl = file_exists($texturesDir . '/' . $uuid . '_cape.png') ? ($base . '/data/textures/' . basename($texturesDir . '/' . $uuid . '_cape.png')) : null;
    $textures = [];
    if ($skinUrl) $textures['SKIN'] = ['url'=>$skinUrl];
    if ($capeUrl) $textures['CAPE'] = ['url'=>$capeUrl];
    $payload = [
        'timestamp' => nowMillis(),
        'profileId' => $uuid,
        'profileName' => (function() use ($pdo,$uuid){ $s=$pdo->prepare("SELECT name FROM profiles WHERE id=:id"); $s->execute([':id'=>$uuid]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? $r['name'] : ''; })(),
        'textures' => $textures
    ];
    // signature if private key
    $prop = ['name'=>'textures','value'=>base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE))];
    if ($privateKey) {
        $res = openssl_get_privatekey($privateKey);
        if ($res !== false) {
            openssl_sign(json_encode($payload, JSON_UNESCAPED_UNICODE), $sig, $res, OPENSSL_ALGO_SHA1);
            openssl_free_key($res);
            $prop['signature'] = base64_encode($sig);
        }
    }
    // update profile properties JSON: keep non-textures props and add textures prop
    $st = $pdo->prepare("SELECT properties FROM profiles WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$uuid]);
    $cur = $st->fetchColumn();
    $curArr = json_decode($cur, true) ?: [];
    // remove existing textures entry
    $newProps = array_filter($curArr, function($p){ return !(isset($p['name']) && $p['name'] === 'textures'); });
    $newProps[] = $prop;
    $up = $pdo->prepare("UPDATE profiles SET properties = :props WHERE id = :id");
    $up->execute([':props' => json_encode(array_values($newProps), JSON_UNESCAPED_UNICODE), ':id'=>$uuid]);

    http_response_code(204);
    exit;
}

// DELETE /api/user/profile/{uuid}/{skin|cape}
if (preg_match('#^/api/user/profile/([0-9a-fA-F]{32})/(skin|cape)$#', $path, $m) && $method === 'DELETE') {
    $uuid = $m[1];
    $type = $m[2];
    $token = getBearerToken();
    if (!$token) errorResp('Unauthorized', 'Missing Authorization header', 401);
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE accessToken = :a LIMIT 1");
    $stmt->execute([':a'=>$token]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t || $t['state'] !== 'valid') errorResp('ForbiddenOperation', 'Invalid token', 403);
    // check ownership
    $owns = false;
    if ($t['selectedProfileId'] === $uuid) $owns = true;
    else {
        $st = $pdo->prepare("SELECT user_id FROM profiles WHERE id = :id LIMIT 1");
        $st->execute([':id'=>$uuid]);
        $pp = $st->fetch(PDO::FETCH_ASSOC);
        if ($pp && $pp['user_id'] === $t['user_id']) $owns = true;
    }
    if (!$owns) errorResp('ForbiddenOperation', 'Not allowed to modify this profile', 403);
    // delete file if exists
    $file = $dataDir . '/textures/' . $uuid . '_' . $type . '.png';
    if (file_exists($file)) unlink($file);
    // remove textures entry from profile properties when appropriate
    $st = $pdo->prepare("SELECT properties FROM profiles WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$uuid]);
    $cur = $st->fetchColumn();
    $curArr = json_decode($cur, true) ?: [];
    // we will rebuild textures if skin/cape missing
    $skinFile = $dataDir . '/textures/' . $uuid . '_skin.png';
    $capeFile = $dataDir . '/textures/' . $uuid . '_cape.png';
    $base = getBaseUrl();
    $textures = [];
    if (file_exists($skinFile)) $textures['SKIN'] = ['url'=>$base . '/data/textures/' . basename($skinFile)];
    if (file_exists($capeFile)) $textures['CAPE'] = ['url'=>$base . '/data/textures/' . basename($capeFile)];
    $newProps = array_filter($curArr, function($p){ return !(isset($p['name']) && $p['name'] === 'textures'); });
    if (!empty($textures)) {
        $payload = [
            'timestamp' => nowMillis(),
            'profileId' => $uuid,
            'profileName' => (function() use ($pdo,$uuid){ $s=$pdo->prepare("SELECT name FROM profiles WHERE id=:id"); $s->execute([':id'=>$uuid]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? $r['name'] : ''; })(),
            'textures' => $textures
        ];
        $prop = ['name'=>'textures','value'=>base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE))];
        if ($privateKey) {
            $res = openssl_get_privatekey($privateKey);
            if ($res !== false) {
                openssl_sign(json_encode($payload, JSON_UNESCAPED_UNICODE), $sig, $res, OPENSSL_ALGO_SHA1);
                openssl_free_key($res);
                $prop['signature'] = base64_encode($sig);
            }
        }
        $newProps[] = $prop;
    }
    $up = $pdo->prepare("UPDATE profiles SET properties = :props WHERE id = :id");
    $up->execute([':props' => json_encode(array_values($newProps), JSON_UNESCAPED_UNICODE), ':id'=>$uuid]);

    http_response_code(204);
    exit;
}

// default not found
errorResp('Not Found', 'The requested endpoint was not found', 404);