<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('admin');
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Logs système</h2>
    </div>
    <div style="margin: 0 0 18px;">
        <a class="button ghost" href="<?= $baseUrl; ?>/admin/dashboard">Retour au dashboard</a>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Utilisateur</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>07/02/2026</td>
                <td>Validation organisateur</td>
                <td>Claire Nsanga</td>
                <td>192.168.1.120</td>
            </tr>
            <tr>
                <td>06/02/2026</td>
                <td>Suspension compte</td>
                <td>Kevin Loubelo</td>
                <td>192.168.1.54</td>
            </tr>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
