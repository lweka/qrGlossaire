<?php
require_once __DIR__ . '/includes/auth-check.php';
requireRole('organizer');

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$dashboardSection = 'checkin_scan';
$baseUrlJs = json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$pageHeadExtra = <<<HTML
<style>
    .scan-top-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 18px;
    }

    .scan-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
        gap: 18px;
    }

    .scan-reader-card,
    .scan-help-card {
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        padding: 18px;
    }

    .scan-reader-header {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .scan-reader-header h3 {
        margin: 0;
    }

    .scan-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 12px;
    }

    .scan-controls select {
        min-width: 230px;
    }

    .scan-reader {
        width: 100%;
        min-height: 300px;
        border: 1px dashed rgba(148, 163, 184, 0.6);
        border-radius: 16px;
        background: rgba(248, 250, 252, 0.9);
        overflow: hidden;
    }

    .scan-reader #qr-reader__dashboard_section {
        padding: 12px !important;
    }

    .scan-reader video {
        border-radius: 14px;
    }

    .scan-status {
        margin-top: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        font-weight: 600;
        color: #0f172a;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.35);
    }

    .scan-status.success {
        color: #166534;
        background: #ecfdf5;
        border-color: #86efac;
    }

    .scan-status.warning {
        color: #92400e;
        background: #fffbeb;
        border-color: #fcd34d;
    }

    .scan-status.error {
        color: #b91c1c;
        background: #fef2f2;
        border-color: #fca5a5;
    }

    .scan-help-card h3 {
        margin-bottom: 10px;
    }

    .scan-help-card p {
        margin-bottom: 12px;
        color: var(--text-mid);
    }

    .scan-help-card ol {
        margin: 0 0 14px 18px;
        color: var(--text-mid);
    }

    .scan-help-card li {
        margin-bottom: 8px;
        line-height: 1.5;
    }

    .scan-manual-form {
        margin-top: 12px;
        display: grid;
        gap: 10px;
    }

    .scan-tip {
        margin-top: 12px;
        font-size: 0.9rem;
        color: var(--text-light);
    }

    @media (max-width: 1024px) {
        .scan-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .scan-controls {
            display: grid;
            grid-template-columns: 1fr;
        }

        .scan-controls select,
        .scan-controls .button {
            width: 100%;
        }

        .scan-reader {
            min-height: 280px;
        }
    }
</style>
HTML;

$pageFooterScripts = <<<HTML
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(() => {
    const baseUrl = {$baseUrlJs};
    const readerId = 'qr-reader';
    const statusEl = document.querySelector('[data-scan-status]');
    const cameraSelect = document.getElementById('camera_id');
    const startButton = document.getElementById('start_scanner_btn');
    const stopButton = document.getElementById('stop_scanner_btn');

    let qrReader = null;
    let selectedCameraId = '';
    let handlingScan = false;
    let lastCode = '';
    let lastScanAt = 0;

    function setStatus(message, level = 'info') {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message;
        statusEl.className = 'scan-status';
        if (level === 'success' || level === 'warning' || level === 'error') {
            statusEl.classList.add(level);
        }
    }

    function normalizeCode(rawValue) {
        const value = String(rawValue || '').trim();
        if (value === '') {
            return '';
        }

        const directMatch = value.match(/INV-[A-Z0-9]{6,20}/i);
        if (directMatch) {
            return directMatch[0].toUpperCase();
        }

        try {
            const parsedUrl = new URL(value, window.location.origin);
            const codeFromUrl = parsedUrl.searchParams.get('code');
            if (codeFromUrl) {
                return String(codeFromUrl).toUpperCase().replace(/[^A-Z0-9-]/g, '');
            }
        } catch (error) {
        }

        const cleaned = value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
        if (/^INV-[A-Z0-9]{6,20}$/.test(cleaned)) {
            return cleaned;
        }

        return '';
    }

    function buildCheckinUrl(code) {
        return baseUrl + '/guest-checkin?code=' + encodeURIComponent(code) + '&scan=1';
    }

    async function stopScanner() {
        if (!qrReader) {
            stopButton.disabled = true;
            return;
        }

        try {
            await qrReader.stop();
        } catch (error) {
        }
        stopButton.disabled = true;
    }

    async function onCodeDetected(decodedText) {
        const code = normalizeCode(decodedText);
        if (code === '') {
            const now = Date.now();
            if (now - lastScanAt > 1200) {
                setStatus('QR detecte mais code invitation invalide.', 'warning');
                lastScanAt = now;
            }
            return;
        }

        const now = Date.now();
        if (handlingScan) {
            return;
        }
        if (code === lastCode && (now - lastScanAt) < 1800) {
            return;
        }

        handlingScan = true;
        lastCode = code;
        lastScanAt = now;
        setStatus('Code ' + code + ' detecte. Validation en cours...', 'success');

        await stopScanner();
        window.location.href = buildCheckinUrl(code);
    }

    async function startScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            setStatus('Scanner indisponible. Verifiez la connexion internet.', 'error');
            return;
        }

        const cameraId = String(cameraSelect.value || selectedCameraId || '');
        if (cameraId === '') {
            setStatus('Aucune camera selectionnee.', 'error');
            return;
        }

        if (!qrReader) {
            qrReader = new Html5Qrcode(readerId);
        } else {
            await stopScanner();
        }

        handlingScan = false;

        try {
            await qrReader.start(
                { deviceId: { exact: cameraId } },
                {
                    fps: 10,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        const size = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.72);
                        return { width: size, height: size };
                    },
                },
                onCodeDetected
            );
            stopButton.disabled = false;
            setStatus('Scanner actif. Presentez le QR code invite devant la camera.', 'success');
        } catch (error) {
            setStatus('Impossible de demarrer la camera. Autorisez la camera puis reessayez.', 'error');
        }
    }

    async function loadCameras() {
        if (typeof Html5Qrcode === 'undefined') {
            setStatus('Scanner indisponible. Verifiez la connexion internet.', 'error');
            startButton.disabled = true;
            stopButton.disabled = true;
            return;
        }

        try {
            const devices = await Html5Qrcode.getCameras();
            cameraSelect.innerHTML = '';
            if (!Array.isArray(devices) || devices.length === 0) {
                setStatus('Aucune camera detectee sur cet appareil.', 'error');
                startButton.disabled = true;
                stopButton.disabled = true;
                return;
            }

            devices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.id;
                option.textContent = device.label || ('Camera ' + (index + 1));
                cameraSelect.appendChild(option);
            });

            const backCamera = devices.find((device) => {
                const label = String(device.label || '').toLowerCase();
                return label.includes('back') || label.includes('rear') || label.includes('environment') || label.includes('arriere');
            });
            selectedCameraId = backCamera ? backCamera.id : String(devices[0].id || '');
            cameraSelect.value = selectedCameraId;
            startButton.disabled = false;
            setStatus('Camera detectee. Cliquez sur "Demarrer le scanner".');
        } catch (error) {
            setStatus('Acces camera refuse ou indisponible.', 'error');
            startButton.disabled = true;
            stopButton.disabled = true;
        }
    }

    cameraSelect.addEventListener('change', () => {
        selectedCameraId = String(cameraSelect.value || '');
    });

    startButton.addEventListener('click', () => {
        startScanner();
    });

    stopButton.addEventListener('click', async () => {
        await stopScanner();
        setStatus('Scanner arrete.', 'warning');
    });

    window.addEventListener('beforeunload', () => {
        stopScanner();
    });

    loadCameras();
})();
</script>
HTML;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Check-in</span>
            <h2>Scanner QR a l entree</h2>
        </div>

        <div class="scan-top-actions">
            <a class="button ghost" href="<?= $baseUrl; ?>/guests">Retour aux invites</a>
            <a class="button ghost" href="<?= $baseUrl; ?>/dashboard">Retour au dashboard</a>
        </div>

        <div class="scan-layout">
            <section class="scan-reader-card">
                <div class="scan-reader-header">
                    <h3>Scan camera</h3>
                    <span class="badge">Validation immediate</span>
                </div>
                <div class="scan-controls">
                    <select id="camera_id" aria-label="Camera"></select>
                    <button id="start_scanner_btn" class="button primary" type="button" disabled>Demarrer le scanner</button>
                    <button id="stop_scanner_btn" class="button ghost" type="button" disabled>Arreter</button>
                </div>
                <div id="qr-reader" class="scan-reader"></div>
                <p class="scan-status" data-scan-status>Preparation du scanner...</p>
            </section>

            <aside class="scan-help-card">
                <h3>Comment scanner a l entree</h3>
                <p>L invite presente son QR code (sur telephone ou papier), puis l agent d accueil scanne ici.</p>
                <ol>
                    <li>Ouvrir cette page sur le telephone de l equipe d accueil.</li>
                    <li>Autoriser la camera puis demarrer le scanner.</li>
                    <li>Pointer la camera vers le QR de l invite.</li>
                    <li>Le systeme valide l entree automatiquement.</li>
                </ol>

                <h3>Saisie manuelle (secours)</h3>
                <form method="get" action="<?= $baseUrl; ?>/guest-checkin" class="scan-manual-form">
                    <label for="manual_code">Code invite (ex: INV-923B755267)</label>
                    <input id="manual_code" name="code" type="text" placeholder="INV-XXXXXXXXXX" required>
                    <input type="hidden" name="scan" value="1">
                    <button class="button ghost" type="submit">Valider avec le code</button>
                </form>

                <p class="scan-tip">
                    Le QR embarque deja le lien de check-in. Une application scanner externe peut aussi ouvrir le lien.
                </p>
            </aside>
        </div>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
