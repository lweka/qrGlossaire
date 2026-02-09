<?php
function generateSecureToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

function sanitizeInput(string $input): string
{
    $input = trim($input);
    $input = stripslashes($input);
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function ensureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrfToken(): string
{
    ensureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken(16);
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    ensureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}
