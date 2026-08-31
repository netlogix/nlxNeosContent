<?php

declare(strict_types=1);

namespace nlxNeosContent\Message;

use Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface;

final class WarmCmsPageContentMessage implements LowPriorityMessageInterface
{
    public function __construct(
        private readonly string $cmsPageId,
        private readonly string $salesChannelId,
        private readonly string $languageId,
        private readonly string $domainId,
    ) {
    }

    public function getCmsPageId(): string
    {
        return $this->cmsPageId;
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
