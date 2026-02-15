<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/credits.php';

try {
    ensureCreditSystemSchema($pdo);

    $quotaOk = isCreditQuotaEnabled($pdo, true);
    $requestsOk = isCreditRequestModuleEnabled($pdo, true);
    $communicationsOk = isCommunicationLogModuleEnabled($pdo, true);

    echo "Migration credits: " . (($quotaOk && $requestsOk && $communicationsOk) ? "OK" : "PARTIAL") . "\n";
    echo "- users.invitation_credit_total: " . ($quotaOk ? "OK" : "MISSING") . "\n";
    echo "- users.event_credit_total: " . ($quotaOk ? "OK" : "MISSING") . "\n";
    echo "- table credit_requests: " . ($requestsOk ? "OK" : "MISSING") . "\n";
    echo "- table communication_logs: " . ($communicationsOk ? "OK" : "MISSING") . "\n";

    if (!($quotaOk && $requestsOk && $communicationsOk)) {
        exit(1);
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration credits: ERROR\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
