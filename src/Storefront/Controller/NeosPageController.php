<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use nlxNeosContent\Error\PageTree\NoTreeItemFoundException;
use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use nlxNeosContent\Neos\DTO\NeosResults\NeosAssetResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosRedirectResult;
use nlxNeosContent\Neos\HeadTag\HreflangLink;
use nlxNeosContent\Neos\HeadTag\JsonLdUrlRewriter;
use nlxNeosContent\Neos\HeadTag\NeosHeadDataFactory;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\NeosPageTreeService;
use nlxNeosContent\Service\ResolverContextService;
use nlxNeosContent\Service\ShopwareLinkRedirectResolver;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NeosPageController extends StorefrontController
{
    public const CACHE_TAG_ALL = 'nlx-cbp-page';
    public const CACHE_TAG_PREFIX = 'nlx-cbp-page-';
    public const HEAD_TAGS_EXTENSION = 'neosHeadTags';
    public const CONTENT_BY_PATH_ROUTE_PREFIX = '/neos-cms/';

    /**
     * Request attribute only Router::matchNeosPath() and NeosAwareSeoResolver ever set - both
     * confirm the path actually exists in the current tree before routing here. Its absence
     * means this route was matched directly from a raw request path, which skips that check
     * entirely and would otherwise turn this internal route into a second, public URL for
     * whatever content Neos happens to have at that path.
     */
    public const INTERNAL_DISPATCH_ATTRIBUTE = '_nlxNeosContentInternalDispatch';

    function __construct(
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
        private readonly NeosPageTreeService $neosPageTreeService,
        #[Autowire(service: GenericPageLoader::class)]
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly NeosHeadDataFactory $neosHeadDataFactory,
        private readonly JsonLdUrlRewriter $jsonLdUrlRewriter,
        private readonly ShopwareLinkRedirectResolver $shopwareLinkRedirectResolver,
    ) {
    }

    #[Route(
        path: self::CONTENT_BY_PATH_ROUTE_PREFIX . '{path}',
        name: 'frontend.neos.content-by-path',
        requirements: ['path' => '.+'],
        defaults: [
            '_routeScope' => ['storefront'],
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            // Neos pages are content/CMS, not checkout - same reasoning Shopware's own
            // frontend.maintenance.singlepage applies to keep CMS pages (imprint, privacy, ...)
            // reachable while maintenance mode blocks the shop itself.
            PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true,
        ],
        methods: ['GET', 'POST'],
    )]
    function index(Request $request, SalesChannelContext $salesChannelContext, string $path): Response
    {
        // A raw request to this path never carries INTERNAL_DISPATCH_ATTRIBUTE - only our own
        // fallback/rewrite logic sets it, after having already confirmed the path belongs to
        // the current tree. Without it, this would otherwise double as a public, un-vetted URL
        // for the same content the tree-based resolution already serves through its real path.
        if ($request->attributes->get(self::INTERNAL_DISPATCH_ATTRIBUTE) !== true) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST') && !$this->hasFormLikeRequestStructure($request)) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        return $this->renderPath('/' . $path, $request, $salesChannelContext);
    }

    /**
     * Renders a Neos-authored page's content as a bare, chrome-less fragment - the same
     * "widget" shape Shopware's own CmsController::page() renders CMS pages as for
     * data-ajax-modal use (fetch by XHR, drop the response into a modal). Unlike index(), this
     * is a directly routable, always-public endpoint - no page tree lookup by path is needed,
     * since the identifier itself is the input (as stored by, say, a footer link or the basic
     * information "shop page or Neos page" override), and there's no bare-path/full-page
     * ambiguity to gate against.
     */
    #[Route(
        path: '/widgets/neos-cms/{identifier}',
        name: 'frontend.neos.content-widget',
        defaults: [
            '_routeScope' => ['storefront'],
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: ['GET'],
    )]
    public function widget(string $identifier, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $normalizedIdentifier = self::sanitizeNodeIdentifier($identifier);

        $pathInfo = $this->neosPageTreeService->findPathInfoForIdentifierAndContext($normalizedIdentifier, $salesChannelContext);
        if ($pathInfo === null || $pathInfo === '') {
            throw $this->createNotFoundException();
        }

        try {
            $neosContentResult = $this->contentExchangeService->fetchCmsSectionsFromNeosByPath($pathInfo, $salesChannelContext);
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                throw $this->createNotFoundException(previous: $e);
            }

            throw $e;
        }

        if (!$neosContentResult instanceof NeosContentResult) {
            // A widget embed only ever wants page content - a redirect or a raw asset at this
            // path isn't something that makes sense to show inline.
            throw $this->createNotFoundException();
        }

        $cmsPage = $this->buildCmsPageFromContentResult($neosContentResult, $request, $salesChannelContext);

        $this->cacheTagCollector->addTag(
            self::getCacheTagFromIdentifier($normalizedIdentifier),
            self::CACHE_TAG_ALL,
        );

        return $this->renderStorefront('@Storefront/storefront/page/content/detail.html.twig', [
            'cmsPage' => $cmsPage,
        ]);
    }

    /**
     * Renders a Neos-authored page (fetched from Neos by its bare content path) as a Shopware
     * storefront page. Shared by the navigation-extension fallback route (index(), path taken
     * from the current request) and PreviewController::loadPagePreview() (path taken from a
     * query parameter, so it can preview a path the request itself isn't for).
     */
    public function renderPath(
        string $pathInfo,
        Request $request,
        SalesChannelContext $salesChannelContext,
        bool $navigationExtensionDisabled = false
    ): Response {
        try {
           $neosContentResult = match($request->getMethod()){
               'POST' => $this->contentExchangeService->submitFormToNeosByPath(
                        $pathInfo,
                        $request,
                        $salesChannelContext
                    ),
           default =>  $this->contentExchangeService->fetchCmsSectionsFromNeosByPath(
                        $pathInfo,
                        $salesChannelContext
                    )
                };
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                throw $this->createNotFoundException(previous: $e);
            } else {
                throw $e;
            }
        }

        if ($neosContentResult instanceof NeosRedirectResult) {
            // Force 303 after a POST regardless of what Neos answered, so the browser GETs the
            // target instead of re-submitting the form body to it (standard post-redirect-get).
            $statusCode = $request->isMethod('POST') ? Response::HTTP_SEE_OTHER : $neosContentResult->getStatusCode();

            $redirectPathInfo = $neosContentResult->getRedirectPathInfo();
            $currentDomain = $this->contentExchangeService->getCurrentDomain($salesChannelContext);

            $shopwareLinkTarget = $this->shopwareLinkRedirectResolver->resolve(
                $redirectPathInfo,
                $currentDomain->getUrl(),
                $salesChannelContext
            );
            if ($shopwareLinkTarget !== null) {
                return new RedirectResponse($shopwareLinkTarget, $statusCode);
            }

            if (parse_url($redirectPathInfo, PHP_URL_SCHEME) !== null) {
                // Already a full, external URL (ContentExchangeService::extractRedirectPathInfo()
                // only hands back an absolute URL for a target outside this Neos - re-rooting it
                // under Shopware's own domain below would turn a real external target into a
                // broken, made-up one).
                return new RedirectResponse($redirectPathInfo, $statusCode);
            }

            return new RedirectResponse(
                $this->prependDomainPathUnlessPresent($currentDomain->getUrl(), $redirectPathInfo),
                $statusCode
            );
        }

        if ($neosContentResult instanceof NeosAssetResult) {
            return new Response(
                $neosContentResult->getContent(),
                $neosContentResult->getStatusCode(),
                ['Content-Type' => $neosContentResult->getContentType()],
            );
        }

        $cmsPage = $this->buildCmsPageFromContentResult($neosContentResult, $request, $salesChannelContext);

        try {
            $breadcrumb = $this->neosPageTreeService->findAncestorChainForPathAndContext($pathInfo, $salesChannelContext);
        } catch (NoTreeItemFoundException) {
            // Neos returned real content for a path our cached tree (up to 24h stale) doesn't
            // have yet - rare, but rendering it without a breadcrumb isn't worth it; treat it
            // like the miss it still is from the tree's point of view.
            throw $this->createNotFoundException();
        }
        $treeItem = $breadcrumb[count($breadcrumb) - 1];
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

        $headData = $this->neosHeadDataFactory->createHeadData($neosContentResult->getHead());
        $currentDomain = $this->contentExchangeService->getCurrentDomain($salesChannelContext);
        $metaInformation = $page->getMetaInformation();
        $metaInformation->setMetaTitle($headData->getTitle() ?? $treeItem->label);
        if ($headData->getDescription() !== null) {
            $metaInformation->setMetaDescription($headData->getDescription());
        }
        if ($headData->getCanonical() !== null) {
            $metaInformation->setCanonical(rtrim($currentDomain->getUrl(), '/') . '/' . trim($pathInfo, '/'));
        }
        if ($headData->getRobots() !== null) {
            $metaInformation->setRobots($headData->getRobots());
        }
        $headTags = [
            ...$headData->getRemainingHeadData(),
            ...$this->buildHreflangHeadTags($headData->getHreflangLinks(), $salesChannelContext),
            ...$this->buildJsonLdHeadTags($headData->getJsonLdScripts(), $currentDomain, $salesChannelContext),
        ];
        $page->addExtension(self::HEAD_TAGS_EXTENSION, new ArrayStruct($headTags));

        // Tagging with every ancestor's identifier (not just the current page's) so that
        // changing an ancestor invalidates this cached page too, since its breadcrumb depends on them.
        $breadcrumbTags = array_map(
            static fn (NeosPageDTO $ancestor): string => self::getCacheTagFromIdentifier($ancestor->identifier),
            iterator_to_array($breadcrumb)
        );
        $breadcrumbTags[] = self::CACHE_TAG_ALL;
        $this->cacheTagCollector->addTag(...$breadcrumbTags);

        $breadcrumb = $this->prependHomeNameBreadcrumbItem($breadcrumb, $salesChannelContext);

        return $this->renderStorefront('@Storefront/storefront/page/neosPage.html.twig', [
            'page' => $page,
            'cmsPage' => $cmsPage,
            'landingPage' => [],
            'navigationExtensionDisabled' => $navigationExtensionDisabled,
            'breadcrumb' => iterator_to_array($breadcrumb),
        ]);
    }

    private function buildCmsPageFromContentResult(
        NeosContentResult $neosContentResult,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): CmsPageEntity {
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

        return $cmsPage;
    }

    private function prependHomeNameBreadcrumbItem(
        NeosPageCollection $breadcrumb,
        SalesChannelContext $salesChannelContext
    ): NeosPageCollection {
        $salesChannel = $salesChannelContext->getSalesChannel();

        return new NeosPageCollection(
            new NeosPageDTO(
                identifier: $salesChannel->getNavigationCategoryId(),
                label: $salesChannel->getTranslation('homeName') ?: $this->trans('general.homeLink'),
                path: '',
                children: new NeosPageCollection(),
            ),
            ...iterator_to_array($breadcrumb)
        );
    }

    /**
     * @param HreflangLink[] $hreflangLinks
     * @return array<string>
     */
    private function buildHreflangHeadTags(array $hreflangLinks, SalesChannelContext $salesChannelContext): array
    {
        $headTags = [];

        foreach ($hreflangLinks as $hreflangLink) {
            $domain = $this->contentExchangeService->findDomainForHreflangCode($hreflangLink->hreflangCode, $salesChannelContext);
            if ($domain === null) {
                continue;
            }

            $href = rtrim($domain->getUrl(), '/') . '/' . trim($hreflangLink->contentPath, '/');
            $headTags[] = sprintf(
                '<link rel="alternate" hreflang="%s" href="%s">',
                htmlspecialchars($hreflangLink->hreflangCode, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
            );
        }

        return $headTags;
    }

    /**
     * @param array<string> $jsonLdScripts
     * @return array<string>
     */
    private function buildJsonLdHeadTags(
        array $jsonLdScripts,
        SalesChannelDomainEntity $currentDomain,
        SalesChannelContext $salesChannelContext
    ): array {
        $headTags = [];

        foreach ($jsonLdScripts as $jsonLdScript) {
            $rewritten = $this->jsonLdUrlRewriter->rewrite(
                $jsonLdScript,
                $currentDomain,
                $salesChannelContext->getSalesChannelId()
            );
            if ($rewritten === null) {
                continue;
            }

            $headTags[] = '<script type="application/ld+json">' . $rewritten . '</script>';
        }

        return $headTags;
    }

    /**
     * Shopware's routing determines the locale from the domain's own path prefix (e.g. "/de"), so
     * a bare Neos path needs that prefix restored. Neos may already have put it there though.
     */
    private function prependDomainPathUnlessPresent(string $domainUrl, string $path): string
    {
        $path = ltrim($path, '/');
        $domainUrlParts = parse_url($domainUrl);
        $domainPath = trim($domainUrlParts['path'] ?? '', '/');

        if ($domainPath !== '' && preg_match('#^' . preg_quote($domainPath, '#') . '(?:[/?\#]|$)#i', $path) === 1) {
            $origin = $domainUrlParts['scheme'] . '://' . $domainUrlParts['host']
                . (isset($domainUrlParts['port']) ? ':' . $domainUrlParts['port'] : '');

            return $origin . '/' . $path;
        }

        return rtrim($domainUrl, '/') . '/' . $path;
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
