<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Service;

use nlxNeosContent\Service\NeosAuthorizationRoleService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class NeosAuthorizationRoleServiceTest extends TestCase
{
    public static function roles(): iterable
    {
        yield 'viewer' => ['refreshNeosViewerRole', 'Neos-Viewer'];
        yield 'editor' => ['refreshNeosEditorRole', 'Neos-Editor'];
    }

    #[DataProvider('roles')]
    public function testRoleCanViewCategories(string $refreshMethod, string $roleName): void
    {
        $upserted = $this->refresh($refreshMethod);

        static::assertSame($roleName, $upserted['name']);
        foreach (['category.viewer', 'category:read', 'category_translation:read', 'seo_url:read', 'custom_field_set:read'] as $privilege) {
            static::assertContains($privilege, $upserted['privileges']);
        }
    }

    public function testBothRolesGetTheBasicPrivilegesAndCanReadTheSystemConfig(): void
    {
        foreach (['refreshNeosViewerRole', 'refreshNeosEditorRole'] as $refreshMethod) {
            $privileges = $this->refresh($refreshMethod)['privileges'];
            foreach ([...NeosAuthorizationRoleService::BASIC_PRIVILEGES, 'system_config:read', 'user:read', 'acl_role:read'] as $privilege) {
                static::assertContains($privilege, $privileges, $refreshMethod);
            }
        }
    }

    public function testEditorHasEverythingTheViewerHasPlusTheEditorPrivileges(): void
    {
        $viewer = $this->refresh('refreshNeosViewerRole')['privileges'];
        $editor = $this->refresh('refreshNeosEditorRole')['privileges'];

        static::assertSame([], array_diff($viewer, $editor));
        static::assertEqualsCanonicalizing(NeosAuthorizationRoleService::NEOS_EDITOR_PRIVILEGES, array_values(array_diff($editor, $viewer)));
    }

    public function testNeosTokenPrivilegesAreTheBasicAndTheOptionalOnes(): void
    {
        $token = NeosAuthorizationRoleService::getNeosTokenPrivileges();

        static::assertEqualsCanonicalizing(
            [...NeosAuthorizationRoleService::BASIC_PRIVILEGES, ...NeosAuthorizationRoleService::NEOS_OPTIONAL_TOKEN_PRIVILEGES],
            $token
        );
        static::assertNotContains('system_config:read', $token);
        static::assertSame([], array_diff(NeosAuthorizationRoleService::NEOS_OPTIONAL_TOKEN_PRIVILEGES, NeosAuthorizationRoleService::NEOS_EDITOR_PRIVILEGES));
    }

    /**
     * @return array<string, mixed>
     */
    private function refresh(string $refreshMethod): array
    {
        $upserted = [];
        $aclRoleRepository = $this->createMock(EntityRepository::class);
        $aclRoleRepository->method('upsert')->willReturnCallback(function (array $data) use (&$upserted) {
            $upserted = $data[0];

            return $this->createStub(\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent::class);
        });

        (new NeosAuthorizationRoleService($aclRoleRepository, $this->createMock(EntityRepository::class)))
            ->$refreshMethod(Context::createDefaultContext());

        return $upserted;
    }
}
