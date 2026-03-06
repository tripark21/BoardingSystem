<?php
// Support both Railway environment variables and local development
$db_host = getenv('PGHOST') ?: ($_ENV['PGHOST'] ?? 'localhost');
$db_port = getenv('PGPORT') ?: ($_ENV['PGPORT'] ?? '5432');
$db_user = getenv('PGUSER') ?: ($_ENV['PGUSER'] ?? 'postgres');
$db_pass = getenv('PGPASSWORD') ?: ($_ENV['PGPASSWORD'] ?? '123');
$db_name = getenv('PGDATABASE') ?: ($_ENV['PGDATABASE'] ?? 'boarding_db');

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $conn->exec("SET search_path TO boarding, public");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Auth helpers ──────────────────────────────────────────────

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function isTenant() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'tenant';
}
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /boarding_system/login.php");
        exit;
    }
}
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /boarding_system/tenant/rooms.php");
        exit;
    }
}
function requireTenant() {
    requireLogin();
    if (!isTenant()) {
        header("Location: /boarding_system/admin/dashboard.php");
        exit;
    }
}

// ── CSRF helpers ──────────────────────────────────────────────

/**
 * Output a hidden CSRF input field.
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token on POST. Dies on failure.
 */
function verifyCsrf() {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die("Invalid CSRF token. Please go back and try again.");
    }
}

// ── Input helpers ─────────────────────────────────────────────

/**
 * Clean raw user input for storage (trim + strip dangerous tags).
 * Do NOT use htmlspecialchars here — that is for OUTPUT only.
 */
function cleanInput($data) {
    return trim(strip_tags($data));
}

/**
 * Sanitize a value for safe HTML output.
 * Use this when echoing user data into HTML.
 */
function sanitize($conn, $data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ── DB helpers ────────────────────────────────────────────────

function dbScalar(PDO $conn, string $sql, array $params = []) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : 0;
}
function dbAll(PDO $conn, string $sql, array $params = []): array {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
function dbOne(PDO $conn, string $sql, array $params = []) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}
function dbExec(PDO $conn, string $sql, array $params = []): PDOStatement {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
?>
