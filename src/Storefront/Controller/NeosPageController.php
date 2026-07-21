<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosRedirectResult;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\NeosPageTreeService;
use nlxNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NeosPageController extends StorefrontController
{
    public const CACHE_TAG_ALL = 'nlx-cbp-page';
    public const CACHE_TAG_PREFIX = 'nlx-cbp-page-';

    function __construct(
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
        private readonly NeosPageTreeService $neosPageTreeService,
        #[Autowire(service: GenericPageLoader::class)]
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    function index(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if ($request->isMethod('POST') && !$this->hasFormLikeRequestStructure($request)) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        try {
            $neosContentResult = $request->isMethod('POST')
                ? $this->contentExchangeService->submitFormToNeosByPath(
                    $request->getPathInfo(),
                    $request,
                    $salesChannelContext
                )
                : $this->contentExchangeService->fetchCmsSectionsFromNeosByPath(
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

        if ($neosContentResult instanceof NeosRedirectResult) {
            return new RedirectResponse($neosContentResult->getRedirectPathInfo(), Response::HTTP_SEE_OTHER);
        }

        $sections = $neosContentResult->getSections();
        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            entityName: CategoryDefinition::ENTITY_NAME,
            entityId: $salesChannelContext->getSalesChannel()->getNavigationCategoryId(),
            context: $salesChannelContext,
            request: $request,
        );
        $this->contentExchangeService->loadSlotData($sections->getBlocks(), $resolverContext);
        $cmsPage = new CmsPageEntity();
        $cmsPage->setSections($sections);

        $treeItem = $this->neosPageTreeService->findNodeIdentifierForRequestAndContext($request, $salesChannelContext);
        //Setting NavigationId so the navigation js can display the active page
        $identifier = self::sanitizeNodeIdentifier($treeItem->identifier);
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $request->attributes->set('navigationId', $identifier);
        $request->attributes->set('_route', 'frontend.navigation.page');
        $request->attributes->set('_route_params', [
            'neos' => "1",
            'navigationId' => $identifier,
        ]);

        $page = $this->genericPageLoader->load($request, $salesChannelContext);
        $page->getMetaInformation()->setMetaTitle($treeItem->label);
        //TODO add missing Metadata

        //Adding two cache tags, so we can invalidate a specific cached page or all of them
        $this->cacheTagCollector->addTag(self::getCacheTagFromIdentifier($treeItem->identifier), self::CACHE_TAG_ALL);
        return $this->renderStorefront('@Storefront/storefront/page/neosPage.html.twig', [
            'page' => $page,
            'cmsPage' => $cmsPage,
            'landingPage' => []
        ]);
    }

    private function hasFormLikeRequestStructure(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type', '');
        $isJson = str_starts_with($contentType, 'application/json');
        $isFormEncoded = str_starts_with($contentType, 'multipart/form-data')
            || str_starts_with($contentType, 'application/x-www-form-urlencoded');
        if (!$isJson && !$isFormEncoded) {
            return false;
        }

        $hasBody = $isJson
            ? $request->getContent() !== ''
            : ($request->request->count() > 0 || $request->files->count() > 0);
        if (!$hasBody) {
            return false;
        }

        $origin = $request->headers->get('Origin');
        if ($origin !== null && rtrim($origin, '/') !== rtrim($request->getSchemeAndHttpHost(), '/')) {
            return false;
        }

        return true;
    }

    public static function sanitizeNodeIdentifier(string $identifier): string
    {
        return str_replace('-', '', $identifier);
    }

    public static function getCacheTagFromIdentifier(string $identifier): string
    {
        $identifier = self::sanitizeNodeIdentifier($identifier);
        return self::CACHE_TAG_PREFIX . $identifier;
    }
}
