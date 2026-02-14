<?php
require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/helpers/security.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
ensureSession();

if (!empty($_SESSION['user_id']) && (string) ($_SESSION['user_type'] ?? '') === 'admin') {
    header('Location: ' . $baseUrl . '/admin/dashboard');
    exit;
}

header('Location: ' . $baseUrl . '/admin/login');
exit;
