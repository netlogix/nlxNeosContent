<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Content\Admin\ApiRoutes;

use nlxNeosContent\Core\Content\Admin\Dto\CacheInvalidationDto;
use nlxNeosContent\Core\Content\Admin\ApiRoutes\CacheInvalidation\CacheInvalidationRoute;
use nlxNeosContent\Service\CachingInvalidationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class CacheInvalidationRouteTest extends TestCase
{
    public static function types(): iterable
    {
        yield 'all' => [new CacheInvalidationDto(CacheInvalidationDto::TYPE_ALL), 1];
        yield 'layouts' => [new CacheInvalidationDto(CacheInvalidationDto::TYPE_LAYOUTS, ['layout-id']), 0];
        yield 'navigation' => [new CacheInvalidationDto(CacheInvalidationDto::TYPE_NAVIGATION, ['node-id']), 0];
    }

    #[DataProvider('types')]
    public function testOnlyAFullResetClearsPluginWideCaches(CacheInvalidationDto $dto, int $expectedCalls): void
    {
        $service = $this->createMock(CachingInvalidationService::class);
        $service->expects(static::exactly($expectedCalls))->method('invalidatePluginWideCaches');

        (new CacheInvalidationRoute($service))->load($dto, Context::createDefaultContext());
    }
}
