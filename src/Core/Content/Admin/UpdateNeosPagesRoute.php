<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Admin;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use nlxNeosContent\Service\NeosLayoutPageService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class UpdateNeosPagesRoute extends AbstractUpdatePagesRoute
{
    public function __construct(
        private readonly NeosLayoutPageService $neosLayoutPageService
    ) {
    }

    public function getDecorated(): AbstractUpdatePagesRoute
    {
        return $this;
    }

    #[Route(path: '/api/_action/neos/update-neos-pages', name: 'api.neos.update-neos-pages', methods: ['POST'])]
    public function load(): Response
    {
        $context = Context::createDefaultContext();
        try {
            $neosPages = $this->neosLayoutPageService->getNeosLayoutPages(
                explode('|', NeosLayoutPageService::AVAILABLE_FILTER_PAGE_TYPES)
            );
        } catch (GuzzleException $e) {
            $this->neosLayoutPageService->createNotification($context);
            throw new Exception('Failed to retrieve Neos layout pages: ' . $e->getMessage(), 1751381726, $e);
        }

        $this->neosLayoutPageService->createMissingNeosCmsPages($neosPages, $context);
        $this->neosLayoutPageService->updateNeosCmsPages($neosPages, $context);
        $this->neosLayoutPageService->removeCmsPagesWithInvalidNodeIdentifiers($neosPages, $context);


        return new JsonResponse(['status' => 'Neos layout pages updated successfully.'], Response::HTTP_OK);
    }
}
