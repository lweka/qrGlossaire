<?php
require_once __DIR__ . '/../config/constants.php';

function ensureCreditSystemSchema(PDO $pdo): void
{
    static $schemaChecked = false;
    if ($schemaChecked) {
        return;
    }
    $schemaChecked = true;

    $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        return;
    }

    $columnExists = static function (string $table, string $column) use ($pdo, $databaseName): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'schema' => $databaseName,
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    };

    $tableExists = static function (string $table) use ($pdo, $databaseName): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name'
        );
        $stmt->execute([
            'schema' => $databaseName,
            'table_name' => $table,
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    };

    if (!$columnExists('users', 'invitation_credit_total')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN invitation_credit_total INT NOT NULL DEFAULT 0');
    }
    if (!$columnExists('users', 'event_credit_total')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN event_credit_total INT NOT NULL DEFAULT 0');
    }

    if (!$tableExists('credit_requests')) {
        $pdo->exec(
            "CREATE TABLE credit_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                requested_invitation_credits INT NOT NULL DEFAULT 0,
                requested_event_credits INT NOT NULL DEFAULT 0,
                unit_price_usd DECIMAL(10,2) NOT NULL DEFAULT 0.30,
                amount_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                request_note TEXT NULL,
                admin_note TEXT NULL,
                approved_by_admin_id INT NULL,
                approved_at DATETIME NULL,
                rejected_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_credit_requests_user_status (user_id, status),
                INDEX idx_credit_requests_status (status),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    if (!$tableExists('communication_logs')) {
        $pdo->exec(
            "CREATE TABLE communication_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                event_id INT NULL,
                channel ENUM('email', 'sms', 'whatsapp', 'manual') NOT NULL DEFAULT 'email',
                recipient_scope ENUM('all', 'pending', 'confirmed', 'declined') NOT NULL DEFAULT 'all',
                message_text TEXT NOT NULL,
                recipient_count INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_communication_logs_user_created (user_id, created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

function invitationUnitPriceUsd(): float
{
    return defined('INVITATION_UNIT_PRICE_USD') ? (float) INVITATION_UNIT_PRICE_USD : 0.30;
}

function getUserCreditSummary(PDO $pdo, int $userId): array
{
    ensureCreditSystemSchema($pdo);

    $stmt = $pdo->prepare(
        "SELECT
            u.invitation_credit_total,
            u.event_credit_total,
            (SELECT COUNT(*)
             FROM guests g
             INNER JOIN events ev ON ev.id = g.event_id
             WHERE ev.user_id = u.id) AS invitations_used,
            (SELECT COUNT(*)
             FROM events ev2
             WHERE ev2.user_id = u.id) AS events_used
         FROM users u
         WHERE u.id = :user_id
         LIMIT 1"
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return [
            'invitation_total' => 0,
            'invitation_used' => 0,
            'invitation_remaining' => 0,
            'event_total' => 0,
            'event_used' => 0,
            'event_remaining' => 0,
        ];
    }

    $invitationTotal = (int) ($row['invitation_credit_total'] ?? 0);
    $invitationUsed = (int) ($row['invitations_used'] ?? 0);
    $eventTotal = (int) ($row['event_credit_total'] ?? 0);
    $eventUsed = (int) ($row['events_used'] ?? 0);

    return [
        'invitation_total' => $invitationTotal,
        'invitation_used' => $invitationUsed,
        'invitation_remaining' => max(0, $invitationTotal - $invitationUsed),
        'event_total' => $eventTotal,
        'event_used' => $eventUsed,
        'event_remaining' => max(0, $eventTotal - $eventUsed),
    ];
}

function getPendingCreditRequestForUser(PDO $pdo, int $userId): ?array
{
    ensureCreditSystemSchema($pdo);

    $stmt = $pdo->prepare(
        "SELECT *
         FROM credit_requests
         WHERE user_id = :user_id AND status = 'pending'
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $stmt->execute(['user_id' => $userId]);
    $request = $stmt->fetch();

    return $request ?: null;
}

function getLatestProcessedCreditRequestForUser(PDO $pdo, int $userId): ?array
{
    ensureCreditSystemSchema($pdo);

    $stmt = $pdo->prepare(
        "SELECT *
         FROM credit_requests
         WHERE user_id = :user_id AND status IN ('approved', 'rejected')
         ORDER BY updated_at DESC
         LIMIT 1"
    );
    $stmt->execute(['user_id' => $userId]);
    $request = $stmt->fetch();

    return $request ?: null;
}

function grantCreditsToUser(PDO $pdo, int $userId, int $invitationCredits, int $eventCredits): void
{
    ensureCreditSystemSchema($pdo);

    $invitationCredits = max(0, $invitationCredits);
    $eventCredits = max(0, $eventCredits);

    if ($invitationCredits === 0 && $eventCredits === 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE users
         SET invitation_credit_total = invitation_credit_total + :invitation_credits,
             event_credit_total = event_credit_total + :event_credits
         WHERE id = :id'
    );
    $stmt->execute([
        'invitation_credits' => $invitationCredits,
        'event_credits' => $eventCredits,
        'id' => $userId,
    ]);
}

function createCreditIncreaseRequest(PDO $pdo, int $userId, int $requestedInvitationCredits, int $requestedEventCredits, string $requestNote = ''): array
{
    ensureCreditSystemSchema($pdo);

    $requestedInvitationCredits = max(0, $requestedInvitationCredits);
    $requestedEventCredits = max(0, $requestedEventCredits);

    if ($requestedInvitationCredits <= 0 && $requestedEventCredits <= 0) {
        return [
            'ok' => false,
            'message' => 'Indiquez au moins un credit a ajouter.',
            'request_id' => null,
        ];
    }

    $pendingRequest = getPendingCreditRequestForUser($pdo, $userId);
    if ($pendingRequest) {
        return [
            'ok' => false,
            'message' => 'Une demande d augmentation est deja en attente.',
            'request_id' => (int) $pendingRequest['id'],
        ];
    }

    $unitPrice = invitationUnitPriceUsd();
    $amount = round($requestedInvitationCredits * $unitPrice, 2);

    $stmt = $pdo->prepare(
        'INSERT INTO credit_requests (user_id, requested_invitation_credits, requested_event_credits, unit_price_usd, amount_usd, request_note)
         VALUES (:user_id, :requested_invitation_credits, :requested_event_credits, :unit_price_usd, :amount_usd, :request_note)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'requested_invitation_credits' => $requestedInvitationCredits,
        'requested_event_credits' => $requestedEventCredits,
        'unit_price_usd' => $unitPrice,
        'amount_usd' => $amount,
        'request_note' => trim($requestNote),
    ]);

    return [
        'ok' => true,
        'message' => 'Demande enregistree avec succes.',
        'request_id' => (int) $pdo->lastInsertId(),
    ];
}

function getPendingCreditRequests(PDO $pdo): array
{
    ensureCreditSystemSchema($pdo);

    $stmt = $pdo->query(
        "SELECT cr.*, u.full_name, u.email
         FROM credit_requests cr
         INNER JOIN users u ON u.id = cr.user_id
         WHERE cr.status = 'pending'
         ORDER BY cr.created_at ASC"
    );

    return $stmt->fetchAll();
}

function approveCreditRequestById(PDO $pdo, int $requestId, int $adminId, string $adminNote = ''): array
{
    ensureCreditSystemSchema($pdo);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM credit_requests WHERE id = :id AND status = \'pending\' LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Demande introuvable ou deja traitee.'];
        }

        $requestedInvitationCredits = max(0, (int) ($request['requested_invitation_credits'] ?? 0));
        $requestedEventCredits = max(0, (int) ($request['requested_event_credits'] ?? 0));

        $updateUser = $pdo->prepare(
            'UPDATE users
             SET invitation_credit_total = invitation_credit_total + :invitation_credits,
                 event_credit_total = event_credit_total + :event_credits
             WHERE id = :user_id'
        );
        $updateUser->execute([
            'invitation_credits' => $requestedInvitationCredits,
            'event_credits' => $requestedEventCredits,
            'user_id' => (int) $request['user_id'],
        ]);

        $updateRequest = $pdo->prepare(
            "UPDATE credit_requests
             SET status = 'approved',
                 admin_note = :admin_note,
                 approved_by_admin_id = :admin_id,
                 approved_at = NOW(),
                 rejected_at = NULL
             WHERE id = :id"
        );
        $updateRequest->execute([
            'admin_note' => trim($adminNote),
            'admin_id' => $adminId,
            'id' => $requestId,
        ]);

        $pdo->commit();

        return ['ok' => true, 'message' => 'Demande approuvee et credits ajoutes.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Erreur lors de l approbation: ' . $exception->getMessage()];
    }
}

function rejectCreditRequestById(PDO $pdo, int $requestId, int $adminId, string $adminNote = ''): array
{
    ensureCreditSystemSchema($pdo);

    $stmt = $pdo->prepare(
        "UPDATE credit_requests
         SET status = 'rejected',
             admin_note = :admin_note,
             approved_by_admin_id = :admin_id,
             rejected_at = NOW(),
             approved_at = NULL
         WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute([
        'admin_note' => trim($adminNote),
        'admin_id' => $adminId,
        'id' => $requestId,
    ]);

    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Demande introuvable ou deja traitee.'];
    }

    return ['ok' => true, 'message' => 'Demande rejetee.'];
}
