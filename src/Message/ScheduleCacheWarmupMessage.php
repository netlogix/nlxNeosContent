<?php

declare(strict_types=1);

namespace nlxNeosContent\Message;

use nlxNeosContent\Cache\Warmup\CacheWarmerInterface;
use nlxNeosContent\Cache\Warmup\DTO\AdditionalDataInterface;
use Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface;

final readonly class ScheduleCacheWarmupMessage implements LowPriorityMessageInterface
{
    /**
     * @param class-string<CacheWarmerInterface> $cacheWarmerClass
     */
    public function __construct(
        public string $cacheWarmerClass,
        public string $salesChannelId,
        public string $languageId,
        public string $domainId,
        public ?AdditionalDataInterface $additionalData = null,
    ) {
    }
}
