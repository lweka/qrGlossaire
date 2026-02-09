<?php
require_once __DIR__ . '/../app/config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitations Numériques</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= $baseUrl; ?>/assets/images/Logo12.png">
    <link rel="stylesheet" href="<?= $baseUrl; ?>/assets/css/main.css">
</head>
<body>
