<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class NeosCmsPageLifecycleService
{
    private const DEACTIVATED_VERSION_ID = 'deaddeaddeaddeaddeaddeaddeaddead';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function deactivateNeosPages(): void
    {
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $deactivatedVersionId = Uuid::fromHexToBytes(self::DEACTIVATED_VERSION_ID);

        $neosPageCount = (int) $this->connection->executeQuery('SELECT COUNT(*) FROM nlx_neos_node')->fetchOne();
        if ($neosPageCount === 0) {
            return;
        }

        $this->assertNoNeosPagesAreDefault();

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page
                    SET version_id = :deactivated
                    WHERE version_id = :live
                      AND id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page_translation
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE category
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE product
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE landing_page
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE nlx_neos_node
                    SET version_id = :deactivated
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['deactivated' => $deactivatedVersionId]
            );

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw new RuntimeException(
                'Failed to deactivate Neos CMS pages: ' . $e->getMessage(),
                1774254879,
                $e
            );
        }
    }

    public function restoreNeosPages(): void
    {
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $deactivatedVersionId = Uuid::fromHexToBytes(self::DEACTIVATED_VERSION_ID);

        $deactivatedCount = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM cms_page WHERE version_id = :deactivated',
            ['deactivated' => $deactivatedVersionId]
        )->fetchOne();

        if ($deactivatedCount === 0) {
            return;
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page
                    SET version_id = :live
                    WHERE version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page_translation
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE category
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE product
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE landing_page
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE nlx_neos_node
                    SET version_id = :live
                    WHERE cms_page_version_id = :live
                      AND version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw new RuntimeException(
                'Failed to restore Neos CMS pages: ' . $e->getMessage(),
                1774254901,
                $e
            );
        }
    }

    public function removeCmsPages(): void
    {
        $cmsPageIds = $this->connection->executeQuery(
            'SELECT cms_page_id FROM nlx_neos_node'
        )->fetchAllAssociative();

        $defaultIdsQueryResult = $this->connection->executeQuery(
            'SELECT configuration_value FROM system_config WHERE configuration_key LIKE "%cms.default_%"'
        )->fetchAllAssociative();

        $defaultCmsPageIds = $this->extractDefaultCmsPageIds($defaultIdsQueryResult);

        foreach ($cmsPageIds as $queryRow) {
            $cmsPageId = Uuid::fromBytesToHex($queryRow['cms_page_id']);
            if (in_array($cmsPageId, $defaultCmsPageIds)) {
                throw new RuntimeException(
                    'Cannot remove Neos CMS page with ID ' . $cmsPageId . ' because it is set as default CMS page. Please change the default CMS page before deactivating.',
                    1774254918
                );
            }
        }

        $this->connection->executeStatement(
            <<<SQL
                    UPDATE category
                    SET cms_page_id = NULL
                    WHERE cms_page_id IN (
                        SELECT cms_page_id FROM nlx_neos_node
                    );
                SQL
        );

        $this->connection->executeStatement(
            <<<SQL
                    UPDATE product
                    SET cms_page_id = NULL
                    WHERE cms_page_id IN (
                        SELECT cms_page_id FROM nlx_neos_node
                    );
                SQL
        );

        $this->connection->executeStatement(
            <<<SQL
                    DELETE FROM cms_page
                    WHERE id IN (
                        SELECT cms_page_id FROM nlx_neos_node
                    );
                SQL
        );

        //FIXME Drop nlx_neos_node table, currently not possible due to creation with migrations
        $this->connection->executeStatement(
            <<<SQL
                    DELETE FROM nlx_neos_node;
                SQL
        );
    }

    private function assertNoNeosPagesAreDefault(): void
    {
        $cmsPageIds = $this->connection->executeQuery(
            'SELECT cms_page_id FROM nlx_neos_node'
        )->fetchAllAssociative();

        $defaultIdsQueryResult = $this->connection->executeQuery(
            'SELECT configuration_value FROM system_config WHERE configuration_key LIKE "%cms.default_%"'
        )->fetchAllAssociative();

        $defaultCmsPageIds = $this->extractDefaultCmsPageIds($defaultIdsQueryResult);

        foreach ($cmsPageIds as $queryRow) {
            $cmsPageId = Uuid::fromBytesToHex($queryRow['cms_page_id']);
            if (in_array($cmsPageId, $defaultCmsPageIds, true)) {
                throw new RuntimeException(
                    'Cannot deactivate Neos CMS pages: Page with ID ' . $cmsPageId .
                    ' is set as default CMS page. Please change the default CMS page before deactivating.',
                    1774254947
                );
            }
        }
    }

    /**
     * @param array<int, array{configuration_value: string}> $defaultCmsPageIds
     * @return string[]
     */
    private function extractDefaultCmsPageIds(array $defaultCmsPageIds): array
    {
        $ids = [];
        foreach ($defaultCmsPageIds as $defaultCmsPageId) {
            if (isset($defaultCmsPageId['configuration_value'])) {
                $jsonDecoded = json_decode($defaultCmsPageId['configuration_value'], true);
                if ($jsonDecoded['_value'] === null) {
                    continue;
                }
                $ids[] = $jsonDecoded['_value'];
            }
        }

        return $ids;
    }
}

