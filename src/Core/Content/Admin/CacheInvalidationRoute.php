<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use nlxNeosContent\Service\CachingInvalidationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class CacheInvalidationRoute extends AbstractAdminRoute
{
    public function __construct(
        private readonly CachingInvalidationService $cacheInvalidationService
    ) {
    }

    public function getDecorated(): AbstractAdminRoute
    {
        return $this;
    }

    #[Route(path: '/api/_action/neos/clear-cache', name: 'api.neos.clear-cache', methods: ['POST'])]
    public function load(): Response
    {
        $this->cacheInvalidationService->invalidateCachesForNeosCmsPages();

        return new Response('Neos Content Cache invalidated');
    }
}
