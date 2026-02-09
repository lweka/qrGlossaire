<?php
require_once __DIR__ . '/constants.php';

$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbPort = getenv('DB_PORT');

$dbHost = ($dbHost !== false && trim($dbHost) !== '') ? trim($dbHost) : DB_HOST;
$dbName = ($dbName !== false && trim($dbName) !== '') ? trim($dbName) : DB_NAME;
$dbUser = ($dbUser !== false && trim($dbUser) !== '') ? trim($dbUser) : DB_USER;
$dbPass = ($dbPass !== false) ? $dbPass : DB_PASS;
$dbPort = ($dbPort !== false && trim($dbPort) !== '') ? trim($dbPort) : '';

$hostsToTry = [];
foreach ([$dbHost, 'localhost', '127.0.0.1'] as $candidateHost) {
    $candidateHost = trim((string) $candidateHost);
    if ($candidateHost !== '' && !in_array($candidateHost, $hostsToTry, true)) {
        $hostsToTry[] = $candidateHost;
    }
}

$lastError = null;
foreach ($hostsToTry as $host) {
    try {
        $dsn = 'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4';
        if ($dbPort !== '') {
            $dsn = 'mysql:host=' . $host . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';
        }

        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $lastError = null;
        break;
    } catch (PDOException $e) {
        $lastError = $e;
        $message = $e->getMessage();
        if ((string) $e->getCode() === '1045' || strpos($message, '[1045]') !== false) {
            break;
        }
    }
}

if (!isset($pdo)) {
    http_response_code(500);
    if (defined('APP_DEBUG') && APP_DEBUG && $lastError instanceof PDOException) {
        echo 'Erreur de connexion a la base de donnees. Detail: ' . $lastError->getMessage();
    } else {
        echo 'Erreur de connexion a la base de donnees.';
    }
    exit;
}
