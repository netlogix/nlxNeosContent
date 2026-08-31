<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use nlxNeosContent\Twig\ResourceHelper;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;

#[AutoconfigureTag('nlx_neos_content.cache_warmer')]
class ResourceCacheWarmer implements CacheWarmerInterface
{
    private bool $warmed = false;

    public function __construct(
        private readonly ResourceHelper $resourceHelper,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext): void
    {
        // The underlying cache is a single global entry, independent of sales channel,
        // language or domain, so it only ever needs to be warmed once per run.
        if ($this->warmed) {
            return;
        }

        $this->warmed = true;

        // getStyleUrls() and getScriptUrls() share a single cache entry, so
        // triggering either one is enough to warm both.
        $this->resourceHelper->getStyleUrls();
    }

    public function scheduleWarmUp(SalesChannelContext $salesChannelContext): void
    {
        if ($this->warmed) {
            return;
        }

        $domainId = $salesChannelContext->getDomainId();
        if ($domainId === null) {
            return;
        }

        $this->warmed = true;

        $this->messageBus->dispatch(new ScheduleCacheWarmupMessage(
            self::class,
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            $domainId,
        ));
    }
}
