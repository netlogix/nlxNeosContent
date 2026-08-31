<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;

#[AutoconfigureTag('nlx_neos_content.cache_warmer')]
class PageTreeCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var array<string, true>
     */
    private array $warmedSalesChannelLanguages = [];

    public function __construct(
        private readonly AbstractNeosPageTreeLoader $neosPageTreeLoader,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext): void
    {
        // The underlying cache doesn't vary by domain, only by sales channel and language,
        // so there's no need to redo this work for every domain sharing the same language.
        if (!$this->markAsWarmed($salesChannelContext)) {
            return;
        }

        $this->neosPageTreeLoader->load($salesChannelContext);
    }

    public function scheduleWarmUp(SalesChannelContext $salesChannelContext): void
    {
        if (!$this->markAsWarmed($salesChannelContext)) {
            return;
        }

        $domainId = $salesChannelContext->getDomainId();
        if ($domainId === null) {
            return;
        }

        $this->messageBus->dispatch(new ScheduleCacheWarmupMessage(
            self::class,
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            $domainId,
        ));
    }

    private function markAsWarmed(SalesChannelContext $salesChannelContext): bool
    {
        $key = $salesChannelContext->getSalesChannelId() . $salesChannelContext->getLanguageId();
        if (isset($this->warmedSalesChannelLanguages[$key])) {
            return false;
        }

        $this->warmedSalesChannelLanguages[$key] = true;

        return true;
    }
}
