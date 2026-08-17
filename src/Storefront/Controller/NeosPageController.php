<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosRedirectResult;
use nlxNeosContent\Neos\HeadTag\HreflangLink;
use nlxNeosContent\Neos\HeadTag\JsonLdUrlRewriter;
use nlxNeosContent\Neos\HeadTag\NeosHeadDataFactory;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\NeosPageTreeService;
use nlxNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Struct\ArrayStruct;
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
    ) {
    }

    function index(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if ($request->isMethod('POST') && !$this->hasFormLikeRequestStructure($request)) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        try {
           $neosContentResult = match($request->getMethod()){
               'POST' => $this->contentExchangeService->submitFormToNeosByPath(
                        $request->getPathInfo(),
                        $request,
                        $salesChannelContext
                    ),
           default =>  $this->contentExchangeService->fetchCmsSectionsFromNeosByPath(
                        $request->getPathInfo(),
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

        $headData = $this->neosHeadDataFactory->createHeadData($neosContentResult->getHead());
        $currentDomain = $this->contentExchangeService->getCurrentDomain($salesChannelContext);
        $metaInformation = $page->getMetaInformation();
        $metaInformation->setMetaTitle($headData->getTitle() ?? $treeItem->label);
        if ($headData->getDescription() !== null) {
            $metaInformation->setMetaDescription($headData->getDescription());
        }
        if ($headData->getCanonical() !== null) {
            $metaInformation->setCanonical(rtrim($currentDomain->getUrl(), '/') . '/' . trim($request->getPathInfo(), '/'));
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

        //Adding two cache tags, so we can invalidate a specific cached page or all of them
        $this->cacheTagCollector->addTag(self::getCacheTagFromIdentifier($treeItem->identifier), self::CACHE_TAG_ALL);
        return $this->renderStorefront('@Storefront/storefront/page/neosPage.html.twig', [
            'page' => $page,
            'cmsPage' => $cmsPage,
            'landingPage' => []
        ]);
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
