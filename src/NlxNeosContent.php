<?php

declare(strict_types=1);

namespace nlxNeosContent;

use nlxNeosContent\Service\NeosAuthorizationRoleService;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NlxNeosContent extends Plugin
{

    public function update(UpdateContext $updateContext): void
    {
        //FIXME: WSGENIUS-124 remove when releasing v1.0
        parent::update($updateContext);

        $neosAuthorizationRoleService = $this->getNeosAuthorizationRoleService();
        $neosAuthorizationRoleService->removeNeosViewerRole();
        $neosAuthorizationRoleService->removeNeosEditorRole();
        $neosAuthorizationRoleService->createNeosViewerRole();
        $neosAuthorizationRoleService->createNeosEditorRole();

    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $neosAuthorizationRoleService = $this->getNeosAuthorizationRoleService();
        $neosAuthorizationRoleService->createNeosViewerRole();
        $neosAuthorizationRoleService->createNeosEditorRole();
    }

    public function uninstall(Plugin\Context\UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $neosAuthorizationRoleService = $this->getNeosAuthorizationRoleService();
        $neosAuthorizationRoleService->removeNeosViewerRole();
        $neosAuthorizationRoleService->removeNeosEditorRole();
    }

    private function getNeosAuthorizationRoleService(): NeosAuthorizationRoleService
    {
        $container = $this->container;
        if (!$container instanceof ContainerInterface) {
            throw new \RuntimeException('Container is not an instance of ContainerInterface');
        }

        if (!$container->has('acl_role.repository')) {
            throw new \RuntimeException('Service acl_role.repository not found');
        }
        if (!$container->has('user.repository')) {
            throw new \RuntimeException('Service user.repository not found');
        }

        return new NeosAuthorizationRoleService(
            $container->get('acl_role.repository'),
            $container->get('user.repository')
        );
    }

}
