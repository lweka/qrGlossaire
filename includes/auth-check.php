<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/config/constants.php';

function roleLoginPath(string $role): string
{
    if ($role === 'admin') {
        return '/admin/login';
    }
    return '/login';
}

function roleHomePath(string $role): string
{
    if ($role === 'admin') {
        return '/admin/dashboard';
    }
    return '/dashboard';
}

function requireLogin(?string $expectedRole = null): void
{
    $expectedRole = $expectedRole ?? 'organizer';
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    ensureSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $baseUrl . roleLoginPath($expectedRole));
        exit;
    }
}

function requireRole(string $role): void
{
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    requireLogin($role);

    $currentRole = (string) ($_SESSION['user_type'] ?? '');
    if ($currentRole !== $role) {
        if ($currentRole === 'admin' || $currentRole === 'organizer') {
            header('Location: ' . $baseUrl . roleHomePath($currentRole));
            exit;
        }

        header('Location: ' . $baseUrl . roleLoginPath($role));
        exit;
    }
}
