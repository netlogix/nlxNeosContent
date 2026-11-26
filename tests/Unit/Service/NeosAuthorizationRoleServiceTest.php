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
        $upserted = [];
        $aclRoleRepository = $this->createMock(EntityRepository::class);
        $aclRoleRepository->method('upsert')->willReturnCallback(function (array $data) use (&$upserted) {
            $upserted = $data[0];

            return $this->createStub(\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent::class);
        });

        (new NeosAuthorizationRoleService($aclRoleRepository, $this->createMock(EntityRepository::class)))
            ->$refreshMethod(Context::createDefaultContext());

        static::assertSame($roleName, $upserted['name']);
        foreach (['category.viewer', 'category:read', 'category_translation:read', 'seo_url:read', 'custom_field_set:read'] as $privilege) {
            static::assertContains($privilege, $upserted['privileges']);
        }
    }
}
