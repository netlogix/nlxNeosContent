<?php

declare(strict_types=1);

namespace nlxNeosContent\Service\Loader;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Cache\CacheInterface;


#[AsDecorator(NeosDimensionLoader::class)]
final class CachedNeosDimensionLoader extends AbstractNeosDimensionLoader
{
    private const CACHE_KEY = 'netlogix_neos_content_dimension_mapping';
    private const CACHE_TTL = 86400;

    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractNeosDimensionLoader $decorated,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function load(): array
    {
        try {
            return
                $this->cache->get(
                    self::CACHE_KEY,
                    function () {
                        return $this->decorated->load();
                    },
                    self::CACHE_TTL
                );
        } catch(\Throwable $e) {
            $this->logger->error(sprintf('Could not load dimension Mapping from Neos with error message: %s', $e));
            return [];
        }
    }
}
