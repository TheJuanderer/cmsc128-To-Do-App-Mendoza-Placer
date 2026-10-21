<?php
// Enable error reporting during development to catch issues instantly
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (!function_exists('load_env')) {
    function load_env(string $file): void {
        if (!is_readable($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \t\"'");
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        return $_ENV[$key] ?? (getenv($key) ?: $default);
    }
}

load_env(__DIR__ . '/.env');

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 1; // 1 day
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli(
        env('DB_HOST', 'localhost'),
        env('DB_USER', 'root'),
        env('DB_PASS', ''),
        env('DB_NAME', 'todo_db')
    );

    if ($conn->connect_error) {
        error_log('DB connection failed: ' . $conn->connect_error);
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
}
?>