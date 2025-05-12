<?php

declare(strict_types=1);

namespace nlxNeosContent\Decorator;

use nlxNeosContent\Service\NeosLayoutPageService;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsDecorator(decorates: ApiController::class)]
class ApiControllerDecorator extends AbstractController
{
    /**
     * FIXME This works but we have some problems
     *
     * 1. we dont want to redefine all these methods
     * 2. When loading the "Shopping Experiences" the ApiController receives for whatever reason two identical requests this causes our logic to be executed twice.
     *      That might be the case in more places than just there and might therefore result in multiple doubled cmsPages
     *
     * Conclusion: We need to find another way of triggering the Logic that is beeing executed in NeosLayoutPageService and remove this decorator.
     */

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractController $inner,
        private readonly NeosLayoutPageService $neosLayoutPageService,
    ) {
    }

    public function clone(Context $context, string $entity, string $id, Request $request): JsonResponse
    {
        return $this->inner->clone($context, $entity, $id, $request);
    }

    public function createVersion(Request $request, Context $context, string $entity, string $id): Response
    {
        return $this->inner->createVersion($request, $context, $entity, $id);
    }

    public function mergeVersion(Context $context, string $entity, string $versionId): JsonResponse
    {
        return $this->inner->mergeVersion($context, $entity, $versionId);
    }

    public function deleteVersion(Context $context, string $entity, string $entityId, string $versionId): JsonResponse
    {
        return $this->inner->deleteVersion($context, $entity, $entityId, $versionId);
    }

    public function detail(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->inner->detail($request, $context, $responseFactory, $entityName, $path);
    }

    public function searchIds(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->inner->searchIds($request, $context, $responseFactory, $entityName, $path);
    }

    public function search(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $entityName,
        string $path
    ): Response {
        //Only decorate for the cms_page entity if it is in a list request
        if ($entityName !== 'cms-page' || ($request->getPayload()->has('limit') && $request->getPayload()->get(
                    'limit'
                ) <= 1)) {
            return $this->inner->search($request, $context, $responseFactory, $entityName, $path);;
        }

        $payload = $request->getPayload();
        $this->neosLayoutPageService->createCmsPagesForMissingNeosNodes(
            $payload,
            $context
        );

        return $this->inner->search($request, $context, $responseFactory, $entityName, $path);
    }

    public function aggregate(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->inner->aggregate($request, $context, $responseFactory, $entityName, $path);
    }

    public function list(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->inner->list($request, $context, $responseFactory, $entityName, $path);
    }

    public function create(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->inner->create($request, $context, $responseFactory, $entityName, $path);
    }

    public function update(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->inner->update($request, $context, $responseFactory, $entityName, $path);
    }

    public function delete(Request $request, Context $context, ResponseFactoryInterface $responseFactory, string $entityName, string $path): Response
    {
        return $this->inner->delete($request, $context, $responseFactory, $entityName, $path);
    }
}
