<?php

declare(strict_types=1);

namespace nlxNeosContent\Message;

use nlxNeosContent\Cache\Warmup\CacheWarmerInterface;
use Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface;

final class ScheduleCacheWarmupMessage implements LowPriorityMessageInterface
{
    /**
     * @param class-string<CacheWarmerInterface> $cacheWarmerClass
     */
    public function __construct(
        private readonly string $cacheWarmerClass,
        private readonly string $salesChannelId,
        private readonly string $languageId,
        private readonly string $domainId,
    ) {
    }

    /**
     * @return class-string<CacheWarmerInterface>
     */
    public function getCacheWarmerClass(): string
    {
        return $this->cacheWarmerClass;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getDomainId(): string
    {
        return $this->domainId;
    }
}
