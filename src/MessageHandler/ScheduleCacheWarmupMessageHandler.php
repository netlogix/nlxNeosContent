<?php

declare(strict_types=1);

namespace nlxNeosContent\MessageHandler;

use nlxNeosContent\Cache\Warmup\CacheWarmerInterface;
use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScheduleCacheWarmupMessageHandler
{
    /**
     * @param iterable<CacheWarmerInterface> $cacheWarmers
     */
    public function __construct(
        #[AutowireIterator('nlx_neos_content.cache_warmer')]
        private readonly iterable $cacheWarmers,
        #[Autowire(service: SalesChannelContextFactory::class)]
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScheduleCacheWarmupMessage $message): void
    {
        foreach ($this->cacheWarmers as $cacheWarmer) {
            if ($cacheWarmer::class !== $message->cacheWarmerClass) {
                continue;
            }

            $salesChannelContext = $this->salesChannelContextFactory->create(
                '',
                $message->salesChannelId,
                [
                    SalesChannelContextService::LANGUAGE_ID => $message->languageId,
                    SalesChannelContextService::DOMAIN_ID => $message->domainId,
                ]
            );

            $cacheWarmer->warmUp($salesChannelContext, $message->additionalData);

            return;
        }

        $this->logger->warning(sprintf(
            'Could not schedule cache warmup: no cache warmer of class %s is registered.',
            $message->cacheWarmerClass
        ));
    }
}
