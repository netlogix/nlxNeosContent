<?php

declare(strict_types=1);

namespace netlogixNeosContent\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NeosAuthorizationRoleService
{
    const NEOS_VIEWER = 'Neos-Viewer';
    const NEOS_EDITOR = 'Neos-Editor';

    private Context $context;
    /**
     * The first four privileges are always given to an "empty" ACL role
     * which makes sense since otherwise the admin area throws Errors at the user
     *
     * 'system_config:read', 'acl_role:read' & 'user:read' are necessary for the Neos authentication
     */
    private array $basicPrivileges = [
        'language:read',
        'locale:read',
        "log_entry:create",
        "message_queue_stats:read",
        'system_config:read',
        'acl_role:read',
        'user:read'
    ];

    public function __construct(
        #[Autowire(service: 'acl_role.repository')]
        private readonly EntityRepository $aclRoleRepository,
        #[Autowire(service: 'user.repository')]
        private readonly EntityRepository $userRepository,
    ) {
        $this->context = Context::createDefaultContext();
    }

    public function createNeosViewerRole(): void
    {
        $this->installRole(
            $this::NEOS_VIEWER,
            'This role has viewing privileges for Neos',
            [
                'neos.viewer',
                'nlx_neos_node:read',
                'neos:read'
            ]
        );
    }

    public function createNeosEditorRole(): void
    {
        $this->installRole(
            $this::NEOS_EDITOR,
            'This role has all editorial privileges for the Neos CMS',
            [
                'neos.viewer',
                'nlx_neos_node:read',
                'neos:read',
                'neos.editor',
                'neos:edit',
                'nlx_neos_node:update'
            ]
        );
    }

    private function installRole(string $roleName, string $description, array $privileges): void
    {
        $roleId = $this->getRoleIdFromName($roleName);
        if ($this->roleExists($roleId)) {
            return;
        }

        $roleData = [
            'id' => $roleId,
            'name' => $roleName,
            'description' => $description,
            'privileges' => array_merge($this->basicPrivileges, $privileges)
        ];

        $this->aclRoleRepository->upsert([$roleData], $this->context);
    }

    private function roleExists(string $roleId): bool
    {
        /** @var EntityCollection $roles */
        $roles = $this->getRoleById($roleId);
        if ($roles->count() === 0) {
            return false;
        } elseif ($roles->count() === 1) {
            return true;
        } else {
            throw new \Exception('Multiple roles with the same ID found', 1742476289);
        }
    }

    public function removeNeosViewerRole(): void
    {
        $this->removeRole($this::NEOS_VIEWER);
    }

    public function removeNeosEditorRole(): void
    {
        $this->removeRole($this::NEOS_EDITOR);
    }

    private function removeRole(
        string $roleName
    ): void {
        $roleId = $this->getRoleIdFromName($roleName);
        if (!$this->roleExists($roleId)) {
            //Role does not exist nothing to do
            return;
        }

        $userCriteria = new Criteria();
        $userCriteria->addAssociation('aclRoles');
        $userCriteria->addFilter(new EqualsFilter('aclRoles.id', $roleId));

        $users = $this->userRepository->search($userCriteria, $this->context)->getEntities();

        /** @var UserEntity $user */
        foreach ($users as $user) {
            $updatedRoles = $user->getAclRoles()->filter(fn ($r) => $r->getId() !== $roleId);

            if ($updatedRoles->count() === 0) {
                continue;
            }

            $this->userRepository->update([
                [
                    'id' => $user->getId(),
                    'aclRoles' => $updatedRoles->map((fn ($r) => ['id' => $r->getId()]))
                ]
            ], $this->context);
        }

        $this->aclRoleRepository->delete([['id' => $roleId]], $this->context);
    }

    private function getRoleIdFromName(string $roleName): string
    {
        return md5($roleName);
    }

    private function getRoleById(string $roleId): EntityCollection
    {
        return $this->aclRoleRepository->search(new Criteria([$roleId]), Context::createDefaultContext())->getEntities(
        );
    }
}
