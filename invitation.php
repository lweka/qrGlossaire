<?php include __DIR__ . '/includes/header.php'; ?>
<?php
$modelsDirectory = __DIR__ . '/assets/images/modele_invitations';
$modelsWebPath = 'assets/images/modele_invitations';
$allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

$modelProfiles = [
    'conference' => [
        'type_label' => 'Conference',
        'headline' => 'Invitation Conference',
        'meta' => 'Mercredi 04 novembre 2026 - 09h30',
        'date' => '04 novembre 2026',
        'location' => 'Lieu a confirmer',
        'dress_code' => 'Business formel',
        'rsvp_deadline' => '25 octobre 2026',
    ],
    'mariage' => [
        'type_label' => 'Mariage',
        'headline' => 'Invitation Mariage',
        'meta' => 'Samedi 12 juillet 2026 - 16h00',
        'date' => '12 juillet 2026',
        'location' => 'Lieu a confirmer',
        'dress_code' => 'Elegance noire',
        'rsvp_deadline' => '30 juin 2026',
    ],
    'anniversaire' => [
        'type_label' => 'Anniversaire',
        'headline' => 'Invitation Anniversaire',
        'meta' => 'Vendredi 08 mai 2026 - 19h00',
        'date' => '08 mai 2026',
        'location' => 'Lieu a confirmer',
        'dress_code' => 'Smart casual',
        'rsvp_deadline' => '30 avril 2026',
    ],
    'corporate' => [
        'type_label' => 'Corporate',
        'headline' => 'Invitation Corporate',
        'meta' => 'Jeudi 22 septembre 2026 - 10h00',
        'date' => '22 septembre 2026',
        'location' => 'Lieu a confirmer',
        'dress_code' => 'Tenue professionnelle',
        'rsvp_deadline' => '10 septembre 2026',
    ],
    'default' => [
        'type_label' => 'Evenement',
        'headline' => 'Invitation Personnalisee',
        'meta' => 'Configurez votre modele et partagez-le en QR Code',
        'date' => 'A definir',
        'location' => 'A definir',
        'dress_code' => 'A definir',
        'rsvp_deadline' => 'A definir',
    ],
];

$profileKeywords = [
    'conference' => ['confe', 'conference', 'conf', 'seminaire', 'seminar', 'colloque'],
    'mariage' => ['mariage', 'wedding', 'marry'],
    'anniversaire' => ['anniv', 'anniversaire', 'birthday', 'bday'],
    'corporate' => ['corporate', 'entreprise', 'business', 'corp'],
];

$detectProfileKey = static function (string $fileName) use ($profileKeywords): string {
    $normalized = strtolower((string) pathinfo($fileName, PATHINFO_FILENAME));
    foreach ($profileKeywords as $profileKey => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                return $profileKey;
            }
        }
    }
    return 'default';
};

$invitationModels = [];
if (is_dir($modelsDirectory)) {
    $entries = scandir($modelsDirectory);
    if ($entries !== false) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $modelsDirectory . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($fullPath)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedImageExtensions, true)) {
                continue;
            }

            $baseName = strtolower((string) pathinfo($entry, PATHINFO_FILENAME));
            if ($baseName === 'logo12' || strpos($baseName, 'logo') === 0) {
                continue;
            }

            $profileKey = $detectProfileKey($entry);
            $profile = $modelProfiles[$profileKey] ?? $modelProfiles['default'];
            $modelName = ucwords(str_replace(['-', '_'], ' ', (string) pathinfo($entry, PATHINFO_FILENAME)));

            $invitationModels[] = [
                'file' => $entry,
                'type_label' => (string) $profile['type_label'],
                'headline' => (string) $profile['headline'],
                'meta' => (string) $profile['meta'],
                'date' => (string) $profile['date'],
                'location' => (string) $profile['location'],
                'dress_code' => (string) $profile['dress_code'],
                'rsvp_deadline' => (string) $profile['rsvp_deadline'],
                'alt' => 'Modele ' . strtolower((string) $profile['type_label']) . ' - ' . $modelName,
            ];
        }
    }
}

usort(
    $invitationModels,
    static function (array $a, array $b): int {
        return strnatcasecmp($a['file'], $b['file']);
    }
);

$activeModel = $invitationModels[0] ?? $modelProfiles['default'];
?>
<section class="container invitation-hero">
    <div class="badge">Modeles d invitations</div>
    <h1><?= htmlspecialchars((string) ($activeModel['headline'] ?? $modelProfiles['default']['headline']), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?= htmlspecialchars((string) ($activeModel['meta'] ?? $modelProfiles['default']['meta']), ENT_QUOTES, 'UTF-8'); ?></p>
</section>

<section class="container invitation-showcase" data-invitation-showcase>
    <div class="invitation-slider-wrap">
        <div class="invitation-model-slider" data-slider>
            <?php if (empty($invitationModels)): ?>
                <article class="slide active">
                    <div class="invitation-empty-state">
                        Ajoutez vos visuels dans assets/images/modele_invitations.
                        <br>
                        Exemple de noms: model_invite_confe.jpg, model_invite_mariage.jpg
                    </div>
                </article>
            <?php else: ?>
                <?php foreach ($invitationModels as $index => $model): ?>
                    <article
                        class="slide<?= $index === 0 ? ' active' : ''; ?>"
                        data-invitation-headline="<?= htmlspecialchars($model['headline'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-invitation-meta="<?= htmlspecialchars($model['meta'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-invitation-date="<?= htmlspecialchars($model['date'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-invitation-location="<?= htmlspecialchars($model['location'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-invitation-dress-code="<?= htmlspecialchars($model['dress_code'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-invitation-rsvp-deadline="<?= htmlspecialchars($model['rsvp_deadline'], ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <img src="<?= $baseUrl; ?>/<?= $modelsWebPath; ?>/<?= rawurlencode($model['file']); ?>" alt="<?= htmlspecialchars($model['alt'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="invitation-slide-overlay">
                            <span class="invitation-slide-chip"><?= htmlspecialchars($model['type_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <h3><?= htmlspecialchars($model['headline'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?= htmlspecialchars($model['meta'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="hero-slider-dots" data-dots></div>
        </div>
    </div>

    <div class="invitation-section invitation-dynamic-details">
        <h3 data-invitation-current-headline><?= htmlspecialchars((string) ($activeModel['headline'] ?? $modelProfiles['default']['headline']), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p class="invitation-meta-line" data-invitation-current-meta><?= htmlspecialchars((string) ($activeModel['meta'] ?? $modelProfiles['default']['meta']), ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="invitation-detail-list">
            <p><strong>Date :</strong> <span data-invitation-current-date><?= htmlspecialchars((string) ($activeModel['date'] ?? $modelProfiles['default']['date']), ENT_QUOTES, 'UTF-8'); ?></span></p>
            <p><strong>Lieu :</strong> <span data-invitation-current-location><?= htmlspecialchars((string) ($activeModel['location'] ?? $modelProfiles['default']['location']), ENT_QUOTES, 'UTF-8'); ?></span></p>
            <p><strong>Dress code :</strong> <span data-invitation-current-dress-code><?= htmlspecialchars((string) ($activeModel['dress_code'] ?? $modelProfiles['default']['dress_code']), ENT_QUOTES, 'UTF-8'); ?></span></p>
        </div>
    </div>

    <div class="invitation-section">
        <h3>Confirmer ma presence</h3>
        <button class="button primary" type="button" data-rsvp-button>Je confirme ma presence</button>
        <p class="invitation-rsvp-status" data-rsvp-status>Cliquez sur le bouton pour simuler la confirmation de presence.</p>
        <p style="margin-top: 12px; color: var(--muted);">Reponse attendue avant <span data-invitation-current-rsvp-deadline><?= htmlspecialchars((string) ($activeModel['rsvp_deadline'] ?? $modelProfiles['default']['rsvp_deadline']), ENT_QUOTES, 'UTF-8'); ?></span>.</p>
    </div>

    <div class="invitation-section">
        <h3>Votre QR Code personnel</h3>
        <div class="qr-box">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=INVITATION-DEMO" alt="QR code invite">
            <button class="button ghost" type="button">Telecharger le QR code</button>
        </div>
    </div>

    <div class="invitation-section">
        <h3>Carte interactive</h3>
        <iframe title="Carte" width="100%" height="260" style="border:0;border-radius:16px;" loading="lazy" allowfullscreen src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d418.1905466862109!2d15.27022589766349!3d-4.312646422274183!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1a6a33cd8a49cd59%3A0x44c649ce70ff5c4a!2sFleuve%20Congo%20Hotel%20by%20Blazon%20Hotels!5e0!3m2!1sfr!2scd!4v1771195221499!5m2!1sfr!2scd"></iframe>
    </div>

    <div class="invitation-section">
        <h3>Messages aux hotes</h3>
        <textarea rows="3" placeholder="Laissez un message..."></textarea>
        <button class="button primary" type="button" style="margin-top: 12px;">Envoyer</button>
    </div>
</section>

<section class="container" style="padding: 0 0 40px; text-align: center;">
    <a class="button ghost" href="<?= $baseUrl; ?>/">Retourner a l'accueil</a>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

