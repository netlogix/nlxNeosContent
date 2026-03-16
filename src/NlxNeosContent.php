<?php

declare(strict_types=1);

namespace nlxNeosContent;

use Doctrine\DBAL\Connection;
use nlxNeosContent\Core\Notification\NotificationService;
use nlxNeosContent\Core\Notification\NotificationService66;
use nlxNeosContent\Core\Notification\NotificationServiceInterface;
use nlxNeosContent\Service\NeosAuthorizationRoleService;
use RuntimeException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;


class NlxNeosContent extends Plugin implements CompilerPassInterface
{
    private const DEACTIVATED_VERSION_ID = 'deaddeaddeaddeaddeaddeaddeaddead';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $neosAuthorizationRoleService = $this->getNeosAuthorizationRoleService();
        $neosAuthorizationRoleService->createNeosViewerRole();
        $neosAuthorizationRoleService->createNeosEditorRole();
    }

    function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass($this);
    }

    public function process(ContainerBuilder $container): void
    {
        $container->setDefinition(
            NotificationServiceInterface::class,
            (new Definition(Feature::isActive('v6.7.0.0') ? NotificationService::class : NotificationService66::class))
                ->setAutoconfigured(true)
                ->setAutowired(true)
        );
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        $this->restoreNeosPages();
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);

        $this->deactivateNeosPages();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        //Remove acl roles
        $neosAuthorizationRoleService = $this->getNeosAuthorizationRoleService();
        $neosAuthorizationRoleService->removeNeosViewerRole();
        $neosAuthorizationRoleService->removeNeosEditorRole();

        //Remove cms pages
        $this->removeCmsPages();
    }

    private function getNeosAuthorizationRoleService(): NeosAuthorizationRoleService
    {
        $container = $this->container;
        if (!$container instanceof ContainerInterface) {
            throw new RuntimeException('Container is not an instance of ContainerInterface');
        }

        if (!$container->has('acl_role.repository')) {
            throw new RuntimeException('Service acl_role.repository not found');
        }
        if (!$container->has('user.repository')) {
            throw new RuntimeException('Service user.repository not found');
        }

        return new NeosAuthorizationRoleService(
            $container->get('acl_role.repository'),
            $container->get('acl_user_role.repository')
        );
    }

    private function deactivateNeosPages(): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $deactivatedVersionId = Uuid::fromHexToBytes(self::DEACTIVATED_VERSION_ID);

        $neosPageCount = (int) $connection->executeQuery('SELECT COUNT(*) FROM nlx_neos_node')->fetchOne();
        if ($neosPageCount === 0) {
            return;
        }

        $this->assertNoNeosPagesAreDefault($connection);

        $connection->beginTransaction();
        try {
            $connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page
                    SET version_id = :deactivated
                    WHERE version_id = :live
                      AND id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page_translation
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE category
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE product
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE landing_page
                    SET cms_page_version_id = :deactivated
                    WHERE cms_page_version_id = :live
                      AND cms_page_id IN (SELECT cms_page_id FROM nlx_neos_node)
                SQL,
                ['deactivated' => $deactivatedVersionId, 'live' => $liveVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE nlx_neos_node
                    SET version_id = :deactivated
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['deactivated' => $deactivatedVersionId]
            );

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new RuntimeException(
                'Failed to deactivate Neos CMS pages: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function restoreNeosPages(): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $deactivatedVersionId = Uuid::fromHexToBytes(self::DEACTIVATED_VERSION_ID);

        $deactivatedCount = (int) $connection->executeQuery(
            'SELECT COUNT(*) FROM cms_page WHERE version_id = :deactivated',
            ['deactivated' => $deactivatedVersionId]
        )->fetchOne();

        if ($deactivatedCount === 0) {
            return;
        }

        $connection->beginTransaction();
        try {
            $connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page
                    SET version_id = :live
                    WHERE version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE cms_page_translation
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE category
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE product
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE landing_page
                    SET cms_page_version_id = :live
                    WHERE cms_page_version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $connection->executeStatement(
                <<<'SQL'
                    UPDATE nlx_neos_node
                    SET version_id = :live
                    WHERE cms_page_version_id = :live
                      AND version_id = :deactivated
                SQL,
                ['live' => $liveVersionId, 'deactivated' => $deactivatedVersionId]
            );

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new RuntimeException(
                'Failed to restore Neos CMS pages: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function assertNoNeosPagesAreDefault(Connection $connection): void
    {
        $cmsPageIds = $connection->executeQuery(
            'SELECT cms_page_id FROM nlx_neos_node'
        )->fetchAllAssociative();

        $defaultIdsQueryResult = $connection->executeQuery(
            'SELECT configuration_value FROM system_config WHERE configuration_key LIKE "%cms.default_%"'
        )->fetchAllAssociative();

        $defaultCmsPageIds = $this->extractDefaultCmsPageIds($defaultIdsQueryResult);

        foreach ($cmsPageIds as $queryRow) {
            $cmsPageId = Uuid::fromBytesToHex($queryRow['cms_page_id']);
            if (in_array($cmsPageId, $defaultCmsPageIds, true)) {
                throw new RuntimeException(
                    'Cannot deactivate Neos CMS pages: Page with ID ' . $cmsPageId .
                    ' is set as default CMS page. Please change the default CMS page before deactivating.'
                );
            }
        }
    }

    private function removeCmsPages(): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        $cmsPageIds = $connection->executeQuery(
            'SELECT cms_page_id FROM nlx_neos_node'
        )->fetchAllAssociative();


        $defaultIdsQueryResult = $connection->executeQuery(
            'SELECT configuration_value FROM system_config WHERE configuration_key LIKE "%cms.default_%"'
        )->fetchAllAssociative();

        $defaultCmsPageIds = $this->extractDefaultCmsPageIds($defaultIdsQueryResult);

        foreach ($cmsPageIds as $queryRow) {
            $cmsPageId = $queryRow['cms_page_id'];
            $cmsPageId = Uuid::fromBytesToHex($cmsPageId);
            if (in_array($cmsPageId, $defaultCmsPageIds)) {
                throw new RuntimeException(
                    'Cannot remove Neos CMS page with ID ' . $cmsPageId . ' because it is set as default CMS page. Please change the default CMS page before deactivating.'
                );
            }
        }

        $connection->executeStatement(
            <<<SQL
                    UPDATE category
                    SET cms_page_id = NULL
                    WHERE cms_page_id IN (
                        SELECT cms_page_id FROM nlx_neos_node
                    );
                SQL
        );

        $connection->executeStatement(
            <<<SQL
                    UPDATE product
                    SET cms_page_id = NULL
                    WHERE cms_page_id IN (
                        SELECT cms_page_id FROM nlx_neos_node
                    );
                SQL
        );

        $connection->executeStatement(
            <<<SQL
                    DELETE FROM cms_page
                    WHERE id IN (
                        SELECT cms_page_id FROM nlx_neos_node
                    );
                SQL
        );

        //FIXME Drop nlx_neos_node table, currently not possible due to creation with migrations
        $connection->executeStatement(
            <<<SQL
                    DELETE FROM nlx_neos_node;
                SQL
        );
    }

    private function extractDefaultCmsPageIds(array $defaultCmsPageIds): array
    {
        $ids = [];
        foreach ($defaultCmsPageIds as $defaultCmsPageId) {
            if (isset($defaultCmsPageId['configuration_value'])) {
                // Configuration value is stored like: {"_value":"0195b441757773ea96e225bb1fa260fe"}
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
