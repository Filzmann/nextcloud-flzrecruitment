<?php

declare(strict_types=1);

$migration = (string)file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000017Date202608250001.php');
foreach (['recr_admin_access', 'target_uid', 'granted_by', 'starts_at', 'ends_at', 'revoked_at', 'revoked_by', 'DATETIME_IMMUTABLE'] as $contract) {
    if (!str_contains($migration, $contract)) throw new RuntimeException("Adminfreigabe-Migrationsvertrag fehlt: {$contract}");
}
if (!str_contains($migration, "if (!\$schema->hasTable('recr_admin_access'))")) throw new RuntimeException('Adminfreigabe-Migration ist nicht wiederholbar abgesichert.');
echo "AdminAccessMigrationContractTest: OK\n";

