    <footer class="footer">
        <div class="container">
            <p>© 2026 Cartelplus Congo. Plateforme d'invitations numériques professionnelles.</p>
        </div>
    </footer>
    <?php
    require_once __DIR__ . '/../app/config/constants.php';
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    ?>
    <script src="<?= $baseUrl; ?>/assets/js/main.js"></script>
</body>
</html>
