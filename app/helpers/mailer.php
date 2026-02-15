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

function extractDomainFromEmail(string $email, string $fallback = 'localhost'): string
{
    $parts = explode('@', $email);
    if (count($parts) === 2) {
        $candidate = strtolower(trim($parts[1]));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return $fallback;
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
    $replyToEmail = trim((string) (getenv('MAIL_REPLY_TO_EMAIL') !== false ? getenv('MAIL_REPLY_TO_EMAIL') : $fromEmail));
    $replyToName = trim((string) (getenv('MAIL_REPLY_TO_NAME') !== false ? getenv('MAIL_REPLY_TO_NAME') : 'Support Cartelplus Congo'));

    if ($smtpHost === '' || $smtpUser === '' || $smtpPassword === '') {
        $errorDetail = 'Configuration SMTP incomplete.';
        return false;
    }

    $safeRecipientName = trim($recipientName) === '' ? 'Organisateur' : trim($recipientName);
    $appName = defined('APP_NAME') ? (string) APP_NAME : 'InviteQR';
    $tokenExpiryDays = defined('TOKEN_EXPIRY_DAYS') ? (string) TOKEN_EXPIRY_DAYS : '7';
    $subject = 'Action requise: activez votre compte ' . $appName . ' (paiement confirme)';
    $safeActivationLink = htmlspecialchars($activationLink, ENT_QUOTES, 'UTF-8');
    $safeFromName = htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8');
    $safeReplyToEmail = htmlspecialchars($replyToEmail, ENT_QUOTES, 'UTF-8');
    $previewText = 'Paiement confirme. Activez votre compte pour acceder a votre espace.';

    $htmlBody = '<!doctype html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">';
    $htmlBody .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') . '</div>';
    $htmlBody .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:20px 0;"><tr><td align="center">';
    $htmlBody .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:12px;overflow:hidden;">';
    $htmlBody .= '<tr><td style="padding:20px 28px;background:#0f766e;color:#ffffff;"><h1 style="margin:0;font-size:20px;line-height:1.3;">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</h1><p style="margin:8px 0 0 0;font-size:14px;opacity:0.92;">Activation de votre compte client</p></td></tr>';
    $htmlBody .= '<tr><td style="padding:24px 28px;">';
    $htmlBody .= '<p style="margin:0 0 14px 0;">Bonjour ' . htmlspecialchars($safeRecipientName, ENT_QUOTES, 'UTF-8') . ',</p>';
    $htmlBody .= '<p style="margin:0 0 14px 0;">Votre paiement a ete valide. Pour terminer votre inscription et acceder a votre espace de configuration, activez votre compte maintenant.</p>';
    $htmlBody .= '<p style="text-align:center;margin:24px 0;"><a href="' . $safeActivationLink . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;">Activer mon compte</a></p>';
    $htmlBody .= '<p style="margin:0 0 10px 0;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>';
    $htmlBody .= '<p style="margin:0 0 14px 0;word-break:break-all;"><a href="' . $safeActivationLink . '">' . $safeActivationLink . '</a></p>';
    $htmlBody .= '<p style="margin:0 0 8px 0;">Informations importantes :</p>';
    $htmlBody .= '<ul style="margin:0 0 16px 18px;padding:0;"><li>Ce lien expire dans ' . $tokenExpiryDays . ' jours.</li><li>Cet email est envoye suite a votre demande d inscription.</li></ul>';
    $htmlBody .= '<p style="margin:0;">Besoin d aide ? Repondez a cet email ou contactez le support.</p>';
    $htmlBody .= '</td></tr>';
    $htmlBody .= '<tr><td style="padding:14px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">Email transactionnel automatique envoye par ' . $safeFromName . '. Support: ' . $safeReplyToEmail . '.</td></tr>';
    $htmlBody .= '</table></td></tr></table></body></html>';

    $plainBody = "Bonjour {$safeRecipientName},\n\n";
    $plainBody .= "Votre paiement a ete valide.\n";
    $plainBody .= "Pour terminer votre inscription, activez votre compte via ce lien:\n{$activationLink}\n\n";
    $plainBody .= "Ce lien expire dans {$tokenExpiryDays} jours.\n";
    $plainBody .= "Cet email est envoye suite a votre demande d inscription.\n\n";
    $plainBody .= "Besoin d aide ? Repondez a cet email.\n\n";
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
        $mail->Timeout = 20;
        $mail->SMTPAutoTLS = true;

        $mailDomain = extractDomainFromEmail($fromEmail, 'cartelplus.site');
        $mail->Hostname = $mailDomain;
        $mail->Helo = $mailDomain;
        $mail->MessageID = sprintf('<%s.%s@%s>', date('YmdHis'), bin2hex(random_bytes(6)), $mailDomain);

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $safeRecipientName);
        if ($replyToEmail !== '') {
            $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $fromName);
        }
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

        $dkimDomain = trim((string) (getenv('MAIL_DKIM_DOMAIN') !== false ? getenv('MAIL_DKIM_DOMAIN') : ''));
        $dkimSelector = trim((string) (getenv('MAIL_DKIM_SELECTOR') !== false ? getenv('MAIL_DKIM_SELECTOR') : ''));
        $dkimPrivateKeyPath = trim((string) (getenv('MAIL_DKIM_PRIVATE_KEY_PATH') !== false ? getenv('MAIL_DKIM_PRIVATE_KEY_PATH') : ''));
        $dkimPassphrase = (string) (getenv('MAIL_DKIM_PASSPHRASE') !== false ? getenv('MAIL_DKIM_PASSPHRASE') : '');
        $dkimIdentity = trim((string) (getenv('MAIL_DKIM_IDENTITY') !== false ? getenv('MAIL_DKIM_IDENTITY') : $fromEmail));
        if ($dkimDomain !== '' && $dkimSelector !== '' && $dkimPrivateKeyPath !== '' && is_file($dkimPrivateKeyPath)) {
            $mail->DKIM_domain = $dkimDomain;
            $mail->DKIM_selector = $dkimSelector;
            $mail->DKIM_private = $dkimPrivateKeyPath;
            $mail->DKIM_passphrase = $dkimPassphrase;
            $mail->DKIM_identity = $dkimIdentity;
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
