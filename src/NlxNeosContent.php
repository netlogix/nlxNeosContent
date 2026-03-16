<?php

declare(strict_types=1);

namespace nlxNeosContent;

use Doctrine\DBAL\Connection;
use nlxNeosContent\Core\Notification\NotificationService;
use nlxNeosContent\Core\Notification\NotificationService66;
use nlxNeosContent\Core\Notification\NotificationServiceInterface;
use nlxNeosContent\Service\NeosAuthorizationRoleService;
use nlxNeosContent\Service\NeosCmsPageLifecycleService;
use RuntimeException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
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

        $this->getNeosCmsPageLifecycleService()->restoreNeosPages();
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);

        $this->getNeosCmsPageLifecycleService()->deactivateNeosPages();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        //Remove acl roles
        $neosAuthorizationRoleService = $this->getNeosAuthorizationRoleService();
        $neosAuthorizationRoleService->removeNeosViewerRole();
        $neosAuthorizationRoleService->removeNeosEditorRole();

        //Remove cms pages
        $this->getNeosCmsPageLifecycleService()->removeCmsPages();
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

    private function getNeosCmsPageLifecycleService(): NeosCmsPageLifecycleService
    {
        $container = $this->container;
        if (!$container instanceof ContainerInterface) {
            throw new RuntimeException('Container is not an instance of ContainerInterface');
        }

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        return new NeosCmsPageLifecycleService($connection);
    }
}
