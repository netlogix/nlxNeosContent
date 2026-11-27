<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use nlxNeosContent\Twig\ResourceHelper;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

#[AutoconfigureTag('nlx_neos_content.cache_warmer')]
class ResourceCacheWarmer implements CacheWarmerInterface, ResetInterface
{
    private bool $warmed = false;

    /**
     * Kept apart from $warmed: with a sync transport the handler runs
     * warmUp() right inside scheduleWarmUp() and must not be skipped.
     */
    private bool $scheduled = false;

    public function __construct(
        private readonly ResourceHelper $resourceHelper,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext, ?array $additionalData = null): void
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
        if ($this->scheduled) {
            return;
        }

        $domainId = $salesChannelContext->getDomainId();
        if ($domainId === null) {
            return;
        }

        $this->scheduled = true;

        $this->messageBus->dispatch(new ScheduleCacheWarmupMessage(
            self::class,
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            $domainId,
        ));
    }

    /**
     * Called by the messenger worker after every message, so long-running
     * workers don't skip warmups of later runs.
     */
    public function reset(): void
    {
        $this->warmed = false;
        $this->scheduled = false;
    }
}
