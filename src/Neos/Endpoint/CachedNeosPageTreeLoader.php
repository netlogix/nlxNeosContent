<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\Endpoint;

use nlxNeosContent\Neos\DTO\NeosPageCollection;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Cache\CacheInterface;

#[AsDecorator(NeosPageTreeLoader::class)]
readonly class CachedNeosPageTreeLoader extends AbstractNeosPageTreeLoader
{
    private const CACHE_KEY = 'netlogix_neos_content_neos_page_tree';
    private const CACHE_TTL = 86400;

    public function __construct(
        #[AutowireDecorated]
        private AbstractNeosPageTreeLoader $decorated,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {

    }

    function load(SalesChannelContext $salesChannelContext): NeosPageCollection
    {
        try {
            return $this->cache->get(
                self::CACHE_KEY . $salesChannelContext->getLanguageId(),
                function() use ($salesChannelContext) {
                    return $this->decorated->load($salesChannelContext);
                },
                self::CACHE_TTL
            );
        } catch (\Throwable $e) {
            $this->logger->error($e);
            return new NeosPageCollection();
        }
    }
}
