<?php

declare(strict_types=1);

namespace nlxNeosContent;

use Doctrine\DBAL\Connection;
use nlxNeosContent\Core\Notification\NotificationService;
use nlxNeosContent\Core\Notification\NotificationService66;
use nlxNeosContent\Core\Notification\NotificationServiceInterface;
use nlxNeosContent\Service\NeosAuthorizationRoleService;
use RuntimeException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;


class NlxNeosContent extends Plugin implements CompilerPassInterface
{
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
    }

    public function deactivate(Plugin\Context\DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
    }

    public function uninstall(Plugin\Context\UninstallContext $uninstallContext): void
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
            $cmsPageId = UUID::fromBytesToHex($cmsPageId);
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
