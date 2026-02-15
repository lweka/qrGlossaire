<?php

function buildAbsoluteUrl(string $path): string
{
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    $normalizedPath = '/' . ltrim($path, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host !== '') {
        return $scheme . '://' . $host . $normalizedPath;
    }

    $envPublicUrl = getenv('APP_PUBLIC_URL');
    if ($envPublicUrl !== false && trim((string) $envPublicUrl) !== '') {
        return rtrim(trim((string) $envPublicUrl), '/') . $normalizedPath;
    }

    return $normalizedPath;
}

function sendActivationLinkEmail(string $recipientEmail, string $recipientName, string $activationLink, ?string &$errorDetail = null): bool
{
    $errorDetail = null;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
        }
    }
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        $errorDetail = 'PHPMailer indisponible. Executez composer install.';
        return false;
    }

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $errorDetail = 'Adresse email destinataire invalide.';
        return false;
    }

    $smtpHost = trim((string) (getenv('SMTP_HOST') !== false ? getenv('SMTP_HOST') : 'smtp.titan.email'));
    $smtpPort = (int) (getenv('SMTP_PORT') !== false ? getenv('SMTP_PORT') : 587);
    $smtpUser = trim((string) (getenv('SMTP_USERNAME') !== false ? getenv('SMTP_USERNAME') : 'cartelplus-congo@cartelplus.site'));
    $smtpPassword = (string) (getenv('SMTP_PASSWORD') !== false ? getenv('SMTP_PASSWORD') : 'Jo@Kin243');
    $smtpSecure = trim((string) (getenv('SMTP_ENCRYPTION') !== false ? getenv('SMTP_ENCRYPTION') : 'tls'));
    $fromEmail = trim((string) (getenv('MAIL_FROM_EMAIL') !== false ? getenv('MAIL_FROM_EMAIL') : 'cartelplus-congo@cartelplus.site'));
    $fromName = trim((string) (getenv('MAIL_FROM_NAME') !== false ? getenv('MAIL_FROM_NAME') : 'Cartelplus Congo'));
    $replyToEmail = trim((string) (getenv('MAIL_REPLY_TO_EMAIL') !== false ? getenv('MAIL_REPLY_TO_EMAIL') : 'support@cartelplus.cd'));
    $replyToName = trim((string) (getenv('MAIL_REPLY_TO_NAME') !== false ? getenv('MAIL_REPLY_TO_NAME') : 'Support Cartelplus Congo'));

    if ($smtpHost === '' || $smtpUser === '' || $smtpPassword === '') {
        $errorDetail = 'Configuration SMTP incomplete.';
        return false;
    }

    $safeRecipientName = trim($recipientName) === '' ? 'Organisateur' : trim($recipientName);
    $appName = defined('APP_NAME') ? (string) APP_NAME : 'InviteQR';
    $subject = 'Activation de votre compte ' . $appName;

    $htmlBody = '<p>Bonjour ' . htmlspecialchars($safeRecipientName, ENT_QUOTES, 'UTF-8') . ',</p>';
    $htmlBody .= '<p>Votre paiement a ete valide.</p>';
    $htmlBody .= '<p>Cliquez sur ce lien pour activer votre compte :</p>';
    $htmlBody .= '<p><a href="' . htmlspecialchars($activationLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($activationLink, ENT_QUOTES, 'UTF-8') . '</a></p>';
    $htmlBody .= '<p>Ce lien expire dans ' . (defined('TOKEN_EXPIRY_DAYS') ? (string) TOKEN_EXPIRY_DAYS : '7') . ' jours.</p>';
    $htmlBody .= '<p>Si vous n etes pas a l origine de cette demande, ignorez cet email.</p>';
    $htmlBody .= '<p>Cordialement,<br>' . htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8') . '</p>';

    $plainBody = "Bonjour {$safeRecipientName},\n\n";
    $plainBody .= "Votre paiement a ete valide.\n";
    $plainBody .= "Cliquez sur ce lien pour activer votre compte :\n{$activationLink}\n\n";
    $plainBody .= "Ce lien expire dans " . (defined('TOKEN_EXPIRY_DAYS') ? (string) TOKEN_EXPIRY_DAYS : '7') . " jours.\n\n";
    $plainBody .= "Si vous n etes pas a l origine de cette demande, ignorez cet email.\n\n";
    $plainBody .= "Cordialement,\n{$fromName}";

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = ($smtpSecure === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort > 0 ? $smtpPort : 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $safeRecipientName);
        if ($replyToEmail !== '') {
            $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $fromName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();
    } catch (\Throwable $throwable) {
        $errorDetail = 'Erreur SMTP: ' . $throwable->getMessage();
        return false;
    }

    return true;
}
