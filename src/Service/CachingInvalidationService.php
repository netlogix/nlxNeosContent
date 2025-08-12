<?php

namespace netlogixNeosContent\Service;


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

    public function invalidateCachesForNeosCmsPages(Context $context): void
    {
        $criteria =  new Criteria();
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

        $tags = array_map(fn (string $id) => 'cms-page-' . $id, $neosCmsPageIds->getIds());

        $this->cacheInvalidator->invalidate($tags);
        $this->cacheInvalidator->invalidateExpired();

        $this->cacheInvalidator->invalidate(['netlogixNeosContent']);
    }
}
