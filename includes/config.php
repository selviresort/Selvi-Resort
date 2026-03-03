<?php
// ============================================================
//  includes/config.php  — DB connection & shared helpers
//  Place at: selvi-resort/includes/config.php
// ============================================================

define('DB_HOST',    'localhost');
define('DB_USER',    'root');         // XAMPP default — change for production
define('DB_PASS',    '');             // XAMPP default — change for production
define('DB_NAME',    'selvi_resort');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME',        'Selvi Resort & Lawn');
define('ADMIN_SESSION_KEY', 'selvi_admin_logged_in');
define('ADMIN_USER_KEY',    'selvi_admin_user');

// ── PDO Connection (singleton) ──────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log real error, show safe message
            error_log('DB Connection failed: ' . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'Database connection failed. Check config.php credentials.']));
        }
    }
    return $pdo;
}

// ── Session helpers ─────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isAdminLoggedIn(): bool {
    startSession();
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentAdmin(): array {
    startSession();
    return $_SESSION[ADMIN_USER_KEY] ?? [];
}

// ── Booking reference generator ─────────────────────────────
function generateBookingRef(): string {
    $pdo   = getDB();
    $year  = date('Y');
    $stmt  = $pdo->query("SELECT COUNT(*) FROM bookings WHERE YEAR(created_at) = $year");
    $count = (int)$stmt->fetchColumn() + 1;
    return 'SR-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

// ── JSON response helper ─────────────────────────────────────
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ── Sanitize input ───────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// ── Activity log ─────────────────────────────────────────────
function logActivity(string $action, string $type = '', int $targetId = 0): void {
    try {
        $admin   = getCurrentAdmin();
        $adminId = $admin['id'] ?? null;
        $pdo     = getDB();
        $pdo->prepare("INSERT INTO activity_log (admin_id, action, target_type, target_id) VALUES (?,?,?,?)")
            ->execute([$adminId, $action, $type ?: null, $targetId ?: null]);
    } catch (Exception $e) {
        error_log('logActivity error: ' . $e->getMessage());
    }
}

// ── Settings helper ──────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    try {
        $stmt = getDB()->prepare("SELECT setting_val FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Start session on every include
startSession();
