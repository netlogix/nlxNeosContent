<?php

namespace nlxNeosContent\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1738219496AddNeosNodeIdentifierExtension extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1738219496;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
           CREATE TABLE IF NOT EXISTS `nlx_neos_node`
           (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `cms_page_id` BINARY(16) NOT NULL,
                `cms_page_version_id` BINARY(16) NOT NULL,
                `node_identifier` VARCHAR(255) COLLATE utf8mb4_unicode_ci,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
               CONSTRAINT `fk.nlx_neos_node.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
