<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rekap.php';
require_once __DIR__ . '/layout.php';
