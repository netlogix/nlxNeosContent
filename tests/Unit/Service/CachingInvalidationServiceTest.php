<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Service;

use nlxNeosContent\Neos\Endpoint\CachedNeosPageTreeLoader;
use nlxNeosContent\Service\CachingInvalidationService;
use nlxNeosContent\Storefront\Controller\NeosPageController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class CachingInvalidationServiceTest extends TestCase
{
    /**
     * @var list<array{list<string>, bool}>
     */
    private array $invalidations = [];

    public function testLayoutPublishOnlyInvalidatesThatLayout(): void
    {
        $this->service()->invalidateNeosCmsLayoutCaches(['layout-id'], Context::createDefaultContext());

        static::assertSame([[['cms-page-layout-id'], true]], $this->invalidations);
    }

    public function testNavigationKeepsPluginWideCaches(): void
    {
        $this->service()->invalidateNavigationCaches();

        static::assertSame([[[NavigationRoute::ALL_TAG, CachedNeosPageTreeLoader::CACHE_KEY], true]], $this->invalidations);
    }

    public function testNeosPagePublishOnlyInvalidatesThatPage(): void
    {
        $this->service()->invalidateNeosPageCaches(['node-id']);

        static::assertSame([[[NeosPageController::getCacheTagFromIdentifier('node-id')], true]], $this->invalidations);
    }

    public function testPluginWideTagIsOnlyInvalidatedOnRequest(): void
    {
        $this->service()->invalidatePluginWideCaches();

        static::assertSame([[[CachingInvalidationService::CACHE_TAG], true]], $this->invalidations);
    }

    private function service(): CachingInvalidationService
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::never())->method('invalidateExpired');
        $cacheInvalidator->method('invalidate')->willReturnCallback(
            function (array $tags, bool $force = false): void {
                $this->invalidations[] = [$tags, $force];
            }
        );

        return new CachingInvalidationService($cacheInvalidator, $this->createMock(EntityRepository::class));
    }
}
