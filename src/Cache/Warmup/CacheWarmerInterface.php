<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CacheWarmerInterface
{
    public function warmUp(SalesChannelContext $salesChannelContext): void;

    /**
     * Schedules this warmer's work for asynchronous processing by the message queue workers,
     * instead of executing it synchronously in the calling process.
     */
    public function scheduleWarmUp(SalesChannelContext $salesChannelContext): void;
}
