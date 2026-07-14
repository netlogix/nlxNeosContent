<?php

namespace nlxNeosContent\Service;


use nlxNeosContent\Neos\Endpoint\CachedNeosPageTreeLoader;
use nlxNeosContent\Storefront\Controller\NeosPageController;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class CachingInvalidationService
{
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly EntityRepository $cmsPageRepository,
    ) {
    }

    public function invalidateNeosCmsLayoutCaches(array $cmsPageIds, Context $context): void
    {
        if (empty($cmsPageIds)) {
            $criteria = new Criteria();
            $criteria->addAssociation('nlxNeosNode');
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('nlxNeosNode.id', null),
                    ]
                )
            );
            $neosCmsPageIds = $this->cmsPageRepository->searchIds(
                $criteria,
                $context
            );

            $cmsPageIds = $neosCmsPageIds->getIds();
        }

        $tags = array_map(fn (string $id) => 'cms-page-' . $id, $cmsPageIds);

        $this->cacheInvalidator->invalidate($tags);
        $this->cacheInvalidator->invalidateExpired();
        $this->cacheInvalidator->invalidate(['nlxNeosContent']);
    }

    public function invalidateNavigationCaches(): void
    {
        $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG, CachedNeosPageTreeLoader::CACHE_KEY]);
        $this->cacheInvalidator->invalidateExpired();
        $this->cacheInvalidator->invalidate(['nlxNeosContent']);
    }

    public function invalidateNeosPageCaches(array $identifiers = []): void
    {
        if (empty($identifiers)) {
            $this->cacheInvalidator->invalidate([NeosPageController::CACHE_TAG_ALL]);

            return;
        }

        $tags = array_map(
            fn (string $identifier) => NeosPageController::getCacheTagFromIdentifier($identifier),
            $identifiers
        );

        $this->cacheInvalidator->invalidate($tags);
    }
}
