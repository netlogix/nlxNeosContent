<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use nlxNeosContent\Service\CachingInvalidationService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function load(Request $request, Context $context): Response
    {
        $this->cacheInvalidationService->invalidateCachesForNeosCmsPages($context);

        return new JsonResponse(['status' => 'Neos Content Cache invalidated']);
    }
}
