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

function hasOrganizerSession(): bool
{
    ensureSession();
    return !empty($_SESSION['user_id']) && (string) ($_SESSION['user_type'] ?? '') === 'organizer';
}

function hasAdminSession(): bool
{
    ensureSession();
    return !empty($_SESSION['admin_id']);
}

function requireLogin(?string $expectedRole = null): void
{
    $expectedRole = $expectedRole ?? 'organizer';
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

    if ($expectedRole === 'admin') {
        if (!hasAdminSession()) {
            header('Location: ' . $baseUrl . roleLoginPath('admin'));
            exit;
        }
        return;
    }

    if (!hasOrganizerSession()) {
        header('Location: ' . $baseUrl . roleLoginPath('organizer'));
        exit;
    }
}

function requireRole(string $role): void
{
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

    if ($role === 'admin') {
        if (hasAdminSession()) {
            return;
        }

        if (hasOrganizerSession()) {
            header('Location: ' . $baseUrl . roleHomePath('organizer'));
            exit;
        }

        header('Location: ' . $baseUrl . roleLoginPath('admin'));
        exit;
    }

    if ($role === 'organizer') {
        if (hasOrganizerSession()) {
            return;
        }

        if (hasAdminSession()) {
            header('Location: ' . $baseUrl . roleHomePath('admin'));
            exit;
        }

        header('Location: ' . $baseUrl . roleLoginPath('organizer'));
        exit;
    }

    requireLogin($role);
}
