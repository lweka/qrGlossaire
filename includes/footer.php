    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 Cartelplus Congo. Plateforme d'invitations numériques professionnelles.</p>
        </div>
    </footer>
    <?php
    require_once __DIR__ . '/../app/config/constants.php';
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    $mainJsPath = __DIR__ . '/../assets/js/main.js';
    $mainJsVersion = is_file($mainJsPath) ? (string) filemtime($mainJsPath) : (string) time();
    ?>
    <?php if (isset($pageFooterScripts) && is_string($pageFooterScripts) && $pageFooterScripts !== ''): ?>
        <?= $pageFooterScripts; ?>
    <?php endif; ?>
    <script src="<?= $baseUrl; ?>/assets/js/main.js?v=<?= htmlspecialchars($mainJsVersion, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

