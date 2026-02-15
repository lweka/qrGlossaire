<?php
require_once __DIR__ . '/../config/constants.php';

function creditIdentifierIsSafe(string $identifier): bool
{
    return preg_match('/^[a-zA-Z0-9_]+$/', $identifier) === 1;
}

function creditTableExists(PDO $pdo, string $tableName, bool $refresh = false): bool
{
    static $cache = [];
    $cacheKey = strtolower($tableName);
    if (!$refresh && array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!creditIdentifierIsSafe($tableName)) {
        $cache[$cacheKey] = false;
        return false;
    }

    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $tableName]);
        $cache[$cacheKey] = (bool) $stmt->fetchColumn();
    } catch (Throwable $throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function creditColumnExists(PDO $pdo, string $tableName, string $columnName, bool $refresh = false): bool
{
    static $cache = [];
    $cacheKey = strtolower($tableName . '.' . $columnName);
    if (!$refresh && array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!creditIdentifierIsSafe($tableName) || !creditIdentifierIsSafe($columnName)) {
        $cache[$cacheKey] = false;
        return false;
    }

    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE :column_name");
        $stmt->execute(['column_name' => $columnName]);
        $cache[$cacheKey] = (bool) $stmt->fetch();
    } catch (Throwable $throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function isCreditQuotaEnabled(PDO $pdo, bool $refresh = false): bool
{
    return creditColumnExists($pdo, 'users', 'invitation_credit_total', $refresh)
        && creditColumnExists($pdo, 'users', 'event_credit_total', $refresh);
}

function isCreditRequestModuleEnabled(PDO $pdo, bool $refresh = false): bool
{
    return isCreditQuotaEnabled($pdo, $refresh) && creditTableExists($pdo, 'credit_requests', $refresh);
}

function isCommunicationLogModuleEnabled(PDO $pdo, bool $refresh = false): bool
{
    return creditTableExists($pdo, 'communication_logs', $refresh);
}

function ensureCreditSystemSchema(PDO $pdo): bool
{
    static $attempted = false;
    static $ready = false;

    if ($attempted) {
        return $ready;
    }
    $attempted = true;

    try {
        if (!creditColumnExists($pdo, 'users', 'invitation_credit_total')) {
            try {
                $pdo->exec('ALTER TABLE users ADD COLUMN invitation_credit_total INT NOT NULL DEFAULT 0');
            } catch (Throwable $throwable) {
            }
        }

        if (!creditColumnExists($pdo, 'users', 'event_credit_total')) {
            try {
                $pdo->exec('ALTER TABLE users ADD COLUMN event_credit_total INT NOT NULL DEFAULT 0');
            } catch (Throwable $throwable) {
            }
        }

        if (!creditTableExists($pdo, 'credit_requests')) {
            try {
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
            } catch (Throwable $throwable) {
            }
        }

        if (!creditTableExists($pdo, 'communication_logs')) {
            try {
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
            } catch (Throwable $throwable) {
            }
        }
    } catch (Throwable $throwable) {
    }

    $ready = isCreditRequestModuleEnabled($pdo, true) && isCommunicationLogModuleEnabled($pdo, true);
    return $ready;
}

function invitationUnitPriceUsd(): float
{
    return defined('INVITATION_UNIT_PRICE_USD') ? (float) INVITATION_UNIT_PRICE_USD : 0.30;
}

function getUserCreditSummary(PDO $pdo, int $userId): array
{
    ensureCreditSystemSchema($pdo);

    $summary = [
        'invitation_total' => 0,
        'invitation_used' => 0,
        'invitation_remaining' => 0,
        'event_total' => 0,
        'event_used' => 0,
        'event_remaining' => 0,
        'credit_controls_enabled' => isCreditQuotaEnabled($pdo),
        'request_module_enabled' => isCreditRequestModuleEnabled($pdo),
    ];

    $userExists = false;
    try {
        $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = :user_id LIMIT 1');
        $userStmt->execute(['user_id' => $userId]);
        $userExists = (bool) $userStmt->fetch();
    } catch (Throwable $throwable) {
        return $summary;
    }

    if (!$userExists) {
        return $summary;
    }

    try {
        $invitationUsedStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM guests g
             INNER JOIN events ev ON ev.id = g.event_id
             WHERE ev.user_id = :user_id"
        );
        $invitationUsedStmt->execute(['user_id' => $userId]);
        $summary['invitation_used'] = (int) $invitationUsedStmt->fetchColumn();
    } catch (Throwable $throwable) {
        $summary['invitation_used'] = 0;
    }

    try {
        $eventUsedStmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE user_id = :user_id');
        $eventUsedStmt->execute(['user_id' => $userId]);
        $summary['event_used'] = (int) $eventUsedStmt->fetchColumn();
    } catch (Throwable $throwable) {
        $summary['event_used'] = 0;
    }

    if (!empty($summary['credit_controls_enabled'])) {
        try {
            $totalsStmt = $pdo->prepare(
                'SELECT invitation_credit_total, event_credit_total
                 FROM users
                 WHERE id = :user_id
                 LIMIT 1'
            );
            $totalsStmt->execute(['user_id' => $userId]);
            $totals = $totalsStmt->fetch();
            if ($totals) {
                $summary['invitation_total'] = (int) ($totals['invitation_credit_total'] ?? 0);
                $summary['event_total'] = (int) ($totals['event_credit_total'] ?? 0);
            }
        } catch (Throwable $throwable) {
            $summary['invitation_total'] = 0;
            $summary['event_total'] = 0;
        }
    }

    $summary['invitation_remaining'] = max(0, $summary['invitation_total'] - $summary['invitation_used']);
    $summary['event_remaining'] = max(0, $summary['event_total'] - $summary['event_used']);

    return $summary;
}

function getPendingCreditRequestForUser(PDO $pdo, int $userId): ?array
{
    ensureCreditSystemSchema($pdo);
    if (!isCreditRequestModuleEnabled($pdo)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM credit_requests
             WHERE user_id = :user_id AND status = 'pending'
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $request = $stmt->fetch();
    } catch (Throwable $throwable) {
        return null;
    }

    return $request ?: null;
}

function getLatestProcessedCreditRequestForUser(PDO $pdo, int $userId): ?array
{
    ensureCreditSystemSchema($pdo);
    if (!isCreditRequestModuleEnabled($pdo)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM credit_requests
             WHERE user_id = :user_id AND status IN ('approved', 'rejected')
             ORDER BY updated_at DESC
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $request = $stmt->fetch();
    } catch (Throwable $throwable) {
        return null;
    }

    return $request ?: null;
}

function grantCreditsToUser(PDO $pdo, int $userId, int $invitationCredits, int $eventCredits): bool
{
    ensureCreditSystemSchema($pdo);
    if (!isCreditQuotaEnabled($pdo)) {
        return false;
    }

    $invitationCredits = max(0, $invitationCredits);
    $eventCredits = max(0, $eventCredits);

    if ($invitationCredits === 0 && $eventCredits === 0) {
        return true;
    }

    try {
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
    } catch (Throwable $throwable) {
        return false;
    }

    return true;
}

function createCreditIncreaseRequest(PDO $pdo, int $userId, int $requestedInvitationCredits, int $requestedEventCredits, string $requestNote = ''): array
{
    ensureCreditSystemSchema($pdo);
    if (!isCreditRequestModuleEnabled($pdo)) {
        return [
            'ok' => false,
            'message' => 'Module credits non initialise. Lancez php scripts/migrate_credit_system.php puis reessayez.',
            'request_id' => null,
        ];
    }

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

    try {
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
    } catch (Throwable $throwable) {
        return [
            'ok' => false,
            'message' => 'Erreur SQL lors de la creation de la demande: ' . $throwable->getMessage(),
            'request_id' => null,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Demande enregistree avec succes.',
        'request_id' => (int) $pdo->lastInsertId(),
    ];
}

function getPendingCreditRequests(PDO $pdo): array
{
    ensureCreditSystemSchema($pdo);
    if (!isCreditRequestModuleEnabled($pdo)) {
        return [];
    }

    try {
        $stmt = $pdo->query(
            "SELECT cr.*, u.full_name, u.email
             FROM credit_requests cr
             INNER JOIN users u ON u.id = cr.user_id
             WHERE cr.status = 'pending'
             ORDER BY cr.created_at ASC"
        );
        return $stmt->fetchAll();
    } catch (Throwable $throwable) {
        return [];
    }
}

function approveCreditRequestById(PDO $pdo, int $requestId, int $adminId, string $adminNote = ''): array
{
    ensureCreditSystemSchema($pdo);
    if (!isCreditRequestModuleEnabled($pdo)) {
        return ['ok' => false, 'message' => 'Module credits non initialise. Lancez php scripts/migrate_credit_system.php.'];
    }

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
    if (!isCreditRequestModuleEnabled($pdo)) {
        return ['ok' => false, 'message' => 'Module credits non initialise. Lancez php scripts/migrate_credit_system.php.'];
    }

    try {
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
    } catch (Throwable $throwable) {
        return ['ok' => false, 'message' => 'Erreur lors du rejet: ' . $throwable->getMessage()];
    }

    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Demande introuvable ou deja traitee.'];
    }

    return ['ok' => true, 'message' => 'Demande rejetee.'];
}
