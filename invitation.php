<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container invitation-hero">
    <div class="badge">Invitation personnalisée</div>
    <h1>Mariage de Clarisse & Jonas</h1>
    <p>Samedi 12 juillet 2026 · 16h00 · Pointe-Noire</p>
</section>

<section class="container">
    <div class="invitation-section">
        <h3>Détails de l'événement</h3>
        <p>Date : 12 juillet 2026</p>
        <p>Lieu : Salle des fêtes Marina, Pointe-Noire</p>
        <p>Dress code : Élégance noire</p>
    </div>

    <div class="invitation-section">
        <h3>Confirmer ma présence</h3>
        <button class="button primary" type="button">Je confirme ma présence</button>
        <p style="margin-top: 12px; color: var(--muted);">Réponse attendue avant le 30 juin 2026.</p>
    </div>

    <div class="invitation-section">
        <h3>Votre QR Code personnel</h3>
        <div class="qr-box">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=INVITATION-DEMO" alt="QR Code invité">
            <button class="button ghost" type="button">Télécharger le QR code</button>
        </div>
    </div>

    <div class="invitation-section">
        <h3>Carte interactive</h3>
        <iframe title="Carte" width="100%" height="260" style="border:0;border-radius:16px;" loading="lazy" allowfullscreen
            src="https://www.google.com/maps/embed/v1/place?key=YOUR_API_KEY&q=Pointe-Noire">
        </iframe>
    </div>

    <div class="invitation-section">
        <h3>Messages aux hôtes</h3>
        <textarea rows="3" placeholder="Laissez un message..."></textarea>
        <button class="button primary" type="button" style="margin-top: 12px;">Envoyer</button>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
