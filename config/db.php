<?php
/**
 * Detox Buses Online Booking System
 * Database Configuration & Global Helpers
 * Course Alignment: BIT 313
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kenya Standard Timezone (EAT: UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Database credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'detox_buses_db');

/**
 * Establish PDO connection with auto-detection for XAMPP ports (3307 / 3306)
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $ports = [3307, 3306];
    $last_exception = null;

    foreach ($ports as $port) {
        try {
            // First check if database exists; if not, connect to server root to create it
            $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";charset=utf8mb4";
            $temp_pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Ensure database exists
            $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Now connect directly to the target database
            $db_dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($db_dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Auto-migrate tables if not present
            check_and_migrate_tables($pdo);

            return $pdo;
        } catch (PDOException $e) {
            $last_exception = $e;
        }
    }

    die("<div style='font-family:sans-serif;max-width:600px;margin:50px auto;padding:20px;border:1px solid #e74c3c;border-radius:8px;background:#fdf2f2;color:#c0392b;'>
        <h2>Database Connection Error</h2>
        <p>Could not connect to MySQL server on port 3307 or 3306. Please ensure MySQL is running in your XAMPP Control Panel.</p>
        <p><small>Details: " . htmlspecialchars($last_exception ? $last_exception->getMessage() : 'Unknown error') . "</small></p>
        </div>");
}

/**
 * Automatically creates the 5 required relational tables and seeds initial data if missing
 */
function check_and_migrate_tables($pdo) {
    $table_check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$table_check) {
        $sql_file = __DIR__ . '/../database.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $pdo->exec($sql);
        }
    }
}

/**
 * Authentication Helper Functions
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'customer'
    ];
}

function require_login($redirect_to = null) {
    if (!is_logged_in()) {
        $target = $redirect_to ?: $_SERVER['REQUEST_URI'];
        set_flash('error', 'Please log in to continue.');
        header('Location: /detox_buses/auth/login.php?redirect=' . urlencode($target));
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Access denied. Administrator privileges required.');
        header('Location: /detox_buses/index.php');
        exit;
    }
}

/**
 * Flash Message Helpers
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display Flash Message HTML
 */
function display_flash() {
    $flash = get_flash();
    if ($flash) {
        $type = htmlspecialchars($flash['type']);
        $msg = htmlspecialchars($flash['message']);
        $icon = ($type === 'success') ? '✓' : (($type === 'error') ? '⚠' : 'ℹ');
        echo "<div class='alert alert-{$type}'>
                <span class='alert-icon'>{$icon}</span>
                <span class='alert-msg'>{$msg}</span>
              </div>";
    }
}

/**
 * Currency and Date Formatters
 */
function format_kes($amount) {
    return 'KES ' . number_format((float)$amount, 2);
}

function format_date($date_str) {
    return date('D, d M Y', strtotime($date_str));
}

function format_time($time_str) {
    return date('h:i A', strtotime($time_str));
}

/**
 * Time-Check Engine for 1-Hour Pre-Departure Cash-Out
 * Compares current Africa/Nairobi server time with scheduled departure
 *
 * @param string $travel_date 'YYYY-MM-DD'
 * @param string $departure_time 'HH:MM:SS' or 'HH:MM'
 * @return array ['minutes_remaining' => int, 'is_eligible' => bool, 'reason' => string]
 */
function check_cashout_eligibility($travel_date, $departure_time) {
    $departure_str = $travel_date . ' ' . $departure_time;
    $departure_timestamp = strtotime($departure_str);
    $current_timestamp = time(); // Africa/Nairobi

    $difference_seconds = $departure_timestamp - $current_timestamp;
    $minutes_remaining = (int)floor($difference_seconds / 60);

    if ($difference_seconds <= 0) {
        return [
            'minutes_remaining' => $minutes_remaining,
            'is_eligible' => false,
            'reason' => 'Trip has already departed.'
        ];
    }

    if ($minutes_remaining < 60) {
        return [
            'minutes_remaining' => $minutes_remaining,
            'is_eligible' => false,
            'reason' => 'Cash-out closed within 1 hour of departure.'
        ];
    }

    return [
        'minutes_remaining' => $minutes_remaining,
        'is_eligible' => true,
        'reason' => 'Eligible for 90% instant cash-out.'
    ];
}

// Global active database handle
$pdo = get_db_connection();
