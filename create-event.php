<?php
require_once __DIR__ . '/includes/auth-check.php';
requireRole('organizer');

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide.";
    }

    $title = sanitizeInput($_POST['title'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $date = sanitizeInput($_POST['date'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $template = sanitizeInput($_POST['template'] ?? '');
    $color = sanitizeInput($_POST['color'] ?? '#4a6fa5');

    if ($title === '' || $type === '' || $date === '' || $location === '') {
        $errors[] = "Veuillez renseigner tous les champs obligatoires.";
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

        $stmt = $pdo->prepare("INSERT INTO events (user_id, title, event_type, event_date, location, invitation_design, settings) VALUES (:user_id, :title, :event_type, :event_date, :location, :invitation_design, :settings)");
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'title' => $title,
            'event_type' => $type,
            'event_date' => $date,
            'location' => $location,
            'invitation_design' => $invitationDesign,
            'settings' => $settings,
        ]);
        $success = true;
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Événement</span>
        <h2>Créer un nouvel événement</h2>
    </div>
    <div class="card">
        <?php if (!empty($errors)): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #fca5a5;"><?= implode('<br>', $errors); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #a7f3d0;">Événement enregistré avec succès.</p>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
            <div class="form-group">
                <label for="title">Titre événement</label>
                <input id="title" name="title" type="text" placeholder="Mariage de Clarisse & Jonas" required>
            </div>
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" required>
                    <option value="">Sélectionner</option>
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
                <input id="location" name="location" type="text" placeholder="Salle des fêtes, Pointe-Noire" required>
            </div>
            <div class="form-group">
                <label for="message">Message personnalisé</label>
                <textarea id="message" name="message" rows="4" placeholder="Nous serons ravis de vous accueillir..."></textarea>
            </div>
            <div class="form-group">
                <label for="template">Template</label>
                <select id="template" name="template">
                    <option>Élégant Nuit</option>
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
            <div class="form-group">
                <label for="photos">Photos (max 10)</label>
                <input id="photos" name="photos[]" type="file" multiple>
            </div>
            <button class="button primary" type="submit">Enregistrer l'événement</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
