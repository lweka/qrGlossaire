<?php

function normalizePhoneToE164(string $rawPhone, string $defaultCountryCode = ''): ?string
{
    $phone = trim($rawPhone);
    if ($phone === '') {
        return null;
    }

    $phone = str_replace(["\t", "\r", "\n", ' ', '-', '.', '(', ')'], '', $phone);
    if (strpos($phone, '00') === 0) {
        $phone = '+' . substr($phone, 2);
    }

    if (strpos($phone, '+') !== 0) {
        $countryCode = trim($defaultCountryCode);
        if ($countryCode !== '') {
            $countryCode = preg_replace('/[^0-9+]/', '', $countryCode);
            if ($countryCode !== '') {
                if (strpos($countryCode, '+') !== 0) {
                    $countryCode = '+' . ltrim($countryCode, '+');
                }
                $phone = ltrim($phone, '0');
                $phone = $countryCode . $phone;
            }
        }
    }

    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $phone) !== 1) {
        return null;
    }

    return $phone;
}

function getMessagingConfig(?string &$errorDetail = null): ?array
{
    $errorDetail = null;

    $accountSid = trim((string) (getenv('TWILIO_ACCOUNT_SID') !== false ? getenv('TWILIO_ACCOUNT_SID') : ''));
    $authToken = trim((string) (getenv('TWILIO_AUTH_TOKEN') !== false ? getenv('TWILIO_AUTH_TOKEN') : ''));
    $smsFrom = trim((string) (getenv('TWILIO_SMS_FROM') !== false ? getenv('TWILIO_SMS_FROM') : ''));
    $whatsAppFrom = trim((string) (getenv('TWILIO_WHATSAPP_FROM') !== false ? getenv('TWILIO_WHATSAPP_FROM') : ''));
    $defaultCountryCode = trim((string) (getenv('DEFAULT_PHONE_COUNTRY_CODE') !== false ? getenv('DEFAULT_PHONE_COUNTRY_CODE') : ''));

    if ($accountSid === '' || $authToken === '') {
        $errorDetail = 'Configuration Twilio absente. Definir TWILIO_ACCOUNT_SID et TWILIO_AUTH_TOKEN.';
        return null;
    }

    return [
        'account_sid' => $accountSid,
        'auth_token' => $authToken,
        'sms_from' => $smsFrom,
        'whatsapp_from' => $whatsAppFrom,
        'default_country_code' => $defaultCountryCode,
    ];
}

function isTwilioChannelReady(string $channel): bool
{
    $config = getMessagingConfig();
    if (!$config) {
        return false;
    }

    if ($channel === 'sms') {
        return trim((string) ($config['sms_from'] ?? '')) !== '';
    }

    if ($channel === 'whatsapp') {
        return trim((string) ($config['whatsapp_from'] ?? '')) !== '';
    }

    return false;
}

function buildGuestDispatchText(
    string $recipientName,
    string $eventTitle,
    string $invitationLink,
    string $eventDate = '',
    string $eventLocation = '',
    string $customMessage = ''
): string {
    $safeRecipientName = trim($recipientName) === '' ? 'Invite' : trim($recipientName);
    $safeEventTitle = trim($eventTitle) === '' ? 'notre evenement' : trim($eventTitle);
    $safeEventDate = trim($eventDate);
    $safeEventLocation = trim($eventLocation);
    $safeCustomMessage = trim($customMessage);

    $lines = [];
    $lines[] = 'Bonjour ' . $safeRecipientName . ',';
    $lines[] = 'Vous etes invite(e) a: ' . $safeEventTitle . '.';

    if ($safeEventDate !== '') {
        $lines[] = 'Date: ' . $safeEventDate;
    }
    if ($safeEventLocation !== '') {
        $lines[] = 'Lieu: ' . $safeEventLocation;
    }
    if ($safeCustomMessage !== '') {
        $lines[] = 'Message: ' . $safeCustomMessage;
    }

    $lines[] = 'Consultez votre invitation et confirmez votre presence:';
    $lines[] = $invitationLink;
    $lines[] = 'Envoye via InviteQR.';

    return implode("\n", $lines);
}

function sendTwilioChannelMessage(
    string $channel,
    string $rawPhone,
    string $body,
    ?string &$errorDetail = null,
    ?string &$providerMessageId = null
): bool {
    $errorDetail = null;
    $providerMessageId = null;

    if (!in_array($channel, ['sms', 'whatsapp'], true)) {
        $errorDetail = 'Canal non supporte.';
        return false;
    }

    if (!extension_loaded('curl')) {
        $errorDetail = 'Extension curl indisponible sur le serveur.';
        return false;
    }

    $config = getMessagingConfig($errorDetail);
    if (!$config) {
        return false;
    }

    $normalizedPhone = normalizePhoneToE164($rawPhone, (string) ($config['default_country_code'] ?? ''));
    if ($normalizedPhone === null) {
        $errorDetail = 'Numero invalide. Utilisez un format international, ex: +243812345678.';
        return false;
    }

    $from = $channel === 'sms'
        ? trim((string) ($config['sms_from'] ?? ''))
        : trim((string) ($config['whatsapp_from'] ?? ''));

    if ($from === '') {
        $errorDetail = $channel === 'sms'
            ? 'TWILIO_SMS_FROM manquant.'
            : 'TWILIO_WHATSAPP_FROM manquant.';
        return false;
    }

    if ($channel === 'sms') {
        if (stripos($from, 'whatsapp:') === 0) {
            $errorDetail = 'TWILIO_SMS_FROM ne doit pas commencer par whatsapp:.';
            return false;
        }
        $from = normalizePhoneToE164($from, (string) ($config['default_country_code'] ?? '')) ?? $from;
    } else {
        if (stripos($from, 'whatsapp:') !== 0) {
            $from = 'whatsapp:' . $from;
        }
    }

    $to = $channel === 'whatsapp' ? 'whatsapp:' . $normalizedPhone : $normalizedPhone;
    $messageBody = trim($body);
    if ($messageBody === '') {
        $errorDetail = 'Message vide.';
        return false;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode((string) $config['account_sid']) . '/Messages.json';
    $payload = http_build_query([
        'To' => $to,
        'From' => $from,
        'Body' => $messageBody,
    ], '', '&', PHP_QUERY_RFC3986);

    $curl = curl_init($url);
    if ($curl === false) {
        $errorDetail = 'Impossible d initialiser la requete HTTP.';
        return false;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_USERPWD => (string) $config['account_sid'] . ':' . (string) $config['auth_token'],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $rawResponse = curl_exec($curl);
    $curlErrNo = curl_errno($curl);
    $curlErr = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($rawResponse === false || $curlErrNo !== 0) {
        $errorDetail = 'Erreur reseau Twilio: ' . $curlErr;
        return false;
    }

    $response = json_decode((string) $rawResponse, true);
    $providerMessageId = is_array($response) ? (string) ($response['sid'] ?? '') : null;

    if ($httpCode >= 200 && $httpCode < 300 && $providerMessageId !== '') {
        return true;
    }

    $apiMessage = '';
    if (is_array($response)) {
        $apiMessage = (string) ($response['message'] ?? '');
        $apiCode = (string) ($response['code'] ?? '');
        if ($apiCode !== '') {
            $apiMessage = '[Code ' . $apiCode . '] ' . $apiMessage;
        }
    }
    if ($apiMessage === '') {
        $apiMessage = trim((string) $rawResponse);
    }
    if ($apiMessage === '') {
        $apiMessage = 'Reponse vide de Twilio.';
    }

    $errorDetail = 'Echec ' . strtoupper($channel) . ' (HTTP ' . $httpCode . '): ' . $apiMessage;
    return false;
}

function sendGuestSmsInvitation(
    string $phone,
    string $recipientName,
    string $eventTitle,
    string $invitationLink,
    string $eventDate = '',
    string $eventLocation = '',
    string $customMessage = '',
    ?string &$errorDetail = null,
    ?string &$providerMessageId = null
): bool {
    $text = buildGuestDispatchText($recipientName, $eventTitle, $invitationLink, $eventDate, $eventLocation, $customMessage);
    return sendTwilioChannelMessage('sms', $phone, $text, $errorDetail, $providerMessageId);
}

function sendGuestWhatsAppInvitation(
    string $phone,
    string $recipientName,
    string $eventTitle,
    string $invitationLink,
    string $eventDate = '',
    string $eventLocation = '',
    string $customMessage = '',
    ?string &$errorDetail = null,
    ?string &$providerMessageId = null
): bool {
    $text = buildGuestDispatchText($recipientName, $eventTitle, $invitationLink, $eventDate, $eventLocation, $customMessage);
    return sendTwilioChannelMessage('whatsapp', $phone, $text, $errorDetail, $providerMessageId);
}

function buildGuestManualShareText(
    string $recipientName,
    string $eventTitle,
    string $invitationLink,
    string $eventDate = '',
    string $eventLocation = '',
    string $customMessage = ''
): string {
    return buildGuestDispatchText($recipientName, $eventTitle, $invitationLink, $eventDate, $eventLocation, $customMessage);
}
