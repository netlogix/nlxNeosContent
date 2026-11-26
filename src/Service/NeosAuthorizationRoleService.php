<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

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

    /**
     * What Neos needs from the Shopware API. Every Neos role gets these, and a Neos token carries
     * them as far as its user has them.
     */
    public const BASIC_PRIVILEGES = [
        'category:read',
        'language:read',
        'locale:read',
        'media:read',
        'media_folder:read',
        'media_thumbnail:read',
        'product:read',
        'product_manufacturer:read',
        'product_media:read',
        'product_sorting:read',
        'product_stream:read',
        'property_group:read',
        'property_group_option:read',
        'sales_channel:read',
        'sales_channel_domain:read',
        'sales_channel_type:read',
    ];

    /**
     * Added to the viewer role on top of the basic privileges. Mostly what the administration
     * needs to be usable; Neos never gets these.
     */
    public const NEOS_VIEWER_PRIVILEGES = [
        // Neos reads the user and its roles from /api/_info/me, only the administration uses these.
        'acl_role:read',
        'user:read',
        'log_entry:create',
        'message_queue_stats:read',
        'system_config:read',
        'cms_page:read',
        'neos.viewer',
        'neos:read',
        'nlx_neos_node:read',
        // "View categories" in the administration (category.viewer and the privileges it requires),
        // so both roles can open the category tree with its Neos pages.
        'category.viewer',
        'category_translation:read',
        'cms_block:read',
        'cms_section:read',
        'cms_slot:read',
        'currency:read',
        'custom_field:read',
        'custom_field_set:read',
        'custom_field_set_relation:read',
        'delivery_time:read',
        'document_base_config:read',
        'landing_page:read',
        'mail_template:read',
        'mail_template_media:read',
        'media_default_folder:read',
        'media_folder_configuration:read',
        'media_thumbnail_size:read',
        'payment_method:read',
        'product_cross_selling:read',
        'product_cross_selling_assigned_products:read',
        'seo_url:read',
        'shipping_method:read',
        'tag:read',
    ];

    /**
     * Added to the editor role on top of everything the viewer role has.
     */
    public const NEOS_EDITOR_PRIVILEGES = [
        'cms_page:create',
        'cms_page:update',
        'category:update',
        'neos.editor',
        'neos:edit',
        'nlx_neos_node:create',
        'nlx_neos_node:update',
    ];

    /**
     * Editor privileges Neos uses itself. A Neos token only carries them if its user has them;
     * otherwise Neos has to tell the user what is missing.
     */
    public const NEOS_OPTIONAL_TOKEN_PRIVILEGES = [
        'category:update',
        'neos:edit',
    ];

    public function __construct(
        private readonly EntityRepository $aclRoleRepository,
        private readonly EntityRepository $aclUserRoleRepository,
    ) {
    }

    /**
     * The most a Neos token may carry. The request context of a token is limited to the part of
     * these its user actually has.
     */
    public static function getNeosTokenPrivileges(): array
    {
        return [...self::BASIC_PRIVILEGES, ...self::NEOS_OPTIONAL_TOKEN_PRIVILEGES];
    }

    public function createNeosViewerRole(
        Context $context,
    ): void
    {
        $this->installRole(
            $this::NEOS_VIEWER,
            'This role has viewing privileges for Neos',
            self::NEOS_VIEWER_PRIVILEGES,
            $context
        );
    }

    public function createNeosEditorRole(
        Context $context,
    ): void
    {
        $this->installRole(
            $this::NEOS_EDITOR,
            'This role has all editorial privileges for the Neos CMS',
            [...self::NEOS_VIEWER_PRIVILEGES, ...self::NEOS_EDITOR_PRIVILEGES],
            $context
        );
    }

    public function removeNeosViewerRole(Context $context): void
    {
        $this->removeRole($this::NEOS_VIEWER, $context);
    }

    public function removeNeosEditorRole(Context $context): void
    {
        $this->removeRole($this::NEOS_EDITOR, $context);
    }

    public function refreshNeosViewerRole(Context $context): void
    {
        $this->refreshRole(
            $this::NEOS_VIEWER,
            'This role has viewing privileges for Neos',
            self::NEOS_VIEWER_PRIVILEGES,
            $context
        );
    }

    public function refreshNeosEditorRole(Context $context): void
    {
        $this->refreshRole(
            $this::NEOS_EDITOR,
            'This role has all editorial privileges for the Neos CMS',
            [...self::NEOS_VIEWER_PRIVILEGES, ...self::NEOS_EDITOR_PRIVILEGES],
            $context
        );
    }

    private function installRole(string $roleName, string $description, array $privileges, Context $context): void
    {
        $roleId = $this->getRoleIdFromName($roleName);
        if ($this->roleExists($roleId, $context)) {
            return;
        }

        $this->upsertRole($roleId, $roleName, $description, $privileges, $context);
    }

    private function refreshRole(string $roleName, string $description, array $privileges, Context $context): void
    {
        $roleId = $this->getRoleIdFromName($roleName);

        $this->upsertRole($roleId, $roleName, $description, $privileges, $context);
    }

    private function upsertRole(string $roleId, string $roleName, string $description, array $privileges, Context $context): void
    {
        $roleData = [
            'id' => $roleId,
            'name' => $roleName,
            'description' => $description,
            'privileges' => array_values(array_unique([...self::BASIC_PRIVILEGES, ...$privileges]))
        ];

        $this->aclRoleRepository->upsert([$roleData], $context);
    }

    private function roleExists(string $roleId, Context $context): bool
    {
        /** @var EntityCollection $roles */
        $roles = $this->getRoleById($roleId, $context);
        if ($roles->count() === 0) {
            return false;
        } elseif ($roles->count() === 1) {
            return true;
        } else {
            throw new \Exception('Multiple roles with the same ID found', 1742476289);
        }
    }

    private function removeRole(
        string $roleName,
        Context $context,
    ): void {
        $roleId = $this->getRoleIdFromName($roleName);
        if (!$this->roleExists($roleId, $context)) {
            //Role does not exist, nothing to do
            return;
        }
        $roleCriteria = new Criteria();
        $roleCriteria->setIds([$roleId]);

        /** @var AclRoleEntity $role */
        $role = $this->aclRoleRepository->search($roleCriteria, $context)->getEntities()->first();
        $deleteAclUserRoles = array_map(fn ($id) => [
            'aclRoleId' => $roleId,
            'userId' => $id
        ], $role->getUsers()?->getIds() ?? []);

        if ($deleteAclUserRoles !== []) {
            $this->aclUserRoleRepository->delete($deleteAclUserRoles, $context);
        }

        $this->aclRoleRepository->delete([['id' => $roleId]], $context);
    }

    private function getRoleIdFromName(string $roleName): string
    {
        return md5($roleName);
    }

    private function getRoleById(string $roleId, Context $context): EntityCollection
    {
        return $this->aclRoleRepository->search(new Criteria([$roleId]), $context)->getEntities(
        );
    }
}
