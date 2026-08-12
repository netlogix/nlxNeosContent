<?php

declare(strict_types=1);

namespace nlxNeosContent\Twig;

use nlxNeosContent\Service\CachingInvalidationService;
use nlxNeosContent\Service\ConfigService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    ) {
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getStyleUrls', [$this, 'getStyleUrls']),
            new TwigFunction('getScriptUrls', [$this, 'getScriptUrls']),
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
}
