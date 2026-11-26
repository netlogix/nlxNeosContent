<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Framework\Api\Routing;

use nlxNeosContent\Core\Framework\Api\Routing\ApiRequestContextResolver;
use nlxNeosContent\Service\NeosAuthorizationRoleService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RequestContextResolverInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class ApiRequestContextResolverTest extends TestCase
{
    public function testViewerTokenCarriesOnlyTheBasicPrivileges(): void
    {
        $permissions = $this->resolveNeosToken($this->source([
            ...NeosAuthorizationRoleService::BASIC_PRIVILEGES,
            ...NeosAuthorizationRoleService::NEOS_VIEWER_PRIVILEGES,
        ]));

        static::assertEqualsCanonicalizing(NeosAuthorizationRoleService::BASIC_PRIVILEGES, $permissions);
    }

    public function testEditorTokenAlsoCarriesTheOptionalNeosPrivileges(): void
    {
        $permissions = $this->resolveNeosToken($this->source([
            ...NeosAuthorizationRoleService::BASIC_PRIVILEGES,
            ...NeosAuthorizationRoleService::NEOS_VIEWER_PRIVILEGES,
            ...NeosAuthorizationRoleService::NEOS_EDITOR_PRIVILEGES,
        ]));

        static::assertEqualsCanonicalizing(
            [...NeosAuthorizationRoleService::BASIC_PRIVILEGES, ...NeosAuthorizationRoleService::NEOS_OPTIONAL_TOKEN_PRIVILEGES],
            $permissions
        );
        static::assertNotContains('system_config:read', $permissions);
    }

    public function testTokenDoesNotCarryTheUserAndRolePrivileges(): void
    {
        $permissions = $this->resolveNeosToken($this->source([
            ...NeosAuthorizationRoleService::BASIC_PRIVILEGES,
            ...NeosAuthorizationRoleService::NEOS_VIEWER_PRIVILEGES,
        ]));

        static::assertNotContains('user:read', $permissions);
        static::assertNotContains('acl_role:read', $permissions);
    }

    public function testTokenNeverCarriesMoreThanItsUserHas(): void
    {
        static::assertSame(['category:read'], $this->resolveNeosToken($this->source(['category:read', 'system_config:read'])));
    }

    public function testAdminTokenIsLimitedToTheNeosPrivileges(): void
    {
        $source = new AdminApiSource('user-id');
        $source->setIsAdmin(true);

        $permissions = $this->resolveNeosToken($source);

        static::assertFalse($source->isAdmin());
        static::assertEqualsCanonicalizing(NeosAuthorizationRoleService::getNeosTokenPrivileges(), $permissions);
    }

    public function testOtherScopesAreLeftAlone(): void
    {
        $source = $this->source(['system_config:read']);
        $request = $this->request($source, ['write']);

        (new ApiRequestContextResolver($this->createMock(RequestContextResolverInterface::class)))->resolve($request);

        static::assertSame(['system_config:read'], $source->getPermissions());
    }

    /**
     * @param list<string> $permissions
     */
    private function source(array $permissions): AdminApiSource
    {
        $source = new AdminApiSource('user-id');
        $source->setPermissions($permissions);

        return $source;
    }

    /**
     * @return list<string>
     */
    private function resolveNeosToken(AdminApiSource $source): array
    {
        (new ApiRequestContextResolver($this->createMock(RequestContextResolverInterface::class)))->resolve($this->request($source, ['neos']));

        return array_values($source->getPermissions());
    }

    /**
     * @param list<string> $scopes
     */
    private function request(AdminApiSource $source, array $scopes): Request
    {
        $request = new Request();
        $request->attributes->set('oauth_scopes', $scopes);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context($source));

        return $request;
    }
}
