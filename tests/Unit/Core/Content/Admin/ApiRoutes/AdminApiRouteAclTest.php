<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Content\Admin\ApiRoutes;

use nlxNeosContent\Core\Content\Admin\ApiRoutes\CacheInvalidation\CacheInvalidationRoute;
use nlxNeosContent\Core\Content\Admin\ApiRoutes\NeosProxy\NeosProxyRoute;
use nlxNeosContent\Core\Content\Admin\ApiRoutes\NeosToken\NeosTokenRoute;
use nlxNeosContent\Core\Content\Admin\ApiRoutes\SeoUrlTemplatePreview\SeoUrlTemplatePreviewRoute;
use nlxNeosContent\Core\Content\Admin\ApiRoutes\UpdateNeosPages\UpdateNeosPagesRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

class AdminApiRouteAclTest extends TestCase
{
    public static function routes(): iterable
    {
        yield 'clear-cache' => [CacheInvalidationRoute::class, 'load', ['neos:edit']];
        yield 'update-neos-pages' => [UpdateNeosPagesRoute::class, 'load', ['neos:edit']];
        yield 'token' => [NeosTokenRoute::class, 'load', ['neos:read']];
        yield 'seo-url-template/preview' => [SeoUrlTemplatePreviewRoute::class, 'load', ['system_config:read']];
        yield 'page-tree' => [NeosProxyRoute::class, 'loadPageTree', ['category:read']];
        yield 'layout' => [NeosProxyRoute::class, 'createLayout', ['neos:edit']];
    }

    /**
     * @param class-string $routeClass
     * @param list<string> $expectedAcl
     */
    #[DataProvider('routes')]
    public function testRouteRequiresPrivileges(string $routeClass, string $method, array $expectedAcl): void
    {
        $attributes = (new \ReflectionMethod($routeClass, $method))->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);

        static::assertCount(1, $attributes);
        static::assertSame($expectedAcl, $attributes[0]->getArguments()['defaults']['_acl'] ?? null);
    }
}
