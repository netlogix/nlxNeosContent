<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use GuzzleHttp\Exception\ClientException;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NeosPageController extends StorefrontController
{
    function __construct(
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
    ) {
    }

    function index(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $sections = $this->contentExchangeService->fetchCmsSectionsFromNeosByPath(
                $request->getPathInfo(),
                $salesChannelContext
            );
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                throw $this->createNotFoundException(previous: $e);
            } else {
                throw $e;
            }
        }

        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            CategoryDefinition::ENTITY_NAME,
            '019a7c22388f72efbf7c5219977ba492',
            $salesChannelContext,
            $request
        );
        $this->contentExchangeService->loadSlotData($sections->getBlocks(), $resolverContext);
        $cmsPage = new CmsPageEntity();
        $cmsPage->setSections($sections);


        return $this->renderStorefront('@Storefront/storefront/page/neosPage.html.twig', [
            'cmsPage' => $cmsPage,
            'landingPage' => []
        ]);
    }
}
