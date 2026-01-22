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
    public function load(Request $request, Context $context): Response
    {
        $content = json_decode(json: $request->getContent(), associative: true) ?? [];
        // If nodes are provided in the request, only process those
        if (!empty($content['updatedNodes'])) {
            $this->neosLayoutPageService->processProvidedNodes($content['updatedNodes'], $context);
            return new JsonResponse(['status' => 'Provided Neos layout pages processed successfully.'], Response::HTTP_OK);
        }

        try {
            $neosPages = $this->neosLayoutPageService->getNeosCmsPageTemplates();
        } catch (GuzzleException $e) {
            $this->neosLayoutPageService->createNotification($context);
            throw new Exception('Failed to retrieve Neos templates: ' . $e->getMessage(), 1751381726, $e);
        }

        $this->neosLayoutPageService->updateNeosCmsPages($neosPages, $context);
        $this->neosLayoutPageService->removeObsoleteCmsPages($neosPages, $context);


        return new JsonResponse(['status' => 'Neos layout pages updated successfully.'], Response::HTTP_OK);
    }
}
