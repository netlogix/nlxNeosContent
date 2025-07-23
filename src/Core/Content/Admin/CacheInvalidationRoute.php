<?php

declare(strict_types=1);

namespace netlogixNeosContent\Core\Content\Admin;

use netlogixNeosContent\Service\CachingInvalidationService;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        return new JsonResponse(['status' => 'Neos Content Cache invalidated']);
    }
}
