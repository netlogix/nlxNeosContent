<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use nlxNeosContent\Service\CachingInvalidationService;
use nlxNeosContent\Service\ConfigService;
use nlxNeosContent\Service\NeosPageTreeService;
use Psr\Log\LoggerInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[AsTaggedItem('twig.extension')]
class ResourceHelper extends AbstractExtension
{
    private const CACHE_KEY = 'netlogix_neos_content_resources';

    private const CACHE_TTL = 86400;

    public function __construct(
        private readonly HttpClientInterface $neosClient,
        private readonly LoggerInterface $logger,
        private readonly ConfigService $configService,
        #[Autowire(service: 'cache.object')]
        private readonly TagAwareCacheInterface $cache,
        private readonly NeosPageTreeService $neosPageTreeService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getStyleUrls', [$this, 'getStyleUrls']),
            new TwigFunction('getScriptUrls', [$this, 'getScriptUrls']),
            new TwigFunction('neos_page_path', [$this, 'getNeosPagePath']),
        ];
    }

    public function getStyleUrls(): array
    {
        return $this->load()['css'] ?? [];
    }

    public function getScriptUrls(): array
    {
        return $this->load()['js'] ?? [];
    }

    /**
     * @return array{css?: list<string>, js?: list<string>}
     */
    private function load(): array
    {
        if (!$this->configService->isEnabled()) {
            return [];
        }

        try {
            return $this->cache->get(
                self::CACHE_KEY,
                function (ItemInterface $item): array {
                    $item->tag(CachingInvalidationService::CACHE_TAG);

                    return $this->requestResources();
                },
                self::CACHE_TTL
            );
        } catch (\Throwable $e) {
            $this->logger->error('Could not fetch resources from Neos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array{css?: list<string>, js?: list<string>}
     */
    private function requestResources(): array
    {
        $response = $this->neosClient->request('GET', '/neos/shopware-api/resources/');
        $decodedBody = json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        if (!($decodedBody['css'] ?? false)) {
            return [];
        }

        return $decodedBody;
    }

    public function getNeosPagePath(string $nodeIdentifier): string
    {
        $context = $this->resolveContext();

        if (!$context instanceof SalesChannelContext) {
            return '';
        }

        $normalizedIdentifier = str_replace('-', '', $nodeIdentifier);

        $pathInfo = $this->neosPageTreeService->findPathInfoForIdentifierAndContext(
            $normalizedIdentifier,
            $context
        );

        return $pathInfo === '' ? '' : '/' . ltrim($pathInfo, '/');
    }

    private function resolveContext(): ?SalesChannelContext
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        $context = $request?->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        return $context instanceof SalesChannelContext ? $context : null;
    }
}
