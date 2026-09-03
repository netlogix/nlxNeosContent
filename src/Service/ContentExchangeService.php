<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use nlxNeosContent\Error\RequestError\NeosContentFetchException;
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

    public function fetchCmsSectionsFromNeosByPath(string $pathInfo, SalesChannelContext $salesChannelContext): NeosContentResult|NeosRedirectResult
    {
        $uri = self::CONTENT_BY_PATH_URI_PREFIX . trim($pathInfo, '/');
        $response = $this->neosClient->request('GET', $uri, [
            'headers' => $this->buildSwHeaders($salesChannelContext),
            'max_redirects' => 0,
        ]);

        return $this->handleContentByPathResponse($response);
    }

    public function submitFormToNeosByPath(string $pathInfo, Request $request, SalesChannelContext $salesChannelContext): NeosContentResult|NeosRedirectResult
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (str_starts_with($contentType, 'application/json')) {
            $headers = array_merge($this->buildSwHeaders($salesChannelContext), ['Content-Type' => $contentType]);
            $body = $request->getContent();
        } else {
            // Recreate formdata since in php it is already consumed by the request
            $formData = new FormDataPart(array_replace_recursive(
                $request->request->all(),
                $this->mapUploadedFiles($request->files->all())
            ));

            $headers = array_merge(
                $this->buildSwHeaders($salesChannelContext),
                $formData->getPreparedHeaders()->toArray()
            );
            $body = $formData->bodyToIterable();
        }

        $uri = self::CONTENT_BY_PATH_URI_PREFIX . trim($pathInfo, '/');
        $response = $this->neosClient->request('POST', $uri, [
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

    private function handleContentByPathResponse(ResponseInterface $response): NeosContentResult|NeosRedirectResult
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 300 && $statusCode < 400) {
            return new NeosRedirectResult(redirectPathInfo: $this->extractRedirectPathInfo($response));
        }

        return $this->serializer->denormalize($response->getContent(), NeosContentResult::class, 'json');
    }

    private function buildSwHeaders(SalesChannelContext $salesChannelContext): array
    {
        $domain = $this->getCurrentDomain($salesChannelContext);

        return [
            'x-sw-language-id' => $salesChannelContext->getLanguageId(),
            'x-sw-sales-channel-id' => $salesChannelContext->getSalesChannelId(),
            'x-sw-sales-channel-domain' => $domain->getUrl(),
            'x-sw-context-token' => $salesChannelContext->getSalesChannel()->getAccessKey(),
        ];
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

        $path = (string) parse_url($location, PHP_URL_PATH);
        $prefixPosition = strpos($path, self::CONTENT_BY_PATH_URI_PREFIX);
        if ($prefixPosition === false) {
            throw new NeosContentFetchException(sprintf('Neos redirected to an unexpected location "%s".', $location));
        }

        return '/' . substr($path, $prefixPosition + strlen(self::CONTENT_BY_PATH_URI_PREFIX));
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
