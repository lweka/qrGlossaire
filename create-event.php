<?php
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/app/helpers/credits.php';
requireRole('organizer');
$creditSchemaReady = ensureCreditSystemSchema($pdo);

$dashboardSection = 'create_event';
$success = false;
$errors = [];
$userId = (int) ($_SESSION['user_id'] ?? 0);
$creditSummary = getUserCreditSummary($pdo, $userId);
$creditControlEnabled = !empty($creditSummary['credit_controls_enabled']);
$canCreateEvent = !$creditControlEnabled || $creditSummary['event_remaining'] > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de securite invalide.';
    }

    $creditSummary = getUserCreditSummary($pdo, $userId);
    $creditControlEnabled = !empty($creditSummary['credit_controls_enabled']);
    if ($creditControlEnabled && $creditSummary['event_remaining'] <= 0) {
        $errors[] = 'Vous n avez plus de credit de creation evenement. Demandez une augmentation avant de continuer.';
    }

    $title = sanitizeInput($_POST['title'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $date = sanitizeInput($_POST['date'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $template = sanitizeInput($_POST['template'] ?? '');
    $color = sanitizeInput($_POST['color'] ?? '#4a6fa5');

    if ($title === '' || $type === '' || $date === '' || $location === '') {
        $errors[] = 'Veuillez renseigner tous les champs obligatoires.';
    }

    if (empty($errors)) {
        $invitationDesign = json_encode([
            'template' => $template,
            'color' => $color,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        $settings = json_encode([
            'dress_code' => null,
            'special_instructions' => null,
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare(
            'INSERT INTO events (user_id, title, event_type, event_date, location, invitation_design, settings)
             VALUES (:user_id, :title, :event_type, :event_date, :location, :invitation_design, :settings)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'event_type' => $type,
            'event_date' => $date,
            'location' => $location,
            'invitation_design' => $invitationDesign,
            'settings' => $settings,
        ]);
        $success = true;
        $creditSummary = getUserCreditSummary($pdo, $userId);
        $creditControlEnabled = !empty($creditSummary['credit_controls_enabled']);
        $canCreateEvent = !$creditControlEnabled || $creditSummary['event_remaining'] > 0;
    }
}

$pageHeadExtra = '';
$pageFooterScripts = '';
if ($success) {
    $pageHeadExtra = <<<'HTML'
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
    .event-success-icon {
        width: 100px;
        height: 100px;
        margin: 0 auto 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        font-weight: 700;
        color: #0f5132;
        background: linear-gradient(145deg, #d1fae5 0%, #86efac 100%);
        box-shadow: 0 16px 40px rgba(15, 81, 50, 0.25);
        animation: pulseValidation 1.6s ease-out infinite;
    }

    @keyframes pulseValidation {
        0% { transform: scale(0.94); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); }
        70% { transform: scale(1); box-shadow: 0 0 0 20px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.94); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
</style>
HTML;

    $pageFooterScripts = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const modalElement = document.getElementById("eventCreatedModal");
    if (!modalElement || typeof bootstrap === "undefined") {
        window.location.href = "dashboard";
        return;
    }

    const successModal = new bootstrap.Modal(modalElement, {
        backdrop: "static",
        keyboard: false
    });
    modalElement.addEventListener("hidden.bs.modal", function () {
        window.location.href = "dashboard";
    });
    successModal.show();
});
</script>
HTML;
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Evenement</span>
            <h2>Creer un nouvel evenement</h2>
        </div>
        <div style="margin: 0 0 18px;">
            <a class="button ghost" href="<?= $baseUrl; ?>/dashboard">Retour au dashboard</a>
        </div>

        <div class="card" style="margin-bottom: 18px;">
            <?php if ($creditControlEnabled): ?>
                <p><strong>Credits evenement restants:</strong> <?= $creditSummary['event_remaining']; ?> / <?= $creditSummary['event_total']; ?></p>
                <p style="margin-top: 6px; color: var(--text-mid);">Chaque creation d evenement consomme 1 credit.</p>
            <?php else: ?>
                <p><strong>Credits evenement:</strong> mode libre temporaire (module credits non initialise).</p>
            <?php endif; ?>
            <?php if (!$creditSchemaReady): ?>
                <p style="margin-top: 6px; color: #92400e;">Le module credits est en initialisation sur ce serveur.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #dc2626;"><?= implode('<br>', array_map(static fn ($err) => htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8'), $errors)); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <?php if (!$canCreateEvent): ?>
                <div class="card">
                    <p style="color: #92400e; margin-bottom: 10px;">
                        Aucun credit evenement disponible. Vous devez demander une augmentation de credits avant de creer un nouvel evenement.
                    </p>
                    <a class="button primary" href="<?= $baseUrl; ?>/dashboard">Demander une augmentation</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                        <div class="form-group">
                            <label for="title">Titre evenement</label>
                            <input id="title" name="title" type="text" placeholder="Mariage de Clarisse & Jonas" required>
                        </div>
                        <div class="form-group">
                            <label for="type">Type</label>
                            <select id="type" name="type" required>
                                <option value="">Selectionner</option>
                                <option value="wedding">Mariage</option>
                                <option value="birthday">Anniversaire</option>
                                <option value="corporate">Corporate</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Date et heure</label>
                            <input id="date" name="date" type="datetime-local" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Lieu</label>
                            <input id="location" name="location" type="text" placeholder="Salle des fetes, Pointe-Noire" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Message personnalise</label>
                            <textarea id="message" name="message" rows="4" placeholder="Nous serons ravis de vous accueillir..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="template">Template</label>
                            <select id="template" name="template">
                                <option>Elegant Nuit</option>
                                <option>Minimal Chic</option>
                                <option>Golden Glow</option>
                                <option>Corporate Nova</option>
                                <option>Vibrant Party</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="color">Couleur principale</label>
                            <input id="color" name="color" type="color" value="#4a6fa5">
                        </div>
                        <button class="button primary" type="submit">Enregistrer l evenement</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php if ($success): ?>
<div class="modal fade" id="eventCreatedModal" tabindex="-1" aria-labelledby="eventCreatedTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-4 p-md-5 text-center">
                <div class="event-success-icon">&#10003;</div>
                <h4 id="eventCreatedTitle" class="mb-3">Evenement valide</h4>
                <p class="text-secondary mb-2">Votre evenement a ete cree avec succes.</p>
                <p class="text-secondary mb-4">Vous allez etre redirige vers le dashboard pour continuer la gestion des invites.</p>
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-outline-secondary px-4" type="button" data-bs-dismiss="modal">Fermer</button>
                    <a class="btn btn-success px-4" href="<?= $baseUrl; ?>/dashboard">Retour dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
