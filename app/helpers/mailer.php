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

function mailerLogPath(): string
{
    return __DIR__ . '/../../storage/logs/mailer.log';
}

function writeMailerLog(string $status, string $detail): void
{
    $statusLabel = strtoupper(trim($status) === '' ? 'INFO' : trim($status));
    $logDir = dirname(mailerLogPath());
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] [' . $statusLabel . '] ' . trim($detail) . PHP_EOL;
    @file_put_contents(mailerLogPath(), $line, FILE_APPEND);
}

function isMailerClassAvailable(?string &$errorDetail = null): bool
{
    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return true;
    }

    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        $errorDetail = 'PHPMailer indisponible. Executez composer install.';
        return false;
    }

    return true;
}

function getMailerConfig(?string &$errorDetail = null): ?array
{
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
        return null;
    }

    return [
        'smtp_host' => $smtpHost,
        'smtp_port' => $smtpPort > 0 ? $smtpPort : 587,
        'smtp_user' => $smtpUser,
        'smtp_password' => $smtpPassword,
        'smtp_secure' => strtolower($smtpSecure) === 'ssl' ? 'ssl' : 'tls',
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'reply_to_email' => $replyToEmail,
        'reply_to_name' => $replyToName,
    ];
}

function createConfiguredMailer(array $config, ?string &$errorDetail = null): ?\PHPMailer\PHPMailer\PHPMailer
{
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $config['smtp_user'];
        $mail->Password = (string) $config['smtp_password'];
        $mail->SMTPSecure = ((string) $config['smtp_secure'] === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $config['smtp_port'];
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 25;
        $mail->SMTPAutoTLS = true;
        $smtpDebug = (int) (getenv('SMTP_DEBUG') !== false ? getenv('SMTP_DEBUG') : 0);
        $mail->SMTPDebug = $smtpDebug;
        if ($smtpDebug > 0) {
            $mail->Debugoutput = static function ($debugText, $debugLevel): void {
                writeMailerLog('SMTP', 'L' . (int) $debugLevel . ': ' . trim((string) $debugText));
            };
        }

        $mailDomain = extractDomainFromEmail((string) $config['from_email'], 'cartelplus.site');
        $mail->Hostname = $mailDomain;
        $mail->Helo = $mailDomain;
        $mail->MessageID = sprintf('<%s.%s@%s>', date('YmdHis'), bin2hex(random_bytes(6)), $mailDomain);

        $mail->setFrom((string) $config['from_email'], (string) $config['from_name']);
        if ((string) $config['reply_to_email'] !== '') {
            $mail->addReplyTo((string) $config['reply_to_email'], (string) $config['reply_to_name']);
        }

        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

        $dkimDomain = trim((string) (getenv('MAIL_DKIM_DOMAIN') !== false ? getenv('MAIL_DKIM_DOMAIN') : ''));
        $dkimSelector = trim((string) (getenv('MAIL_DKIM_SELECTOR') !== false ? getenv('MAIL_DKIM_SELECTOR') : ''));
        $dkimPrivateKeyPath = trim((string) (getenv('MAIL_DKIM_PRIVATE_KEY_PATH') !== false ? getenv('MAIL_DKIM_PRIVATE_KEY_PATH') : ''));
        $dkimPassphrase = (string) (getenv('MAIL_DKIM_PASSPHRASE') !== false ? getenv('MAIL_DKIM_PASSPHRASE') : '');
        $dkimIdentity = trim((string) (getenv('MAIL_DKIM_IDENTITY') !== false ? getenv('MAIL_DKIM_IDENTITY') : ((string) $config['from_email'])));
        if ($dkimDomain !== '' && $dkimSelector !== '' && $dkimPrivateKeyPath !== '' && is_file($dkimPrivateKeyPath)) {
            $mail->DKIM_domain = $dkimDomain;
            $mail->DKIM_selector = $dkimSelector;
            $mail->DKIM_private = $dkimPrivateKeyPath;
            $mail->DKIM_passphrase = $dkimPassphrase;
            $mail->DKIM_identity = $dkimIdentity;
        }

        return $mail;
    } catch (\Throwable $throwable) {
        $errorDetail = 'Erreur configuration SMTP: ' . $throwable->getMessage();
        return null;
    }
}

function sendTransactionalEmail(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $htmlBody,
    string $plainBody,
    ?string &$errorDetail = null
): bool {
    $errorDetail = null;

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $errorDetail = 'Adresse email destinataire invalide.';
        writeMailerLog('ERROR', 'Envoi refuse. Destinataire invalide: ' . $recipientEmail);
        return false;
    }

    if (!isMailerClassAvailable($errorDetail)) {
        writeMailerLog('ERROR', (string) $errorDetail);
        return false;
    }

    $config = getMailerConfig($errorDetail);
    if (!$config) {
        writeMailerLog('ERROR', (string) $errorDetail);
        return false;
    }

    $safeRecipientName = trim($recipientName) === '' ? 'Invite' : trim($recipientName);
    $mail = createConfiguredMailer($config, $errorDetail);
    if (!$mail) {
        writeMailerLog('ERROR', (string) $errorDetail);
        return false;
    }

    try {
        $mail->addAddress($recipientEmail, $safeRecipientName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();
        writeMailerLog('SENT', 'Email envoye vers ' . $recipientEmail . ' | sujet: ' . $subject);
    } catch (\Throwable $throwable) {
        $errorDetail = 'Erreur SMTP: ' . $throwable->getMessage();
        writeMailerLog('ERROR', 'Echec email vers ' . $recipientEmail . ' | sujet: ' . $subject . ' | ' . $throwable->getMessage());
        return false;
    }

    return true;
}

function sendActivationLinkEmail(string $recipientEmail, string $recipientName, string $activationLink, ?string &$errorDetail = null): bool
{
    $appName = defined('APP_NAME') ? (string) APP_NAME : 'InviteQR';
    $tokenExpiryDays = defined('TOKEN_EXPIRY_DAYS') ? (string) TOKEN_EXPIRY_DAYS : '7';
    $safeRecipientName = trim($recipientName) === '' ? 'Organisateur' : trim($recipientName);
    $subject = 'Action requise: activez votre compte ' . $appName . ' (paiement confirme)';
    $safeActivationLink = htmlspecialchars($activationLink, ENT_QUOTES, 'UTF-8');
    $previewText = 'Paiement confirmé. Activez votre compte pour accéder à votre espace.';

    $mailConfig = getMailerConfig();
    $fromName = $mailConfig['from_name'] ?? 'Support';
    $replyToEmail = $mailConfig['reply_to_email'] ?? '';
    $safeFromName = htmlspecialchars((string) $fromName, ENT_QUOTES, 'UTF-8');
    $safeReplyToEmail = htmlspecialchars((string) $replyToEmail, ENT_QUOTES, 'UTF-8');

    $htmlBody = '<!doctype html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">';
    $htmlBody .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8') . '</div>';
    $htmlBody .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:20px 0;"><tr><td align="center">';
    $htmlBody .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:12px;overflow:hidden;">';
    $htmlBody .= '<tr><td style="padding:20px 28px;background:#0f766e;color:#ffffff;"><h1 style="margin:0;font-size:20px;line-height:1.3;">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</h1><p style="margin:8px 0 0 0;font-size:14px;opacity:0.92;">Activation de votre compte client</p></td></tr>';
    $htmlBody .= '<tr><td style="padding:24px 28px;">';
    $htmlBody .= '<p style="margin:0 0 14px 0;">Bonjour ' . htmlspecialchars($safeRecipientName, ENT_QUOTES, 'UTF-8') . ',</p>';
    $htmlBody .= '<p style="margin:0 0 14px 0;">Votre paiement a été validé. Pour terminer votre inscription et accéder à votre espace de configuration, activez votre compte maintenant.</p>';
    $htmlBody .= '<p style="text-align:center;margin:24px 0;"><a href="' . $safeActivationLink . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;">Activer mon compte</a></p>';
    $htmlBody .= '<p style="margin:0 0 10px 0;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>';
    $htmlBody .= '<p style="margin:0 0 14px 0;word-break:break-all;"><a href="' . $safeActivationLink . '">' . $safeActivationLink . '</a></p>';
    $htmlBody .= '<p style="margin:0 0 8px 0;">Informations importantes :</p>';
    $htmlBody .= '<ul style="margin:0 0 16px 18px;padding:0;"><li>Ce lien expire dans ' . $tokenExpiryDays . ' jours.</li><li>Cet email est envoyé suite à votre demande d\'inscription.</li></ul>';
    $htmlBody .= '<p style="margin:0;">Besoin d\'aide ? Répondez à cet email ou contactez le support.</p>';
    $htmlBody .= '</td></tr>';
    $htmlBody .= '<tr><td style="padding:14px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">Email transactionnel automatique envoyé par ' . $safeFromName . '. Support: ' . $safeReplyToEmail . '.</td></tr>';
    $htmlBody .= '</table></td></tr></table></body></html>';

    $plainBody = "Bonjour {$safeRecipientName},\n\n";
    $plainBody .= "Votre paiement a été validé.\n";
    $plainBody .= "Pour terminer votre inscription, activez votre compte via ce lien:\n{$activationLink}\n\n";
    $plainBody .= "Ce lien expire dans {$tokenExpiryDays} jours.\n";
    $plainBody .= "Cet email est envoyé suite à votre demande d'inscription.\n\n";
    $plainBody .= "Besoin d'aide ? Répondez à cet email.\n\n";
    $plainBody .= "Cordialement,\n{$fromName}";

    return sendTransactionalEmail($recipientEmail, $recipientName, $subject, $htmlBody, $plainBody, $errorDetail);
}

function sendGuestInvitationEmail(
    string $recipientEmail,
    string $recipientName,
    string $eventTitle,
    string $invitationLink,
    string $eventDate = '',
    string $eventLocation = '',
    string $customMessage = '',
    ?string &$errorDetail = null
): bool {
    $appName = defined('APP_NAME') ? (string) APP_NAME : 'InviteQR';
    $safeRecipientName = trim($recipientName) === '' ? 'Invite' : trim($recipientName);
    $safeEventTitle = trim($eventTitle) === '' ? 'Votre événement' : trim($eventTitle);
    $subject = 'Invitation: ' . $safeEventTitle;

    $mailConfig = getMailerConfig();
    $fromName = $mailConfig['from_name'] ?? $appName;

    $safeInvitationLink = htmlspecialchars($invitationLink, ENT_QUOTES, 'UTF-8');
    $safeEventDate = trim($eventDate);
    $safeEventLocation = trim($eventLocation);
    $safeCustomMessage = trim($customMessage);

    $htmlBody = '<!doctype html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">';
    $htmlBody .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:20px 0;"><tr><td align="center">';
    $htmlBody .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:12px;overflow:hidden;">';
    $htmlBody .= '<tr><td style="padding:20px 28px;background:#1e3a8a;color:#ffffff;"><h1 style="margin:0;font-size:20px;line-height:1.3;">' . htmlspecialchars($safeEventTitle, ENT_QUOTES, 'UTF-8') . '</h1><p style="margin:8px 0 0 0;font-size:14px;opacity:0.95;">Invitation officielle</p></td></tr>';
    $htmlBody .= '<tr><td style="padding:24px 28px;">';
    $htmlBody .= '<p style="margin:0 0 14px 0;">Bonjour ' . htmlspecialchars($safeRecipientName, ENT_QUOTES, 'UTF-8') . ',</p>';
    $htmlBody .= '<p style="margin:0 0 14px 0;">Vous êtes invité(e) à participer à notre événement. Merci de consulter votre invitation et de confirmer votre présence.</p>';
    if ($safeEventDate !== '') {
        $htmlBody .= '<p style="margin:0 0 8px 0;"><strong>Date:</strong> ' . htmlspecialchars($safeEventDate, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($safeEventLocation !== '') {
        $htmlBody .= '<p style="margin:0 0 12px 0;"><strong>Lieu:</strong> ' . htmlspecialchars($safeEventLocation, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($safeCustomMessage !== '') {
        $htmlBody .= '<p style="margin:0 0 14px 0;"><strong>Message:</strong> ' . htmlspecialchars($safeCustomMessage, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $htmlBody .= '<p style="text-align:center;margin:24px 0;"><a href="' . $safeInvitationLink . '" style="display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;">Voir mon invitation</a></p>';
    $htmlBody .= '<p style="margin:0 0 10px 0;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>';
    $htmlBody .= '<p style="margin:0;word-break:break-all;"><a href="' . $safeInvitationLink . '">' . $safeInvitationLink . '</a></p>';
    $htmlBody .= '</td></tr>';
    $htmlBody .= '<tr><td style="padding:14px 28px;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">Email envoyé via ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars((string) $fromName, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $htmlBody .= '</table></td></tr></table></body></html>';

    $plainBody = "Bonjour {$safeRecipientName},\n\n";
    $plainBody .= "Vous êtes invité(e) à l'événement: {$safeEventTitle}\n";
    if ($safeEventDate !== '') {
        $plainBody .= "Date: {$safeEventDate}\n";
    }
    if ($safeEventLocation !== '') {
        $plainBody .= "Lieu: {$safeEventLocation}\n";
    }
    if ($safeCustomMessage !== '') {
        $plainBody .= "Message: {$safeCustomMessage}\n";
    }
    $plainBody .= "\nConsultez votre invitation et confirmez votre présence:\n{$invitationLink}\n\n";
    $plainBody .= "Cordialement,\n{$fromName}";

    return sendTransactionalEmail($recipientEmail, $recipientName, $subject, $htmlBody, $plainBody, $errorDetail);
}

