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

$allowedEventTypes = ['wedding', 'birthday', 'corporate', 'other'];
$allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
$allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

$modelDesignDirectory = __DIR__ . '/assets/images/modele_invitations';
$modelDesignWebDirectory = 'assets/images/modele_invitations';
$customDesignDirectory = __DIR__ . '/assets/images/user_designs';
$customDesignWebDirectory = 'assets/images/user_designs';

function eventPostValue(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return trim((string) $value);
}

function listInvitationImageFiles(string $directory, array $allowedExtensions): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $entries = scandir($directory);
    if ($entries === false) {
        return [];
    }

    $files = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $directory . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($fullPath)) {
            continue;
        }

        $extension = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $files[] = $entry;
    }

    natcasesort($files);
    return array_values($files);
}

function isValidHexColor(string $color): bool
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
}

function formatUploadErrorCode(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Fichier trop volumineux.';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload partiel detecte. Reessayez.';
        case UPLOAD_ERR_NO_FILE:
            return 'Aucun fichier recu.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Dossier temporaire absent sur le serveur.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Impossible d ecrire le fichier sur le disque.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload bloque par une extension serveur.';
        default:
            return 'Erreur inconnue lors de l upload.';
    }
}

function makeSafeUploadFilename(int $userId, string $extension): string
{
    return sprintf(
        'event_design_u%d_%s_%s.%s',
        $userId,
        date('YmdHis'),
        bin2hex(random_bytes(5)),
        $extension
    );
}

$modelDesignFiles = listInvitationImageFiles($modelDesignDirectory, $allowedImageExtensions);
$formData = [
    'title' => '',
    'type' => '',
    'date' => '',
    'location' => '',
    'message' => '',
    'template' => 'Elegant Nuit',
    'color' => '#4a6fa5',
    'model_image' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de securite invalide.';
    }

    $creditSummary = getUserCreditSummary($pdo, $userId);
    $creditControlEnabled = !empty($creditSummary['credit_controls_enabled']);
    if ($creditControlEnabled && $creditSummary['event_remaining'] <= 0) {
        $errors[] = 'Vous n avez plus de credit de creation evenement. Demandez une augmentation avant de continuer.';
    }

    $formData['title'] = eventPostValue('title');
    $formData['type'] = eventPostValue('type');
    $formData['date'] = eventPostValue('date');
    $formData['location'] = eventPostValue('location');
    $formData['message'] = eventPostValue('message');
    $formData['template'] = eventPostValue('template', 'Elegant Nuit');
    $formData['color'] = eventPostValue('color', '#4a6fa5');
    $formData['model_image'] = eventPostValue('model_image');

    if ($formData['title'] === '' || $formData['type'] === '' || $formData['date'] === '' || $formData['location'] === '') {
        $errors[] = 'Veuillez renseigner tous les champs obligatoires.';
    }
    if (!in_array($formData['type'], $allowedEventTypes, true)) {
        $errors[] = 'Type d evenement invalide.';
    }
    if (!isValidHexColor($formData['color'])) {
        $formData['color'] = '#4a6fa5';
    }

    if ($formData['model_image'] !== '' && !in_array($formData['model_image'], $modelDesignFiles, true)) {
        $errors[] = 'Modele visuel invalide.';
    }

    $coverImagePath = '';
    $coverImageSource = 'none';
    if ($formData['model_image'] !== '') {
        $coverImagePath = $modelDesignWebDirectory . '/' . $formData['model_image'];
        $coverImageSource = 'model';
    }

    if (isset($_FILES['custom_design_image']) && is_array($_FILES['custom_design_image'])) {
        $upload = $_FILES['custom_design_image'];
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = 'Image personnalisee: ' . formatUploadErrorCode($uploadError);
            } else {
                $fileSize = (int) ($upload['size'] ?? 0);
                if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                    $errors[] = 'Image personnalisee: taille invalide (max 5 MB).';
                }

                $originalName = (string) ($upload['name'] ?? '');
                $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedImageExtensions, true)) {
                    $errors[] = 'Image personnalisee: extension non autorisee.';
                }

                $tmpPath = (string) ($upload['tmp_name'] ?? '');
                $mimeType = '';
                if ($tmpPath !== '' && is_file($tmpPath) && function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo !== false) {
                        $mimeType = (string) finfo_file($finfo, $tmpPath);
                        finfo_close($finfo);
                    }
                }
                if ($mimeType !== '' && !in_array($mimeType, $allowedImageMimeTypes, true)) {
                    $errors[] = 'Image personnalisee: format MIME non autorise.';
                }

                if (empty($errors)) {
                    if (!is_dir($customDesignDirectory) && !mkdir($customDesignDirectory, 0775, true) && !is_dir($customDesignDirectory)) {
                        $errors[] = 'Impossible de preparer le dossier de stockage des visuels.';
                    } else {
                        $safeFileName = makeSafeUploadFilename($userId, $extension);
                        $targetPath = $customDesignDirectory . '/' . $safeFileName;
                        if (!move_uploaded_file($tmpPath, $targetPath)) {
                            $errors[] = 'Echec de sauvegarde du visuel personnalise.';
                        } else {
                            @chmod($targetPath, 0644);
                            $coverImagePath = $customDesignWebDirectory . '/' . $safeFileName;
                            $coverImageSource = 'upload';
                        }
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $invitationDesign = json_encode([
            'template' => $formData['template'],
            'color' => $formData['color'],
            'message' => $formData['message'],
            'cover_image' => $coverImagePath,
            'cover_source' => $coverImageSource,
            'cover_alt' => $formData['title'] !== '' ? ('Invitation - ' . $formData['title']) : 'Visuel invitation',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
            'title' => $formData['title'],
            'event_type' => $formData['type'],
            'event_date' => $formData['date'],
            'location' => $formData['location'],
            'invitation_design' => $invitationDesign,
            'settings' => $settings,
        ]);
        $success = true;
        $creditSummary = getUserCreditSummary($pdo, $userId);
        $creditControlEnabled = !empty($creditSummary['credit_controls_enabled']);
        $canCreateEvent = !$creditControlEnabled || $creditSummary['event_remaining'] > 0;
    }
}

$pageHeadExtra = <<<'HTML'
<style>
    .design-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        margin-top: 8px;
    }
    .design-grid-item {
        border: 1px solid #dfe3ea;
        border-radius: 10px;
        padding: 8px;
        background: #f8fafc;
        font-size: 12px;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
HTML;

$pageFooterScripts = '';
if ($success) {
    $pageHeadExtra .= <<<'HTML'
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
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                        <div class="form-group">
                            <label for="title">Titre evenement</label>
                            <input id="title" name="title" type="text" placeholder="Mariage de Clarisse & Jonas" required value="<?= htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="type">Type</label>
                            <select id="type" name="type" required>
                                <option value="">Selectionner</option>
                                <option value="wedding" <?= $formData['type'] === 'wedding' ? 'selected' : ''; ?>>Mariage</option>
                                <option value="birthday" <?= $formData['type'] === 'birthday' ? 'selected' : ''; ?>>Anniversaire</option>
                                <option value="corporate" <?= $formData['type'] === 'corporate' ? 'selected' : ''; ?>>Corporate</option>
                                <option value="other" <?= $formData['type'] === 'other' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Date et heure</label>
                            <input id="date" name="date" type="datetime-local" required value="<?= htmlspecialchars($formData['date'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="location">Lieu</label>
                            <input id="location" name="location" type="text" placeholder="Salle des fetes, Pointe-Noire" required value="<?= htmlspecialchars($formData['location'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="message">Message personnalise</label>
                            <textarea id="message" name="message" rows="4" placeholder="Nous serons ravis de vous accueillir..."><?= htmlspecialchars($formData['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="template">Template</label>
                            <select id="template" name="template">
                                <?php
                                $templates = ['Elegant Nuit', 'Minimal Chic', 'Golden Glow', 'Corporate Nova', 'Vibrant Party'];
                                foreach ($templates as $templateOption):
                                ?>
                                    <option value="<?= htmlspecialchars($templateOption, ENT_QUOTES, 'UTF-8'); ?>" <?= $formData['template'] === $templateOption ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($templateOption, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="color">Couleur principale</label>
                            <input id="color" name="color" type="color" value="<?= htmlspecialchars($formData['color'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="model_image">Modele visuel (optionnel)</label>
                            <select id="model_image" name="model_image">
                                <option value="">Aucun modele</option>
                                <?php foreach ($modelDesignFiles as $modelFile): ?>
                                    <option value="<?= htmlspecialchars($modelFile, ENT_QUOTES, 'UTF-8'); ?>" <?= $formData['model_image'] === $modelFile ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars((string) pathinfo($modelFile, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="margin-top: 6px; color: var(--text-mid); font-size: 13px;">
                                Ce visuel sera affiche en haut de la page invite (`guest-invitation`).
                            </p>
                            <?php if (!empty($modelDesignFiles)): ?>
                                <div class="design-grid">
                                    <?php foreach ($modelDesignFiles as $modelFile): ?>
                                        <div class="design-grid-item"><?= htmlspecialchars((string) pathinfo($modelFile, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="custom_design_image">Ou uploader un visuel personnalise (optionnel)</label>
                            <input id="custom_design_image" name="custom_design_image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.avif,image/*">
                            <p style="margin-top: 6px; color: var(--text-mid); font-size: 13px;">
                                Taille max: 5 MB. Si vous uploadez une image, elle remplace le modele selectionne.
                            </p>
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
