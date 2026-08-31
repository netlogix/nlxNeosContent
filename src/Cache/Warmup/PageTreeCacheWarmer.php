<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('nlx_neos_content.cache_warmer')]
readonly class PageTreeCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private AbstractNeosPageTreeLoader $neosPageTreeLoader
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext): void
    {
        $this->neosPageTreeLoader->load($salesChannelContext);
    }
}
