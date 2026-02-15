<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../app/helpers/credits.php';
requireRole('admin');
ensureCreditSystemSchema($pdo);

$message = null;
$messageType = 'success';
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de securite invalide.';
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
                grantCreditsToUser($pdo, $userId, $invitationCredits, $eventCredits);
                $pdo->commit();
                $message = 'Paiement confirme et credits ajoutes (' . $invitationCredits . ' invitations, ' . $eventCredits . ' evenement(s)).';
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
        u.payment_confirmed,
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
     ORDER BY u.created_at DESC"
)->fetchAll();

$pendingCreditRequests = getPendingCreditRequests($pdo);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Gestion des utilisateurs et credits</h2>
    </div>
    <div style="margin: 0 0 18px;">
        <a class="button ghost" href="<?= $baseUrl; ?>/admin/dashboard">Retour au dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="card" style="margin-bottom: 18px;">
            <?php $messageColor = $messageType === 'error' ? '#dc2626' : '#166534'; ?>
            <p style="color: <?= $messageColor; ?>;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 22px;">
        <h3 style="margin-bottom: 10px;">Demandes d augmentation en attente</h3>
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
                <?php if (empty($pendingCreditRequests)): ?>
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
                                +<?= (int) ($request['requested_event_credits'] ?? 0); ?> credit(s) evenement
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
                    <th>Evenements</th>
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
                        $invitationTotal = (int) ($user['invitation_credit_total'] ?? 0);
                        $invitationUsed = (int) ($user['invitations_used'] ?? 0);
                        $eventTotal = (int) ($user['event_credit_total'] ?? 0);
                        $eventUsed = (int) ($user['events_used'] ?? 0);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($user['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int) ($user['payment_confirmed'] ?? 0) === 1 ? 'Oui' : 'Non'; ?></td>
                            <td><?= max(0, $invitationTotal - $invitationUsed); ?> / <?= $invitationTotal; ?></td>
                            <td><?= max(0, $eventTotal - $eventUsed); ?> / <?= $eventTotal; ?></td>
                            <td>
                                <form method="post" style="display: inline-block; margin: 0 8px 8px 0;">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                    <input type="hidden" name="action" value="confirm-payment">
                                    <input type="hidden" name="id" value="<?= (int) $user['id']; ?>">
                                    <input type="number" name="invitation_credits" min="0" value="<?= DEFAULT_INITIAL_INVITATION_CREDITS; ?>" style="width: 110px;" title="Credits invitations">
                                    <input type="number" name="event_credits" min="0" value="<?= DEFAULT_INITIAL_EVENT_CREDITS; ?>" style="width: 90px;" title="Credits evenement">
                                    <button class="button ghost" type="submit">Paiement + credits</button>
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
