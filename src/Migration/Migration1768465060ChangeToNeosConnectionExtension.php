<?php

namespace nlxNeosContent\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1768465060ChangeToNeosConnectionExtension extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768465060;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            ALTER TABLE `nlx_neos_node`
            DROP COLUMN `node_identifier`,
            ADD COLUMN `neos_connection` TINYINT(1) NOT NULL DEFAULT 0
        SQL;

        $connection->executeStatement(
            $sql
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // Destructive changes (if needed)
    }
}
