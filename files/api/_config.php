<?php
declare(strict_types=1);

/**
 * API Configuration and Bootstrap
 */

// Set proper headers for API responses
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Debug mode
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : 0);

// Include JWT
require_once __DIR__ . '/_jwt.php';

/**
 * Get database connection
 */
function api_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'bdvotigo';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $port = getenv('DB_PORT') ?: '3306';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+03:00'");
    
    return $pdo;
}

/**
 * Get vote secret for anonymous tokens
 */
function vote_secret(): string {
    return (string)($_ENV['VOTIGO_VOTE_SECRET'] ?? getenv('VOTIGO_VOTE_SECRET') ?? 'dev-votigo-secret');
}

/**
 * Generate anonymous token (HMAC-SHA256)
 */
function anon_token(int $idUser, int $idElection): string {
    return hash_hmac('sha256', $idUser . ':' . $idElection, vote_secret());
}

function votes_has_user_column(PDO $pdo): bool {
    static $hasColumn = null;
    if ($hasColumn === null) {
        $stmt = $pdo->query("SHOW COLUMNS FROM votes LIKE 'id_user'");
        $hasColumn = (bool)$stmt->fetch();
    }
    return $hasColumn;
}

/**
 * Get Authorization header (Bearer token)
 */
function get_auth_token(): ?string {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $parts = explode(' ', $headers['Authorization']);
        if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
            return $parts[1];
        }
    }
    return null;
}

/**
 * Get authenticated user from JWT token
 */
function get_auth_user(): ?array {
    $token = get_auth_token();
    if (!$token) {
        return null;
    }
    
    $decoded = JWT::decode($token);
    if (!$decoded || !isset($decoded['id_user'])) {
        return null;
    }
    
    return $decoded;
}

/**
 * Require authentication
 */
function require_auth(): array {
    $user = get_auth_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    return $user;
}

/**
 * Validate JSON input
 */
function get_json_input(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        api_error('Invalid JSON input', 400);
    }
    return $data;
}
