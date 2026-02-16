<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../app/helpers/credits.php';
requireRole('admin');
$creditSchemaReady = ensureCreditSystemSchema($pdo);
$creditQuotaEnabled = isCreditQuotaEnabled($pdo);
$creditRequestModuleEnabled = isCreditRequestModuleEnabled($pdo);

$message = null;
$messageType = 'success';
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de sécurité invalide.';
        $messageType = 'error';
    } else {
        $action = sanitizeInput($_POST['action'] ?? '');
        $userId = (int) ($_POST['id'] ?? 0);

        if ($action === 'suspend' && $userId > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $message = 'Utilisateur suspendu.';
        } elseif ($action === 'activate' && $userId > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active', unique_link_token = NULL, token_expiry = NULL WHERE id = :id AND payment_confirmed = 1");
            $stmt->execute(['id' => $userId]);
            $message = 'Compte active manuellement si paiement confirme.';
        } elseif ($action === 'confirm-payment' && $userId > 0) {
            $invitationCredits = max(0, (int) ($_POST['invitation_credits'] ?? DEFAULT_INITIAL_INVITATION_CREDITS));
            $eventCredits = max(0, (int) ($_POST['event_credits'] ?? DEFAULT_INITIAL_EVENT_CREDITS));

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE users SET payment_confirmed = 1, payment_date = CURDATE() WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                $creditsGranted = grantCreditsToUser($pdo, $userId, $invitationCredits, $eventCredits);
                $pdo->commit();
                if ($creditsGranted) {
                    $message = 'Paiement confirmé et crédits ajoutés (' . $invitationCredits . ' invitations, ' . $eventCredits . ' événement(s)).';
                } else {
                    $message = 'Paiement confirmé. Module crédits non initialisé: exécutez php scripts/migrate_credit_system.php.';
                    $messageType = 'warning';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'Erreur lors de la confirmation du paiement: ' . $exception->getMessage();
                $messageType = 'error';
            }
        } elseif ($action === 'approve-credit-request') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $adminNote = sanitizeInput($_POST['admin_note'] ?? '');
            $result = approveCreditRequestById($pdo, $requestId, $adminId, $adminNote);
            $message = $result['message'] ?? 'Action terminee.';
            $messageType = !empty($result['ok']) ? 'success' : 'error';
        } elseif ($action === 'reject-credit-request') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $adminNote = sanitizeInput($_POST['admin_note'] ?? '');
            $result = rejectCreditRequestById($pdo, $requestId, $adminId, $adminNote);
            $message = $result['message'] ?? 'Action terminee.';
            $messageType = !empty($result['ok']) ? 'success' : 'error';
        }
    }
}

$users = $pdo->query(
    "SELECT
        u.id,
        u.full_name,
        u.email,
        u.status,
        u.payment_confirmed
     FROM users u
     ORDER BY u.created_at DESC"
)->fetchAll();

$pendingCreditRequests = getPendingCreditRequests($pdo);
$userCreditSummary = [];
foreach ($users as $user) {
    $userCreditSummary[(int) $user['id']] = getUserCreditSummary($pdo, (int) $user['id']);
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Gestion des utilisateurs et crédits</h2>
    </div>
    <div style="margin: 0 0 18px;">
        <a class="button ghost" href="<?= $baseUrl; ?>/admin/dashboard">Retour au dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="card" style="margin-bottom: 18px;">
            <?php
            $messageColor = '#166534';
            if ($messageType === 'error') {
                $messageColor = '#dc2626';
            } elseif ($messageType === 'warning') {
                $messageColor = '#92400e';
            }
            ?>
            <p style="color: <?= $messageColor; ?>;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$creditSchemaReady || !$creditQuotaEnabled || !$creditRequestModuleEnabled): ?>
        <div class="card" style="margin-bottom: 18px;">
            <p style="color: #92400e;">
                Le module crédits n'est pas entièrement initialisé sur ce serveur.
                Lancez <code>php scripts/migrate_credit_system.php</code> puis actualisez.
            </p>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 22px;">
        <h3 style="margin-bottom: 10px;">Demandes d'augmentation en attente</h3>
        <p style="margin-bottom: 14px; color: var(--text-mid);">
            Prix de reference: $<?= number_format(invitationUnitPriceUsd(), 2); ?> par invitation.
        </p>
        <table class="table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Demande</th>
                    <th>Montant</th>
                    <th>Note client</th>
                    <th>Action admin</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$creditRequestModuleEnabled): ?>
                    <tr>
                        <td colspan="5">Module de demandes indisponible (migration requise).</td>
                    </tr>
                <?php elseif (empty($pendingCreditRequests)): ?>
                    <tr>
                        <td colspan="5">Aucune demande en attente.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingCreditRequests as $request): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars((string) ($request['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
                                <small><?= htmlspecialchars((string) ($request['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                            </td>
                            <td>
                                +<?= (int) ($request['requested_invitation_credits'] ?? 0); ?> invitations<br>
                                +<?= (int) ($request['requested_event_credits'] ?? 0); ?> crédit(s) événement
                            </td>
                            <td>$<?= number_format((float) ($request['amount_usd'] ?? 0), 2); ?></td>
                            <td><?= htmlspecialchars((string) ($request['request_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <form method="post" style="display: inline-block; margin-right: 6px;">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                    <input type="hidden" name="action" value="approve-credit-request">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id']; ?>">
                                    <input type="text" name="admin_note" placeholder="Note approbation" style="margin-bottom: 8px;">
                                    <button class="button primary" type="submit">Approuver</button>
                                </form>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                    <input type="hidden" name="action" value="reject-credit-request">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id']; ?>">
                                    <input type="text" name="admin_note" placeholder="Motif rejet" style="margin-bottom: 8px;">
                                    <button class="button ghost" type="submit">Rejeter</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 10px;">Comptes clients</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Statut</th>
                    <th>Paiement</th>
                    <th>Invitations</th>
                    <th>Événements</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7">Aucun utilisateur pour le moment.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $summary = $userCreditSummary[(int) $user['id']] ?? [
                            'invitation_total' => 0,
                            'invitation_used' => 0,
                            'invitation_remaining' => 0,
                            'event_total' => 0,
                            'event_used' => 0,
                            'event_remaining' => 0,
                            'credit_controls_enabled' => false,
                        ];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($user['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int) ($user['payment_confirmed'] ?? 0) === 1 ? 'Oui' : 'Non'; ?></td>
                            <td>
                                <?php if (!empty($summary['credit_controls_enabled'])): ?>
                                    <?= (int) $summary['invitation_remaining']; ?> / <?= (int) $summary['invitation_total']; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($summary['credit_controls_enabled'])): ?>
                                    <?= (int) $summary['event_remaining']; ?> / <?= (int) $summary['event_total']; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline-block; margin: 0 8px 8px 0;">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                    <input type="hidden" name="action" value="confirm-payment">
                                    <input type="hidden" name="id" value="<?= (int) $user['id']; ?>">
                                    <input type="number" name="invitation_credits" min="0" value="<?= DEFAULT_INITIAL_INVITATION_CREDITS; ?>" style="width: 110px;" title="Crédits invitations" <?= $creditQuotaEnabled ? '' : 'disabled'; ?>>
                                    <input type="number" name="event_credits" min="0" value="<?= DEFAULT_INITIAL_EVENT_CREDITS; ?>" style="width: 90px;" title="Crédits événement" <?= $creditQuotaEnabled ? '' : 'disabled'; ?>>
                                    <button class="button ghost" type="submit"><?= $creditQuotaEnabled ? 'Paiement + crédits' : 'Paiement'; ?></button>
                                </form>

                                <form method="post" style="display: inline-block; margin-right: 6px;">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="id" value="<?= (int) $user['id']; ?>">
                                    <button class="button ghost" type="submit">Activer</button>
                                </form>

                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                    <input type="hidden" name="action" value="suspend">
                                    <input type="hidden" name="id" value="<?= (int) $user['id']; ?>">
                                    <button class="button ghost" type="submit">Suspendre</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

