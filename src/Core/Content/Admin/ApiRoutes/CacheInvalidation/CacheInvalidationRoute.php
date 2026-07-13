<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin\ApiRoutes\CacheInvalidation;

use nlxNeosContent\Core\Content\Admin\Dto\CacheInvalidationDto;
use nlxNeosContent\Service\CachingInvalidationService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class CacheInvalidationRoute extends AbstractCacheInvalidationRoute
{
    public function __construct(
        private readonly CachingInvalidationService $cacheInvalidationService
    ) {
    }

    public function getDecorated(): AbstractCacheInvalidationRoute
    {
        return $this;
    }

    #[Route(path: '/api/_action/neos/clear-cache', name: 'api.neos.clear-cache', methods: ['POST'])]
    public function load(
        #[MapRequestPayload(acceptFormat: 'json')]
        CacheInvalidationDto $cacheInvalidationDto,
        Context $context
    ): Response
    {
        if ($cacheInvalidationDto->getType() === CacheInvalidationDto::TYPE_ALL) {
            $this->cacheInvalidationService->invalidateNeosCmsLayoutCaches([], $context);
            $this->cacheInvalidationService->invalidateNavigationCaches();
            return new JsonResponse(['status' => 'Neos Content Cache invalidated']);
        }

        if ($cacheInvalidationDto->getType() === CacheInvalidationDto::TYPE_LAYOUTS) {
            $this->cacheInvalidationService->invalidateNeosCmsLayoutCaches($cacheInvalidationDto->getData(), $context);
        } elseif ($cacheInvalidationDto->getType() === CacheInvalidationDto::TYPE_NAVIGATION)
        {
            $this->cacheInvalidationService->invalidateNavigationCaches();
        }

        return new JsonResponse(['status' => 'Neos Content Cache invalidated']);
    }
}
