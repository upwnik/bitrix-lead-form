
<?php

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

function isAdminAuthenticated(): bool
{
    startAdminSession();

    return isset($_SESSION['admin_authenticated'])
        && $_SESSION['admin_authenticated'] === true;
}

function requireAdmin(): void
{
    if (!isAdminAuthenticated()) {
        header('Location: /admin/login.php');
        exit;
    }
}
