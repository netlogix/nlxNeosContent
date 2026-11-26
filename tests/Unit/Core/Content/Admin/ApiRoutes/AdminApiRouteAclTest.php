<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Content\Admin\ApiRoutes;

use nlxNeosContent\Core\Content\Admin\ApiRoutes\CacheInvalidation\CacheInvalidationRoute;
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
        yield 'clear-cache' => [CacheInvalidationRoute::class, ['neos:edit']];
        yield 'update-neos-pages' => [UpdateNeosPagesRoute::class, ['neos:edit']];
        yield 'token' => [NeosTokenRoute::class, ['neos:read']];
        yield 'seo-url-template/preview' => [SeoUrlTemplatePreviewRoute::class, ['system_config:read']];
    }

    /**
     * @param class-string $routeClass
     * @param list<string> $expectedAcl
     */
    #[DataProvider('routes')]
    public function testRouteRequiresPrivileges(string $routeClass, array $expectedAcl): void
    {
        $attributes = (new \ReflectionMethod($routeClass, 'load'))->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);

        static::assertCount(1, $attributes);
        static::assertSame($expectedAcl, $attributes[0]->getArguments()['defaults']['_acl'] ?? null);
    }
}
