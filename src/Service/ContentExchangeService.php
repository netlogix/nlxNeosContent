<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use GuzzleHttp\Exception\RequestException;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionCollection;
use nlxNeosContent\Error\RequestError\NeosContentFetchException;
use nlxNeosContent\Neos\DTO\NeosResults\NeosContentResult;
use nlxNeosContent\Neos\DTO\NeosResults\NeosRedirectResult;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ContentExchangeService
{
    private const CONTENT_BY_PATH_URI_PREFIX = '/neos/shopware-api/content-by-path/';

    public function __construct(
        #[Autowire(service: 'serializer')]
        private readonly DenormalizerInterface $serializer,
        private readonly CmsSlotsDataResolver $cmsSlotsDataResolver,
        private readonly HttpClientInterface $neosClient,
    ) {
    }

    /**
     * @throws NeosContentFetchException
     */
    public function getAlternativeCmsSectionsFromNeos(
        CmsPageEntity $cmsPage,
        SalesChannelContext $salesChannelContext
    ): CmsSectionCollection {
        $elements = $this->fetchNeosContentForCmsPage(
            $cmsPage,
            $salesChannelContext
        );

        return $this->serializer->denormalize($elements, NeosCmsSectionCollection::class,'json');
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

        $sections = $this->serializer->denormalize($response->getContent(), NeosCmsSectionCollection::class, 'json');

        return new NeosContentResult(sections: $sections);
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

    private function getCurrentDomain(SalesChannelContext $salesChannelContext): SalesChannelDomainEntity
    {
        return $salesChannelContext->getSalesChannel()->getDomains()->filter(function ($domain) use ($salesChannelContext) {
            return $domain->getId() === $salesChannelContext->getDomainId();
        })->first();
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
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $domain = $salesChannelContext->getSalesChannel()->getDomains()->filter(function ($domain) use ($salesChannelContext) {
            return $domain->getId() === $salesChannelContext->getDomainId();
        })->first();

        try {
            $uri = sprintf("/neos/shopware-api/content/%s/", $cmsPage->getId());
            $response = $this->neosClient->request('GET', $uri, [
                'headers' => [
                    'x-sw-language-id' => $languageId,
                    'x-sw-sales-channel-id' => $salesChannelId,
                    'x-sw-sales-channel-domain' => $domain->getUrl(),
                    'x-sw-context-token' => $salesChannelContext->getSalesChannel()->getAccessKey(),
                ]
            ]);
        } catch (RequestException $e) {
            throw new NeosContentFetchException (
                sprintf(
                    'Failed to fetch content from Neos for node CmsPage "%s" for sales channel "%s" and language "%s" with Errormessage: %s',
                    $cmsPage->getName(),
                    $salesChannelId,
                    $languageId,
                    $e->getMessage()
                ),
                1752652016,
                $e
            );
        }

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
