<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/credits.php';

try {
    ensureCreditSystemSchema($pdo);
    echo "Migration credits: OK\n";
    echo "- users.invitation_credit_total\n";
    echo "- users.event_credit_total\n";
    echo "- table credit_requests\n";
    echo "- table communication_logs\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration credits: ERROR\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
