<?php
require_once __DIR__ . '/app/helpers/security.php';
require_once __DIR__ . '/app/config/constants.php';
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
ensureSession();
$_SESSION = [];
session_destroy();
header('Location: ' . $baseUrl . '/');
exit;
