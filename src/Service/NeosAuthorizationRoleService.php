<?php

declare(strict_types=1);

namespace netlogixNeosContent\Service;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class NeosAuthorizationRoleService
{
    public const NEOS_VIEWER = 'Neos-Viewer';
    public const NEOS_EDITOR = 'Neos-Editor';

    private ?Context $context = null;

    /**
     * The first four privileges are always given to an "empty" ACL role
     * which makes sense since otherwise the admin area throws Errors at the user
     *
     * 'system_config:read', 'acl_role:read' & 'user:read' are necessary for the Neos authentication
     */
    public const
        BASIC_PRIVILEGES = [
        'language:read',
        'locale:read',
        "log_entry:create",
        "message_queue_stats:read",
        'system_config:read',
        'acl_role:read',
        'user:read',
        'product:read',
        'category:read',
        'product_manufacturer:read',
        'product_sorting:read',
        'product_media:read',
        'property_group_option:read',
        'property_group:read',
    ],
        NEOS_VIEWER_PRIVILEGES = [
        'neos.viewer',
        'nlx_neos_node:read',
        'neos:read',
        'cms_page:read',
    ],
        NEOS_EDITOR_PRIVILEGES = [
        'neos.viewer',
        'nlx_neos_node:read',
        'neos:read',
        'neos.editor',
        'neos:edit',
        'nlx_neos_node:update',
        'cms_page:update',
    ];

    public function __construct(
        private readonly EntityRepository $aclRoleRepository,
        private readonly EntityRepository $aclUserRoleRepository,
    ) {
    }

    public static function getWhiteListPrivileges(): array
    {
        return array_unique([
            ...self::BASIC_PRIVILEGES,
            ...self::NEOS_VIEWER_PRIVILEGES,
            ...self::NEOS_EDITOR_PRIVILEGES
        ]);
    }

    public function createNeosViewerRole(): void
    {
        $this->installRole(
            $this::NEOS_VIEWER,
            'This role has viewing privileges for Neos',
            self::NEOS_VIEWER_PRIVILEGES
        );
    }

    public function createNeosEditorRole(): void
    {
        $this->installRole(
            $this::NEOS_EDITOR,
            'This role has all editorial privileges for the Neos CMS',
            self::NEOS_EDITOR_PRIVILEGES
        );
    }

    public function removeNeosViewerRole(): void
    {
        $this->removeRole($this::NEOS_VIEWER);
    }

    public function removeNeosEditorRole(): void
    {
        $this->removeRole($this::NEOS_EDITOR);
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
            'privileges' => array_merge(self::BASIC_PRIVILEGES, $privileges)
        ];

        $this->aclRoleRepository->upsert([$roleData], $this->getContext());
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

    private function removeRole(
        string $roleName
    ): void {
        $roleId = $this->getRoleIdFromName($roleName);
        if (!$this->roleExists($roleId)) {
            //Role does not exist, nothing to do
            return;
        }
        $roleCriteria = new Criteria();
        $roleCriteria->setIds([$roleId]);

        /** @var AclRoleEntity $role */
        $role = $this->aclRoleRepository->search($roleCriteria, $this->getContext())->first();
        $deleteAclUserRoles = array_map(fn ($id) => [
            'aclRoleId' => $roleId,
            'userId' => $id
        ], $role->getUsers()?->getIds() ?? []);

        if ($deleteAclUserRoles !== []) {
            $this->aclUserRoleRepository->delete($deleteAclUserRoles, $this->getContext());
        }

        $this->aclRoleRepository->delete([['id' => $roleId]], $this->getContext());
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

    private function getContext(): Context
    {
        return $this->context ??= Context::createDefaultContext();
    }
}
