<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Twig\ResourceHelper;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('nlx_neos_content.cache_warmer')]
readonly class ResourceCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private ResourceHelper $resourceHelper
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext): void
    {
        // getStyleUrls() and getScriptUrls() share a single cache entry, so
        // triggering either one is enough to warm both.
        $this->resourceHelper->getStyleUrls();
    }
}
