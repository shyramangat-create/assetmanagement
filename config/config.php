<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}
define('BASE_URL', '/assetmanagement-main/asset_management/');
define('ROOT_PATH', dirname(__DIR__));
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once ROOT_PATH . '/includes/csrf.php';
$host = '127.0.0.1';
$dbname = 'asset_management';
$dbuser = 'root';
$dbpass = '';
