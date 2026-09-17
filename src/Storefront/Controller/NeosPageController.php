<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosRedirectResult;
use nlxNeosContent\Neos\HeadTag\HreflangLink;
use nlxNeosContent\Neos\HeadTag\JsonLdUrlRewriter;
use nlxNeosContent\Neos\HeadTag\NeosHeadDataFactory;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\NeosPageTreeService;
use nlxNeosContent\Service\ResolverContextService;
use nlxNeosContent\Twig\NeosPagePathExtension;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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
    public const HEAD_TAGS_EXTENSION = 'neosHeadTags';

    function __construct(
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
        private readonly NeosPageTreeService $neosPageTreeService,
        #[Autowire(service: GenericPageLoader::class)]
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly NeosHeadDataFactory $neosHeadDataFactory,
        private readonly JsonLdUrlRewriter $jsonLdUrlRewriter,
        #[Autowire(service: 'sales_channel.category.repository')]
        private readonly SalesChannelRepository $categoryRepository,
        private readonly NeosPagePathExtension $neosPagePathExtension,
    ) {
    }

    function index(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if ($request->isMethod('POST') && !$this->hasFormLikeRequestStructure($request)) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        return $this->renderPath($request->getPathInfo(), $request, $salesChannelContext);
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

            return new RedirectResponse($neosContentResult->getRedirectPathInfo(), $statusCode);
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

        $breadcrumb = $this->neosPageTreeService->findAncestorChainForPathAndContext($pathInfo, $salesChannelContext);
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

        $breadcrumb = $this->prependHomeCategoryBreadcrumbItem($breadcrumb, $salesChannelContext);
        // Resolving the url only for the (few) items actually rendered here, not eagerly
        // for the whole tree - the tree-sourced NeosPageDTOs otherwise leave it null.
        $breadcrumbItems = array_map(
            fn (NeosPageDTO $item): NeosPageDTO => new NeosPageDTO(
                identifier: $item->identifier,
                label: $item->label,
                path: $item->path,
                children: $item->children,
                hiddenInIndex: $item->hiddenInIndex,
                url: $this->neosPagePathExtension->getNeosPageUrl($item->path),
            ),
            iterator_to_array($breadcrumb)
        );

        return $this->renderStorefront('@Storefront/storefront/page/neosPage.html.twig', [
            'page' => $page,
            'cmsPage' => $cmsPage,
            'landingPage' => [],
            'navigationExtensionDisabled' => $navigationExtensionDisabled,
            'breadcrumb' => $breadcrumbItems,
        ]);
    }

    /**
     * Prepends the sales channel's Home category (its navigation root) as the first
     * breadcrumb item, named after however it's set up in the Administration - mirroring
     * how Neos itself always shows the site name as the first breadcrumb item.
     */
    private function prependHomeCategoryBreadcrumbItem(
        NeosPageCollection $breadcrumb,
        SalesChannelContext $salesChannelContext
    ): NeosPageCollection {
        $homeCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        $homeCategory = $this->categoryRepository
            ->search(new Criteria([$homeCategoryId]), $salesChannelContext)
            ->getEntities()
            ->first();

        $homeLabel = $homeCategory instanceof CategoryEntity ? $homeCategory->getTranslated()['name'] ?? null : null;
        if (empty($homeLabel)) {
            return $breadcrumb;
        }

        // The category is read outside the sales channel's own cache-tagged read trace,
        // so tag explicitly: a renamed/moved Home category should invalidate this page too.
        $this->cacheTagCollector->addTag(NavigationRoute::ALL_TAG);

        return new NeosPageCollection(
            new NeosPageDTO($homeCategoryId, $homeLabel, '', new NeosPageCollection()),
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
