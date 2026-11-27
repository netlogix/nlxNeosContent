<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsDecorator(NeosPageTreeLoader::class)]
readonly class CachedNeosPageTreeLoader extends AbstractNeosPageTreeLoader
{
    public const CACHE_KEY = 'netlogix_neos_content_neos_page_tree';
    private const CACHE_TTL = 86400;

    /**
     * A failed load is cached as an empty tree this long, so an unreachable Neos isn't asked again on every
     * storefront request, while a transient failure doesn't hide all Neos pages for the full TTL.
     */
    private const FAILURE_CACHE_TTL = 10;

    public function __construct(
        #[AutowireDecorated]
        private AbstractNeosPageTreeLoader $decorated,
        #[Autowire(service: 'cache.object')]
        private TagAwareCacheInterface&CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {

    }

    function load(string $salesChannelId, string $languageId): NeosPageCollection
    {
        try {
            return $this->cache->get(
                $this->cacheKey($salesChannelId, $languageId),
                function (ItemInterface $item) use ($salesChannelId, $languageId) {
                    $item->tag(self::CACHE_KEY);

                    try {
                        $item->expiresAfter(self::CACHE_TTL);

                        return $this->decorated->load($salesChannelId, $languageId);
                    } catch (\Throwable $e) {
                        $this->logger->error($e);
                        $item->expiresAfter(self::FAILURE_CACHE_TTL);

                        return new NeosPageCollection();
                    }
                }
            );
        } catch (\Throwable $e) {
            $this->logger->error($e);
            return new NeosPageCollection();
        }
    }

    public function loadMany(array $requests): array
    {
        $results = [];
        $misses = [];

        foreach ($requests as $request) {
            [$salesChannelId, $languageId] = $request;
            $item = $this->cache->getItem($this->cacheKey($salesChannelId, $languageId));
            if ($item->isHit()) {
                $results[] = new NeosPageTreeLoadResult($salesChannelId, $languageId, $item->get());
            } else {
                $misses[] = $request;
            }
        }

        if ($misses === []) {
            return $results;
        }

        try {
            $fetched = $this->decorated->loadMany($misses);
        } catch (\Throwable $e) {
            $this->logger->error($e);
            $fetched = array_map(
                static function (array $request): NeosPageTreeLoadResult {
                    [$salesChannelId, $languageId] = $request;

                    return new NeosPageTreeLoadResult($salesChannelId, $languageId, new NeosPageCollection(), failed: true);
                },
                $misses
            );
        }

        foreach ($fetched as $result) {
            $results[] = $result;

            /** @var ItemInterface $item TagAwareCacheInterface::getItem() is typed via the plain PSR-6 interface */
            $item = $this->cache->getItem($this->cacheKey($result->salesChannelId, $result->languageId));
            $item->set($result->tree);
            $item->tag(self::CACHE_KEY);
            $item->expiresAfter($result->failed ? self::FAILURE_CACHE_TTL : self::CACHE_TTL);
            $this->cache->save($item);
        }

        return $results;
    }

    private function cacheKey(string $salesChannelId, string $languageId): string
    {
        return self::CACHE_KEY . '-' . $salesChannelId . '-' . $languageId;
    }
}
