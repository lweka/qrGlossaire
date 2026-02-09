<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/security.php';

function requireLogin(): void
{
    ensureSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['user_type'] ?? '') !== $role) {
        header('Location: /login.php');
        exit;
    }
}
