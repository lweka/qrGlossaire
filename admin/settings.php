<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('admin');
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Configuration système</h2>
    </div>
    <div class="card">
        <div class="form-group">
            <label for="smtp">SMTP</label>
            <input id="smtp" type="text" placeholder="smtp.mail.com">
        </div>
        <div class="form-group">
            <label for="storage">Limite stockage (Go)</label>
            <input id="storage" type="number" placeholder="10">
        </div>
        <button class="button primary" type="button">Enregistrer</button>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
