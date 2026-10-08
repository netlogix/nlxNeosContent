<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use nlxNeosContent\Error\RequestError\NeosContentFetchException;
use nlxNeosContent\Neos\DTO\NeosResults\NeosAssetResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosRedirectResult;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ContentExchangeService
{
    private const CONTENT_BY_PATH_URI_PREFIX = '/neos/shopware-api/content-by-path/';

    private const CMS_SECTIONS_CACHE_KEY_PREFIX = 'netlogix_neos_content_cms_page_sections_';

    private const CMS_SECTIONS_CACHE_TTL = 86400;

    public function __construct(
        #[Autowire(service: 'serializer')]
        private readonly DenormalizerInterface $serializer,
        private readonly CmsSlotsDataResolver $cmsSlotsDataResolver,
        private readonly HttpClientInterface $neosClient,
        #[Autowire(service: 'cache.object')]
        private readonly TagAwareCacheInterface $cache,
        private readonly ConfigService $configService,
    ) {
    }

    /**
     * @throws NeosContentFetchException
     */
    public function getAlternativeCmsSectionsFromNeos(
        CmsPageEntity $cmsPage,
        SalesChannelContext $salesChannelContext
    ): CmsSectionCollection {
        $cacheKey = self::CMS_SECTIONS_CACHE_KEY_PREFIX . implode('-', [
            $cmsPage->getId(),
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            $salesChannelContext->getDomainId() ?? '',
        ]);

        return $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($cmsPage, $salesChannelContext) {
                $item->tag([
                    CachingInvalidationService::CMS_PAGE_CACHE_TAG_PREFIX . $cmsPage->getId(),
                    CachingInvalidationService::CACHE_TAG,
                ]);

                $elements = $this->fetchNeosContentForCmsPage(
                    $cmsPage,
                    $salesChannelContext
                );

                /** @var NeosContentResult $result */
                $result = $this->serializer->denormalize($elements, NeosContentResult::class, 'json');

                return $result->getSections();
            },
            self::CMS_SECTIONS_CACHE_TTL
        );
    }

    /**
     * Passing the storefront request forwards its query string and public URI to Neos, so
     * content that depends on them (e.g. prefilled forms) sees what the visitor requested.
     */
    public function fetchCmsSectionsFromNeosByPath(
        string $pathInfo,
        SalesChannelContext $salesChannelContext,
        ?Request $request = null
    ): NeosContentResult|NeosRedirectResult|NeosAssetResult {
        $response = $this->neosClient->request('GET', $this->buildContentByPathUri($pathInfo, $request), [
            'headers' => $this->buildSwHeaders($salesChannelContext, $request),
            'max_redirects' => 0,
        ]);

        return $this->handleContentByPathResponse($response);
    }

    public function submitFormToNeosByPath(string $pathInfo, Request $request, SalesChannelContext $salesChannelContext): NeosContentResult|NeosRedirectResult|NeosAssetResult
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (str_starts_with($contentType, 'application/json')) {
            $headers = array_merge($this->buildSwHeaders($salesChannelContext, $request), ['Content-Type' => $contentType]);
            $body = $request->getContent();
        } else {
            // Recreate formdata since in php it is already consumed by the request
            $formData = new FormDataPart(array_replace_recursive(
                $request->request->all(),
                $this->mapUploadedFiles($request->files->all())
            ));

            $headers = array_merge(
                $this->buildSwHeaders($salesChannelContext, $request),
                $formData->getPreparedHeaders()->toArray()
            );
            $body = $formData->bodyToIterable();
        }

        $response = $this->neosClient->request('POST', $this->buildContentByPathUri($pathInfo, $request), [
            'headers' => $headers,
            'body' => $body,
            'max_redirects' => 0,
        ]);

        return $this->handleContentByPathResponse($response);
    }

    private function mapUploadedFiles(array $files): array
    {
        $mapped = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFile) {
                $mapped[$key] = DataPart::fromPath($value->getPathname(), $value->getClientOriginalName());
            } elseif (is_array($value)) {
                $mapped[$key] = $this->mapUploadedFiles($value);
            }
        }

        return $mapped;
    }

    private function handleContentByPathResponse(ResponseInterface $response): NeosContentResult|NeosRedirectResult|NeosAssetResult
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 300 && $statusCode < 400) {
            return new NeosRedirectResult(
                redirectPathInfo: $this->extractRedirectPathInfo($response),
                statusCode: $statusCode,
            );
        }

        // Throws for a 4xx/5xx status, same as before this method gained an asset branch.
        $content = $response->getContent();

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        if (!str_starts_with($contentType, 'application/json')) {
            // Not the CMS-page JSON envelope - e.g. a Neos asset served directly at this path.
            // NeosContentResultDenormalizer treats invalid JSON as an empty page instead of
            // throwing, so that can't be relied on to tell the two apart.
            return new NeosAssetResult(content: $content, statusCode: $statusCode, contentType: $contentType);
        }

        $result = $this->serializer->denormalize($content, NeosContentResult::class, 'json');

        if (!$this->isCacheable($response)) {
            return new NeosContentResult(sections: $result->getSections(), head: $result->getHead(), cacheable: false);
        }

        return $result;
    }

    private function isCacheable(ResponseInterface $response): bool
    {
        $cacheControl = implode(',', $response->getHeaders(false)['cache-control'] ?? []);

        return preg_match('/(^|,)\s*no-store\s*(,|$)/i', $cacheControl) !== 1;
    }

    private function buildContentByPathUri(string $pathInfo, ?Request $request): string
    {
        $uri = self::CONTENT_BY_PATH_URI_PREFIX . trim($pathInfo, '/');
        $queryString = $request?->getQueryString();

        return $queryString !== null && $queryString !== '' ? $uri . '?' . $queryString : $uri;
    }

    private function buildSwHeaders(SalesChannelContext $salesChannelContext, ?Request $request = null): array
    {
        $domain = $this->getCurrentDomain($salesChannelContext);

        $headers = [
            'x-sw-language-id' => $salesChannelContext->getLanguageId(),
            'x-sw-sales-channel-id' => $salesChannelContext->getSalesChannelId(),
            'x-sw-sales-channel-domain' => $domain->getUrl(),
            'x-sw-context-token' => $salesChannelContext->getSalesChannel()->getAccessKey(),
        ];

        if ($request !== null) {
            $headers['x-sw-request-uri'] = $this->getPublicRequestUri($request);
        }

        return $headers;
    }

    /**
     * The request reaching our controllers has already been rewritten by Shopware's SEO URL
     * resolution (and our own content-by-path routing), so getUri() would return the internal path.
     */
    private function getPublicRequestUri(Request $request): string
    {
        $requestUri = $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI);
        if (!is_string($requestUri) || $requestUri === '') {
            $requestUri = $request->getRequestUri();
        }

        return $request->getSchemeAndHttpHost() . $requestUri;
    }

    public function getCurrentDomain(SalesChannelContext $salesChannelContext): SalesChannelDomainEntity
    {
        $domain = $salesChannelContext->getSalesChannel()->getDomains()?->filter(function ($domain) use ($salesChannelContext) {
            return $domain->getId() === $salesChannelContext->getDomainId();
        })->first();

        if (!$domain instanceof SalesChannelDomainEntity) {
            throw new NeosContentFetchException(sprintf(
                'No domain with id "%s" found for sales channel "%s".',
                $salesChannelContext->getDomainId() ?? '',
                $salesChannelContext->getSalesChannelId()
            ));
        }

        return $domain;
    }

    public function findDomainForHreflangCode(
        string $hreflangCode,
        SalesChannelContext $salesChannelContext
    ): ?SalesChannelDomainEntity {
        $domains = $salesChannelContext->getSalesChannel()->getDomains();
        if ($domains === null) {
            return null;
        }

        if (strcasecmp($hreflangCode, 'x-default') === 0) {
            $defaultDomainId = $salesChannelContext->getSalesChannel()->getHreflangDefaultDomainId();
            if ($defaultDomainId === null) {
                return null;
            }

            foreach ($domains as $domain) {
                if ($domain->getId() === $defaultDomainId) {
                    return $domain;
                }
            }

            return null;
        }

        foreach ($domains as $domain) {
            $path = trim((string) parse_url($domain->getUrl(), PHP_URL_PATH), '/');
            if ($path === '') {
                continue;
            }

            $segments = explode('/', $path);
            $lastSegment = $segments[array_key_last($segments)];
            if (strcasecmp($lastSegment, $hreflangCode) === 0) {
                return $domain;
            }
        }

        return null;
    }

    private function extractRedirectPathInfo(ResponseInterface $response): string
    {
        $location = $response->getHeaders(false)['location'][0] ?? null;
        if ($location === null) {
            throw new NeosContentFetchException('Neos responded with a redirect but did not provide a Location header.');
        }

        $locationHost = parse_url($location, PHP_URL_HOST);
        if ($locationHost !== null && $locationHost !== parse_url($this->configService->getBaseUrl(), PHP_URL_HOST)) {
            // Genuinely external - Neos itself didn't rewrite this into the content-by-path
            // scheme (see ShopwareApiRedirectMiddleware's own host check), so it isn't a path on
            // this site at all. Hand it back exactly as given rather than stripping it down to a
            // bare path and re-rooting it under Shopware's own domain, which would turn a real
            // external target (a different site or service entirely) into a broken, made-up URL.
            return $location;
        }

        $path = (string) parse_url($location, PHP_URL_PATH);
        $prefixPosition = strpos($path, self::CONTENT_BY_PATH_URI_PREFIX);
        if ($prefixPosition !== false) {
            $path = substr($path, $prefixPosition + strlen(self::CONTENT_BY_PATH_URI_PREFIX));
        }

        //Not every redirect Neos issues preserves the content-by-path prefix (e.g. core
        //Neos.Neos controller code that isn't aware of ApiVariantNodeUriService) - a plain
        //frontend path is just as valid a redirect target, so accept it as-is.
        $path = ltrim($path, '/');
        if ($path === '') {
            // parse_url() returns null/false for a malformed or path-less Location (e.g. just
            // a host) - that's not a valid content-by-path target, so surface it as an error
            // instead of silently redirecting to the site root.
            throw new NeosContentFetchException(sprintf('Neos redirected to a location with no usable path: "%s".', $location));
        }

        return '/' . $path;
    }

    /**
     * @throws NeosContentFetchException
     */
    private function fetchNeosContentForCmsPage(
        CmsPageEntity $cmsPage,
        SalesChannelContext $salesChannelContext
    ): string {
        $languageId = $salesChannelContext->getLanguageId();

        try {
            return $this->requestNeosContentForCmsPage($cmsPage, $salesChannelContext, $languageId);
        } catch (ClientException $e) {
            if ($e->getCode() !== 404 || $languageId === Defaults::LANGUAGE_SYSTEM) {
                throw NeosContentFetchException::forCmsPage($cmsPage, $salesChannelContext, $languageId, $e, 1786604083);
            }
        } catch (ExceptionInterface $e) {
            throw NeosContentFetchException::forCmsPage($cmsPage, $salesChannelContext, $languageId, $e, 1786604084);
        }

        // No layout exists for the resolved language yet; fall back to the shop's default language.
        try {
            return $this->requestNeosContentForCmsPage($cmsPage, $salesChannelContext, Defaults::LANGUAGE_SYSTEM);
        } catch (ExceptionInterface $e) {
            throw NeosContentFetchException::forCmsPage($cmsPage, $salesChannelContext, Defaults::LANGUAGE_SYSTEM, $e, 1786604085);
        }
    }

    private function requestNeosContentForCmsPage(
        CmsPageEntity $cmsPage,
        SalesChannelContext $salesChannelContext,
        string $languageId
    ): string {
        $domain = $this->getCurrentDomain($salesChannelContext);

        $uri = sprintf('/neos/shopware-api/content/%s/', $cmsPage->getId());
        $response = $this->neosClient->request('GET', $uri, [
            'headers' => [
                'x-sw-language-id' => $languageId,
                'x-sw-sales-channel-id' => $salesChannelContext->getSalesChannelId(),
                'x-sw-sales-channel-domain' => $domain->getUrl(),
                'x-sw-context-token' => $salesChannelContext->getSalesChannel()->getAccessKey(),
            ],
        ]);

        return $response->getContent();
    }

    /**
     * Loads the slot data into the given blocks for the given resolver context.
     */
    public function loadSlotData(CmsBlockCollection $blocks, ResolverContext $resolverContext): void
    {
        $slots = $this->cmsSlotsDataResolver->resolve($blocks->getSlots(), $resolverContext);

        $blocks->setSlots($slots);
    }
}
