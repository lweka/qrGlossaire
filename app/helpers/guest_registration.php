<?php
require_once __DIR__ . '/credits.php';

function guestRegistrationColumnsReady(PDO $pdo): bool
{
    return creditColumnExists($pdo, 'events', 'public_registration_token')
        && creditColumnExists($pdo, 'events', 'public_registration_enabled');
}

function ensureGuestRegistrationSchema(PDO $pdo): bool
{
    static $schemaChecked = false;
    static $schemaReady = false;

    if ($schemaChecked) {
        return $schemaReady;
    }
    $schemaChecked = true;

    try {
        if (!creditColumnExists($pdo, 'events', 'public_registration_token')) {
            $pdo->exec('ALTER TABLE events ADD COLUMN public_registration_token VARCHAR(64) NULL DEFAULT NULL');
        }
    } catch (Throwable $throwable) {
    }

    try {
        if (!creditColumnExists($pdo, 'events', 'public_registration_enabled')) {
            $pdo->exec('ALTER TABLE events ADD COLUMN public_registration_enabled TINYINT(1) NOT NULL DEFAULT 1');
        }
    } catch (Throwable $throwable) {
    }

    if (guestRegistrationColumnsReady($pdo)) {
        try {
            $indexStmt = $pdo->query("SHOW INDEX FROM events WHERE Key_name = 'uniq_events_public_registration_token'");
            $hasIndex = (bool) ($indexStmt ? $indexStmt->fetch() : false);
            if (!$hasIndex) {
                $pdo->exec('ALTER TABLE events ADD UNIQUE INDEX uniq_events_public_registration_token (public_registration_token)');
            }
        } catch (Throwable $throwable) {
        }
    }

    $schemaReady = guestRegistrationColumnsReady($pdo);
    return $schemaReady;
}

function normalizeGuestRegistrationToken(string $rawToken): string
{
    $token = strtolower(trim($rawToken));
    $token = preg_replace('/[^a-f0-9]/', '', $token);
    if (!is_string($token) || $token === '' || strlen($token) < 16) {
        return '';
    }

    return substr($token, 0, 64);
}

function generateGuestRegistrationToken(): string
{
    return bin2hex(random_bytes(20));
}

function getOrCreateGuestRegistrationToken(PDO $pdo, int $eventId, int $userId): string
{
    if ($eventId <= 0 || $userId <= 0) {
        return '';
    }
    if (!ensureGuestRegistrationSchema($pdo)) {
        return '';
    }

    $eventStmt = $pdo->prepare(
        'SELECT public_registration_token
         FROM events
         WHERE id = :event_id AND user_id = :user_id
         LIMIT 1'
    );
    $eventStmt->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
    ]);
    $event = $eventStmt->fetch();
    if (!$event) {
        return '';
    }

    $existingToken = normalizeGuestRegistrationToken((string) ($event['public_registration_token'] ?? ''));
    if ($existingToken !== '') {
        return $existingToken;
    }

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $token = generateGuestRegistrationToken();
        try {
            $updateStmt = $pdo->prepare(
                'UPDATE events
                 SET public_registration_token = :token
                 WHERE id = :event_id
                   AND user_id = :user_id
                   AND (public_registration_token IS NULL OR public_registration_token = \'\')'
            );
            $updateStmt->execute([
                'token' => $token,
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);
        } catch (Throwable $throwable) {
            continue;
        }

        $refreshStmt = $pdo->prepare(
            'SELECT public_registration_token
             FROM events
             WHERE id = :event_id AND user_id = :user_id
             LIMIT 1'
        );
        $refreshStmt->execute([
            'event_id' => $eventId,
            'user_id' => $userId,
        ]);
        $refreshed = $refreshStmt->fetch();
        $resolvedToken = normalizeGuestRegistrationToken((string) ($refreshed['public_registration_token'] ?? ''));
        if ($resolvedToken !== '') {
            return $resolvedToken;
        }
    }

    return '';
}

function findEventByGuestRegistrationToken(PDO $pdo, string $token): ?array
{
    $token = normalizeGuestRegistrationToken($token);
    if ($token === '') {
        return null;
    }
    if (!ensureGuestRegistrationSchema($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT
            e.id,
            e.user_id,
            e.title,
            e.event_type,
            e.event_date,
            e.location,
            e.is_active,
            e.public_registration_enabled,
            u.full_name AS organizer_name
         FROM events e
         INNER JOIN users u ON u.id = e.user_id
         WHERE e.public_registration_token = :token
         LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $event = $stmt->fetch();

    return $event ?: null;
}

function buildGuestReferenceCode(): string
{
    return 'INV-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
}

function createGuestThroughRegistrationLink(PDO $pdo, int $eventId, int $userId, string $fullName, string $email = '', string $phone = ''): array
{
    $result = [
        'ok' => false,
        'message' => 'Impossible de creer l invitation.',
        'guest_id' => 0,
        'guest_code' => '',
        'credit_control_enabled' => false,
        'invitation_remaining_after' => null,
    ];

    $fullName = trim($fullName);
    $email = trim($email);
    $phone = trim($phone);

    if ($eventId <= 0 || $userId <= 0 || $fullName === '') {
        $result['message'] = 'Veuillez renseigner votre nom.';
        return $result;
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['message'] = 'Adresse email invalide.';
        return $result;
    }

    $guestCustomAnswersEnabled = creditColumnExists($pdo, 'guests', 'custom_answers');

    try {
        $pdo->beginTransaction();

        $eventStmt = $pdo->prepare(
            'SELECT id, is_active, public_registration_enabled
             FROM events
             WHERE id = :event_id AND user_id = :user_id
             LIMIT 1
             FOR UPDATE'
        );
        $eventStmt->execute([
            'event_id' => $eventId,
            'user_id' => $userId,
        ]);
        $event = $eventStmt->fetch();

        if (!$event) {
            $pdo->rollBack();
            $result['message'] = 'Cet evenement est introuvable.';
            return $result;
        }
        if ((int) ($event['is_active'] ?? 0) !== 1) {
            $pdo->rollBack();
            $result['message'] = 'Les inscriptions sont fermees pour cet evenement.';
            return $result;
        }
        if ((int) ($event['public_registration_enabled'] ?? 0) !== 1) {
            $pdo->rollBack();
            $result['message'] = 'Ce lien d inscription est actuellement desactive.';
            return $result;
        }

        $creditControlEnabled = isCreditQuotaEnabled($pdo);
        $result['credit_control_enabled'] = $creditControlEnabled;

        if ($creditControlEnabled) {
            $userStmt = $pdo->prepare(
                'SELECT invitation_credit_total
                 FROM users
                 WHERE id = :user_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $userStmt->execute(['user_id' => $userId]);
            $user = $userStmt->fetch();
            if (!$user) {
                $pdo->rollBack();
                $result['message'] = 'Compte organisateur introuvable.';
                return $result;
            }

            $totalCredits = max(0, (int) ($user['invitation_credit_total'] ?? 0));
            $usedStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM guests g
                 INNER JOIN events e ON e.id = g.event_id
                 WHERE e.user_id = :user_id'
            );
            $usedStmt->execute(['user_id' => $userId]);
            $usedCredits = max(0, (int) $usedStmt->fetchColumn());

            if ($usedCredits >= $totalCredits) {
                $pdo->rollBack();
                $result['message'] = 'Limite atteinte: plus aucun credit invitation disponible pour ce lien.';
                $result['invitation_remaining_after'] = 0;
                return $result;
            }
        }

        if ($email !== '') {
            $emailDupStmt = $pdo->prepare(
                'SELECT id
                 FROM guests
                 WHERE event_id = :event_id AND email = :email
                 LIMIT 1'
            );
            $emailDupStmt->execute([
                'event_id' => $eventId,
                'email' => $email,
            ]);
            if ($emailDupStmt->fetch()) {
                $pdo->rollBack();
                $result['message'] = 'Une invitation existe deja pour cet email sur cet evenement.';
                return $result;
            }
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $guestCode = buildGuestReferenceCode();
            try {
                if ($guestCustomAnswersEnabled) {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO guests (event_id, guest_code, full_name, email, phone, custom_answers)
                         VALUES (:event_id, :guest_code, :full_name, :email, :phone, :custom_answers)'
                    );
                    $insertStmt->execute([
                        'event_id' => $eventId,
                        'guest_code' => $guestCode,
                        'full_name' => $fullName,
                        'email' => $email !== '' ? $email : null,
                        'phone' => $phone !== '' ? $phone : null,
                        'custom_answers' => json_encode(new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                } else {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO guests (event_id, guest_code, full_name, email, phone)
                         VALUES (:event_id, :guest_code, :full_name, :email, :phone)'
                    );
                    $insertStmt->execute([
                        'event_id' => $eventId,
                        'guest_code' => $guestCode,
                        'full_name' => $fullName,
                        'email' => $email !== '' ? $email : null,
                        'phone' => $phone !== '' ? $phone : null,
                    ]);
                }

                $guestId = (int) $pdo->lastInsertId();
                $pdo->commit();

                $remainingAfter = null;
                if ($result['credit_control_enabled']) {
                    $summary = getUserCreditSummary($pdo, $userId);
                    $remainingAfter = (int) ($summary['invitation_remaining'] ?? 0);
                }

                return [
                    'ok' => true,
                    'message' => 'Invitation creee avec succes.',
                    'guest_id' => $guestId,
                    'guest_code' => $guestCode,
                    'credit_control_enabled' => $result['credit_control_enabled'],
                    'invitation_remaining_after' => $remainingAfter,
                ];
            } catch (Throwable $throwable) {
                $duplicate = false;
                if ($throwable instanceof PDOException) {
                    $driverCode = (int) ($throwable->errorInfo[1] ?? 0);
                    if ($driverCode === 1062) {
                        $duplicate = true;
                    }
                }
                if ($duplicate) {
                    continue;
                }

                throw $throwable;
            }
        }

        $pdo->rollBack();
        $result['message'] = 'Impossible de generer un code invitation unique. Reessayez.';
        return $result;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $result['message'] = 'Erreur technique lors de la creation de l invitation.';
        return $result;
    }
}
