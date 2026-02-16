<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/mailer.php';

$options = getopt('', ['to:', 'name::']);
$to = isset($options['to']) ? trim((string) $options['to']) : '';
$name = isset($options['name']) ? trim((string) $options['name']) : 'Test User';

if ($to === '') {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/test_mailer.php --to=\"destinataire@email.com\" [--name=\"Nom\"]\n");
    exit(1);
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Adresse email invalide: {$to}\n");
    exit(1);
}

$subject = 'Test SMTP InviteQR';
$htmlBody = '<p>Test SMTP InviteQR: ce message confirme que la configuration SMTP est operationnelle.</p>';
$plainBody = "Test SMTP InviteQR: ce message confirme que la configuration SMTP est operationnelle.";

$errorDetail = null;
$ok = sendTransactionalEmail($to, $name, $subject, $htmlBody, $plainBody, $errorDetail);

if ($ok) {
    fwrite(STDOUT, "Email envoye avec succes vers {$to}\n");
    fwrite(STDOUT, "Consultez aussi le log: storage/logs/mailer.log\n");
    exit(0);
}

fwrite(STDERR, "Echec envoi SMTP.\n");
if ($errorDetail) {
    fwrite(STDERR, "Detail: {$errorDetail}\n");
}
fwrite(STDERR, "Consultez le log: storage/logs/mailer.log\n");
exit(2);
