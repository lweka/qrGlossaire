<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/config/constants.php';

function requireLogin(): void
{
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    ensureSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $baseUrl . '/login');
        exit;
    }
}

function requireRole(string $role): void
{
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    requireLogin();
    if (($_SESSION['user_type'] ?? '') !== $role) {
        header('Location: ' . $baseUrl . '/login');
        exit;
    }
}
