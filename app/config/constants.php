<?php
const DB_HOST = 'srv996.hstgr.io';
const DB_NAME = 'u424760992_qr_glossaire';
const DB_USER = 'u424760992_qr_glossaire_u';
const DB_PASS = '2612@Qrglossaire';

$baseUrl = '';
$envBaseUrl = getenv('APP_BASE_URL');
if ($envBaseUrl !== false && trim($envBaseUrl) !== '') {
    $normalized = trim($envBaseUrl);
    $baseUrl = '/' . trim($normalized, '/');
    if ($baseUrl === '/') {
        $baseUrl = '';
    }
} else {
    $appRoot = realpath(dirname(__DIR__, 2));
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($appRoot !== false && $documentRoot !== false) {
        $appRootNormalized = str_replace('\\', '/', $appRoot);
        $documentRootNormalized = rtrim(str_replace('\\', '/', $documentRoot), '/');
        if ($documentRootNormalized !== '' && strpos($appRootNormalized, $documentRootNormalized) === 0) {
            $relativePath = trim(substr($appRootNormalized, strlen($documentRootNormalized)), '/');
            $baseUrl = $relativePath === '' ? '' : '/' . $relativePath;
        }
    }
}
define('BASE_URL', $baseUrl);

const APP_NAME = 'InviteQR';
const TOKEN_EXPIRY_DAYS = 7;

const RECAPTCHA_SITE_KEY = '6LcO3mYsAAAAAAbtRNu-zLeNN2fp7TxKSfgKES00';
const RECAPTCHA_API_KEY = '';
const RECAPTCHA_SECRET_KEY_VALUE = '6LcO3mYsAAAAAOIXz0tyGT3t77RnBKkjjVLJDNsY';
const RECAPTCHA_PROJECT_ID = 'qrglossaire';
const RECAPTCHA_ACTION_REGISTER = 'register';
const RECAPTCHA_MIN_SCORE = 0.5;
$recaptchaSitePrefix = substr((string) RECAPTCHA_SITE_KEY, 0, 10);
$envRecaptchaSecret = getenv('RECAPTCHA_SECRET_KEY');
$envRecaptchaSecret = $envRecaptchaSecret === false ? '' : trim((string) $envRecaptchaSecret);
$fileRecaptchaSecret = trim((string) RECAPTCHA_SECRET_KEY_VALUE);
$envLooksLikeApiKey = strpos($envRecaptchaSecret, 'AQ.') === 0;
$fileLooksLikeApiKey = strpos($fileRecaptchaSecret, 'AQ.') === 0;
$envMatchesSiteKey = $envRecaptchaSecret !== '' && $recaptchaSitePrefix !== '' && strpos($envRecaptchaSecret, $recaptchaSitePrefix) === 0;
$fileMatchesSiteKey = $fileRecaptchaSecret !== '' && $recaptchaSitePrefix !== '' && strpos($fileRecaptchaSecret, $recaptchaSitePrefix) === 0;

$recaptchaSecret = '';
$recaptchaSecretSource = '';
if ($envRecaptchaSecret !== '' && !$envLooksLikeApiKey) {
    $recaptchaSecret = $envRecaptchaSecret;
    $recaptchaSecretSource = 'env';
}
if ($fileRecaptchaSecret !== '' && !$fileLooksLikeApiKey) {
    if (
        $recaptchaSecret === ''
        || ($fileMatchesSiteKey && !$envMatchesSiteKey)
    ) {
        $recaptchaSecret = $fileRecaptchaSecret;
        $recaptchaSecretSource = 'constants';
    }
}
if ($recaptchaSecret === '') {
    if ($fileRecaptchaSecret !== '') {
        $recaptchaSecret = $fileRecaptchaSecret;
        $recaptchaSecretSource = 'constants-fallback';
    } else {
        $recaptchaSecret = $envRecaptchaSecret;
        $recaptchaSecretSource = $envRecaptchaSecret !== '' ? 'env-fallback' : 'none';
    }
}

define('RECAPTCHA_SECRET_KEY', $recaptchaSecret);
define('RECAPTCHA_SECRET_KEY_SOURCE', $recaptchaSecretSource);
const APP_DEBUG = true;
