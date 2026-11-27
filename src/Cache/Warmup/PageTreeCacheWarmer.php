<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use nlxNeosContent\Service\NeosPageTreeService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

#[AutoconfigureTag('nlx_neos_content.cache_warmer')]
class PageTreeCacheWarmer implements CacheWarmerInterface, ResetInterface
{
    /**
     * @var array<string, true>
     */
    private array $warmedSalesChannelLanguages = [];

    /**
     * Kept apart from the warmed ones: with a sync transport the handler runs
     * warmUp() right inside scheduleWarmUp() and must not be skipped.
     *
     * @var array<string, true>
     */
    private array $scheduledSalesChannelLanguages = [];

    public function __construct(
        private readonly NeosPageTreeService $neosPageTreeService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext, ?array $additionalData = null): void
    {
        // The underlying cache doesn't vary by domain, only by sales channel and language,
        // so there's no need to redo this work for every domain sharing the same language.
        if (!self::mark($this->warmedSalesChannelLanguages, $salesChannelContext)) {
            return;
        }

        $this->neosPageTreeService->loadTreeForContext($salesChannelContext);
    }

    public function scheduleWarmUp(SalesChannelContext $salesChannelContext): void
    {
        if (!self::mark($this->scheduledSalesChannelLanguages, $salesChannelContext)) {
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

    /**
     * Called by the messenger worker after every message, so long-running
     * workers don't skip warmups of later runs.
     */
    public function reset(): void
    {
        $this->warmedSalesChannelLanguages = [];
        $this->scheduledSalesChannelLanguages = [];
    }

    /**
     * @param array<string, true> $marked
     */
    private static function mark(array &$marked, SalesChannelContext $salesChannelContext): bool
    {
        $key = $salesChannelContext->getSalesChannelId() . '-' . $salesChannelContext->getLanguageId();
        if (isset($marked[$key])) {
            return false;
        }

        $marked[$key] = true;

        return true;
    }
}
