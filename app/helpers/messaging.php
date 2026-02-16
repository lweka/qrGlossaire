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

function messagingEnv(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }
    return trim((string) $value);
}

function messagingProviderLabel(string $provider): string
{
    $provider = strtolower(trim($provider));
    if ($provider === 'twilio') {
        return 'Twilio';
    }
    if ($provider === 'meta') {
        return 'Meta Cloud API';
    }
    if ($provider === 'infobip') {
        return 'Infobip';
    }
    if ($provider === 'none' || $provider === '') {
        return 'Aucun provider';
    }
    return strtoupper($provider);
}

function getMessagingConfig(?string &$errorDetail = null): ?array
{
    $errorDetail = null;

    $config = [
        'default_country_code' => messagingEnv('DEFAULT_PHONE_COUNTRY_CODE', ''),
        'sms_provider' => strtolower(messagingEnv('SMS_PROVIDER', 'twilio')),
        'whatsapp_provider' => strtolower(messagingEnv('WHATSAPP_PROVIDER', 'twilio')),
        'twilio' => [
            'account_sid' => messagingEnv('TWILIO_ACCOUNT_SID', ''),
            'auth_token' => messagingEnv('TWILIO_AUTH_TOKEN', ''),
            'sms_from' => messagingEnv('TWILIO_SMS_FROM', ''),
            'whatsapp_from' => messagingEnv('TWILIO_WHATSAPP_FROM', ''),
        ],
        'meta' => [
            'graph_version' => messagingEnv('WHATSAPP_META_GRAPH_VERSION', 'v20.0'),
            'phone_number_id' => messagingEnv('WHATSAPP_META_PHONE_NUMBER_ID', ''),
            'access_token' => messagingEnv('WHATSAPP_META_ACCESS_TOKEN', ''),
        ],
        'infobip' => [
            'base_url' => rtrim(messagingEnv('INFOBIP_BASE_URL', ''), '/'),
            'api_key' => messagingEnv('INFOBIP_API_KEY', ''),
            'sms_from' => messagingEnv('INFOBIP_SMS_FROM', ''),
        ],
    ];

    $status = getMessagingStatusSummary($config);
    $errors = [];
    if (!$status['sms']['ready'] && $status['sms']['error'] !== '') {
        $errors[] = 'SMS: ' . $status['sms']['error'];
    }
    if (!$status['whatsapp']['ready'] && $status['whatsapp']['error'] !== '') {
        $errors[] = 'WhatsApp: ' . $status['whatsapp']['error'];
    }
    if (!empty($errors)) {
        $errorDetail = implode(' | ', $errors);
    }

    return $config;
}

function getMessagingChannelStatus(string $channel, ?array $config = null): array
{
    $channel = strtolower(trim($channel));
    $status = [
        'ready' => false,
        'provider' => 'none',
        'provider_label' => messagingProviderLabel('none'),
        'error' => '',
    ];

    if (!in_array($channel, ['sms', 'whatsapp'], true)) {
        $status['error'] = 'Canal non supporte.';
        return $status;
    }

    $config = $config ?? getMessagingConfig();
    if (!is_array($config)) {
        $status['error'] = 'Configuration messaging indisponible.';
        return $status;
    }

    $provider = (string) ($config[$channel . '_provider'] ?? 'none');
    $provider = strtolower(trim($provider));
    if ($provider === '') {
        $provider = 'none';
    }

    $status['provider'] = $provider;
    $status['provider_label'] = messagingProviderLabel($provider);

    if ($channel === 'sms') {
        if ($provider === 'twilio') {
            $sid = trim((string) ($config['twilio']['account_sid'] ?? ''));
            $token = trim((string) ($config['twilio']['auth_token'] ?? ''));
            $from = trim((string) ($config['twilio']['sms_from'] ?? ''));

            if ($sid === '' || $token === '') {
                $status['error'] = 'Configuration Twilio absente. Definir TWILIO_ACCOUNT_SID et TWILIO_AUTH_TOKEN.';
            } elseif ($from === '') {
                $status['error'] = 'TWILIO_SMS_FROM manquant.';
            } else {
                $status['ready'] = true;
            }

            return $status;
        }

        if ($provider === 'infobip') {
            $baseUrl = trim((string) ($config['infobip']['base_url'] ?? ''));
            $apiKey = trim((string) ($config['infobip']['api_key'] ?? ''));
            $from = trim((string) ($config['infobip']['sms_from'] ?? ''));

            if ($baseUrl === '' || $apiKey === '') {
                $status['error'] = 'Configuration Infobip absente. Definir INFOBIP_BASE_URL et INFOBIP_API_KEY.';
            } elseif ($from === '') {
                $status['error'] = 'INFOBIP_SMS_FROM manquant.';
            } else {
                $status['ready'] = true;
            }

            return $status;
        }

        $status['error'] = 'SMS_PROVIDER invalide. Valeurs supportees: twilio, infobip.';
        return $status;
    }

    if ($provider === 'twilio') {
        $sid = trim((string) ($config['twilio']['account_sid'] ?? ''));
        $token = trim((string) ($config['twilio']['auth_token'] ?? ''));
        $from = trim((string) ($config['twilio']['whatsapp_from'] ?? ''));

        if ($sid === '' || $token === '') {
            $status['error'] = 'Configuration Twilio absente. Definir TWILIO_ACCOUNT_SID et TWILIO_AUTH_TOKEN.';
        } elseif ($from === '') {
            $status['error'] = 'TWILIO_WHATSAPP_FROM manquant.';
        } else {
            $status['ready'] = true;
        }

        return $status;
    }

    if ($provider === 'meta') {
        $phoneNumberId = trim((string) ($config['meta']['phone_number_id'] ?? ''));
        $accessToken = trim((string) ($config['meta']['access_token'] ?? ''));

        if ($phoneNumberId === '' || $accessToken === '') {
            $status['error'] = 'Configuration Meta WhatsApp absente. Definir WHATSAPP_META_PHONE_NUMBER_ID et WHATSAPP_META_ACCESS_TOKEN.';
        } else {
            $status['ready'] = true;
        }

        return $status;
    }

    $status['error'] = 'WHATSAPP_PROVIDER invalide. Valeurs supportees: twilio, meta.';
    return $status;
}

function getMessagingStatusSummary(?array $config = null): array
{
    $config = $config ?? [
        'default_country_code' => messagingEnv('DEFAULT_PHONE_COUNTRY_CODE', ''),
        'sms_provider' => strtolower(messagingEnv('SMS_PROVIDER', 'twilio')),
        'whatsapp_provider' => strtolower(messagingEnv('WHATSAPP_PROVIDER', 'twilio')),
        'twilio' => [
            'account_sid' => messagingEnv('TWILIO_ACCOUNT_SID', ''),
            'auth_token' => messagingEnv('TWILIO_AUTH_TOKEN', ''),
            'sms_from' => messagingEnv('TWILIO_SMS_FROM', ''),
            'whatsapp_from' => messagingEnv('TWILIO_WHATSAPP_FROM', ''),
        ],
        'meta' => [
            'graph_version' => messagingEnv('WHATSAPP_META_GRAPH_VERSION', 'v20.0'),
            'phone_number_id' => messagingEnv('WHATSAPP_META_PHONE_NUMBER_ID', ''),
            'access_token' => messagingEnv('WHATSAPP_META_ACCESS_TOKEN', ''),
        ],
        'infobip' => [
            'base_url' => rtrim(messagingEnv('INFOBIP_BASE_URL', ''), '/'),
            'api_key' => messagingEnv('INFOBIP_API_KEY', ''),
            'sms_from' => messagingEnv('INFOBIP_SMS_FROM', ''),
        ],
    ];

    return [
        'sms' => getMessagingChannelStatus('sms', $config),
        'whatsapp' => getMessagingChannelStatus('whatsapp', $config),
    ];
}

function isMessagingChannelReady(string $channel): bool
{
    $status = getMessagingChannelStatus($channel);
    return !empty($status['ready']);
}

function isTwilioChannelReady(string $channel): bool
{
    $status = getMessagingChannelStatus($channel);
    return !empty($status['ready']) && (($status['provider'] ?? '') === 'twilio');
}

function messagingHttpPost(
    string $url,
    array $headers,
    string $payload,
    ?string &$errorDetail = null
): ?array {
    $errorDetail = null;

    if (!extension_loaded('curl')) {
        $errorDetail = 'Extension curl indisponible sur le serveur.';
        return null;
    }

    $curl = curl_init($url);
    if ($curl === false) {
        $errorDetail = 'Impossible d initialiser la requete HTTP.';
        return null;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 35,
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
        $errorDetail = 'Erreur reseau: ' . $curlErr;
        return null;
    }

    $json = json_decode((string) $rawResponse, true);
    return [
        'http_code' => $httpCode,
        'raw' => (string) $rawResponse,
        'json' => is_array($json) ? $json : null,
    ];
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

    $config = getMessagingConfig();
    $status = getMessagingChannelStatus($channel, $config);
    if (($status['provider'] ?? '') !== 'twilio' || empty($status['ready'])) {
        $errorDetail = (string) ($status['error'] ?? 'Canal Twilio non disponible.');
        return false;
    }

    $normalizedPhone = normalizePhoneToE164($rawPhone, (string) ($config['default_country_code'] ?? ''));
    if ($normalizedPhone === null) {
        $errorDetail = 'Numero invalide. Utilisez un format international, ex: +243812345678.';
        return false;
    }

    $from = $channel === 'sms'
        ? trim((string) ($config['twilio']['sms_from'] ?? ''))
        : trim((string) ($config['twilio']['whatsapp_from'] ?? ''));

    if ($channel === 'sms') {
        $from = normalizePhoneToE164($from, (string) ($config['default_country_code'] ?? '')) ?? $from;
        $to = $normalizedPhone;
    } else {
        if (stripos($from, 'whatsapp:') !== 0) {
            $from = 'whatsapp:' . $from;
        }
        $to = 'whatsapp:' . $normalizedPhone;
    }

    $messageBody = trim($body);
    if ($messageBody === '') {
        $errorDetail = 'Message vide.';
        return false;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode((string) ($config['twilio']['account_sid'] ?? '')) . '/Messages.json';
    $payload = http_build_query([
        'To' => $to,
        'From' => $from,
        'Body' => $messageBody,
    ], '', '&', PHP_QUERY_RFC3986);

    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode((string) ($config['twilio']['account_sid'] ?? '') . ':' . (string) ($config['twilio']['auth_token'] ?? '')),
    ];

    $response = messagingHttpPost($url, $headers, $payload, $errorDetail);
    if (!is_array($response)) {
        return false;
    }

    $httpCode = (int) ($response['http_code'] ?? 0);
    $json = is_array($response['json']) ? $response['json'] : [];
    $providerMessageId = (string) ($json['sid'] ?? '');

    if ($httpCode >= 200 && $httpCode < 300 && $providerMessageId !== '') {
        return true;
    }

    $apiMessage = (string) ($json['message'] ?? '');
    $apiCode = (string) ($json['code'] ?? '');
    if ($apiCode !== '') {
        $apiMessage = '[Code ' . $apiCode . '] ' . $apiMessage;
    }
    if ($apiMessage === '') {
        $apiMessage = trim((string) ($response['raw'] ?? ''));
    }
    if ($apiMessage === '') {
        $apiMessage = 'Reponse vide de Twilio.';
    }

    $errorDetail = 'Echec ' . strtoupper($channel) . ' (HTTP ' . $httpCode . '): ' . $apiMessage;
    return false;
}

function sendInfobipSmsMessage(
    string $rawPhone,
    string $body,
    ?string &$errorDetail = null,
    ?string &$providerMessageId = null
): bool {
    $errorDetail = null;
    $providerMessageId = null;

    $config = getMessagingConfig();
    $status = getMessagingChannelStatus('sms', $config);
    if (($status['provider'] ?? '') !== 'infobip' || empty($status['ready'])) {
        $errorDetail = (string) ($status['error'] ?? 'Canal SMS Infobip non disponible.');
        return false;
    }

    $normalizedPhone = normalizePhoneToE164($rawPhone, (string) ($config['default_country_code'] ?? ''));
    if ($normalizedPhone === null) {
        $errorDetail = 'Numero invalide. Utilisez un format international, ex: +243812345678.';
        return false;
    }

    $messageBody = trim($body);
    if ($messageBody === '') {
        $errorDetail = 'Message vide.';
        return false;
    }

    $url = rtrim((string) ($config['infobip']['base_url'] ?? ''), '/') . '/sms/2/text/advanced';
    $payloadArray = [
        'messages' => [
            [
                'from' => (string) ($config['infobip']['sms_from'] ?? ''),
                'destinations' => [
                    ['to' => $normalizedPhone],
                ],
                'text' => $messageBody,
            ],
        ],
    ];
    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        $errorDetail = 'Impossible de preparer la charge SMS.';
        return false;
    }

    $headers = [
        'Authorization: App ' . (string) ($config['infobip']['api_key'] ?? ''),
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $response = messagingHttpPost($url, $headers, $payload, $errorDetail);
    if (!is_array($response)) {
        return false;
    }

    $httpCode = (int) ($response['http_code'] ?? 0);
    $json = is_array($response['json']) ? $response['json'] : [];

    $providerMessageId = '';
    if (isset($json['messages'][0]['messageId'])) {
        $providerMessageId = (string) $json['messages'][0]['messageId'];
    } elseif (isset($json['bulkId'])) {
        $providerMessageId = (string) $json['bulkId'];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        if ($providerMessageId === '') {
            $providerMessageId = 'INFOBIP-' . date('YmdHis');
        }
        return true;
    }

    $apiMessage = '';
    if (isset($json['requestError']['serviceException']['text'])) {
        $apiMessage = (string) $json['requestError']['serviceException']['text'];
    }
    if ($apiMessage === '' && isset($json['message'])) {
        $apiMessage = (string) $json['message'];
    }
    if ($apiMessage === '') {
        $apiMessage = trim((string) ($response['raw'] ?? ''));
    }
    if ($apiMessage === '') {
        $apiMessage = 'Reponse vide de Infobip.';
    }

    $errorDetail = 'Echec SMS (HTTP ' . $httpCode . '): ' . $apiMessage;
    return false;
}

function sendMetaWhatsAppMessage(
    string $rawPhone,
    string $body,
    ?string &$errorDetail = null,
    ?string &$providerMessageId = null
): bool {
    $errorDetail = null;
    $providerMessageId = null;

    $config = getMessagingConfig();
    $status = getMessagingChannelStatus('whatsapp', $config);
    if (($status['provider'] ?? '') !== 'meta' || empty($status['ready'])) {
        $errorDetail = (string) ($status['error'] ?? 'Canal WhatsApp Meta non disponible.');
        return false;
    }

    $normalizedPhone = normalizePhoneToE164($rawPhone, (string) ($config['default_country_code'] ?? ''));
    if ($normalizedPhone === null) {
        $errorDetail = 'Numero invalide. Utilisez un format international, ex: +243812345678.';
        return false;
    }

    $to = ltrim($normalizedPhone, '+');
    $messageBody = trim($body);
    if ($messageBody === '') {
        $errorDetail = 'Message vide.';
        return false;
    }

    $version = trim((string) ($config['meta']['graph_version'] ?? 'v20.0'));
    $phoneNumberId = trim((string) ($config['meta']['phone_number_id'] ?? ''));
    $accessToken = trim((string) ($config['meta']['access_token'] ?? ''));
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneNumberId) . '/messages';

    $payloadArray = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $messageBody,
        ],
    ];
    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        $errorDetail = 'Impossible de preparer la charge WhatsApp.';
        return false;
    }

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $response = messagingHttpPost($url, $headers, $payload, $errorDetail);
    if (!is_array($response)) {
        return false;
    }

    $httpCode = (int) ($response['http_code'] ?? 0);
    $json = is_array($response['json']) ? $response['json'] : [];
    if (isset($json['messages'][0]['id'])) {
        $providerMessageId = (string) $json['messages'][0]['id'];
    }

    if ($httpCode >= 200 && $httpCode < 300 && $providerMessageId !== '') {
        return true;
    }

    $apiMessage = '';
    if (isset($json['error']['message'])) {
        $apiMessage = (string) $json['error']['message'];
        $apiCode = (string) ($json['error']['code'] ?? '');
        if ($apiCode !== '') {
            $apiMessage = '[Code ' . $apiCode . '] ' . $apiMessage;
        }
    }
    if ($apiMessage === '') {
        $apiMessage = trim((string) ($response['raw'] ?? ''));
    }
    if ($apiMessage === '') {
        $apiMessage = 'Reponse vide de Meta Cloud API.';
    }

    $errorDetail = 'Echec WhatsApp (HTTP ' . $httpCode . '): ' . $apiMessage;
    return false;
}

function sendConfiguredSmsMessage(
    string $rawPhone,
    string $body,
    ?string &$errorDetail = null,
    ?string &$providerMessageId = null
): bool {
    $status = getMessagingChannelStatus('sms');
    $provider = (string) ($status['provider'] ?? '');

    if ($provider === 'twilio') {
        return sendTwilioChannelMessage('sms', $rawPhone, $body, $errorDetail, $providerMessageId);
    }
    if ($provider === 'infobip') {
        return sendInfobipSmsMessage($rawPhone, $body, $errorDetail, $providerMessageId);
    }

    $errorDetail = (string) ($status['error'] ?? 'Canal SMS non disponible.');
    return false;
}

function sendConfiguredWhatsAppMessage(
    string $rawPhone,
    string $body,
    ?string &$errorDetail = null,
    ?string &$providerMessageId = null
): bool {
    $status = getMessagingChannelStatus('whatsapp');
    $provider = (string) ($status['provider'] ?? '');

    if ($provider === 'twilio') {
        return sendTwilioChannelMessage('whatsapp', $rawPhone, $body, $errorDetail, $providerMessageId);
    }
    if ($provider === 'meta') {
        return sendMetaWhatsAppMessage($rawPhone, $body, $errorDetail, $providerMessageId);
    }

    $errorDetail = (string) ($status['error'] ?? 'Canal WhatsApp non disponible.');
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
    return sendConfiguredSmsMessage($phone, $text, $errorDetail, $providerMessageId);
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
    return sendConfiguredWhatsAppMessage($phone, $text, $errorDetail, $providerMessageId);
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
