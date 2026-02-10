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

const RECAPTCHA_SITE_KEY = '6LeGaGMsAAAAAFvQeB67UFpsTRJdes8O_5Xm64nF';
const RECAPTCHA_API_KEY = 'AQ.Ab8RN6KOW4cx7K9Dv_KTV4ss6e9vNlqGI8xdbgII1AJS4EktLg';
const RECAPTCHA_SECRET_KEY_VALUE = '';
const RECAPTCHA_PROJECT_ID = 'qrglossaire';
const RECAPTCHA_ACTION_REGISTER = 'register';
const RECAPTCHA_MIN_SCORE = 0.5;
$recaptchaSecret = getenv('RECAPTCHA_SECRET_KEY');
if ($recaptchaSecret === false || trim($recaptchaSecret) === '') {
    $recaptchaSecret = RECAPTCHA_SECRET_KEY_VALUE;
}
define('RECAPTCHA_SECRET_KEY', trim((string) $recaptchaSecret));
const APP_DEBUG = true;
