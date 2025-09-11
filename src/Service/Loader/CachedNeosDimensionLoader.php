<?php

declare(strict_types=1);

namespace netlogixNeosContent\Service\Loader;

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
    ) {
    }

    public function load(): array
    {
        return $this->cache->get(
            self::CACHE_KEY,
            fn () => $this->decorated->load(),
            self::CACHE_TTL
        );
    }
}
