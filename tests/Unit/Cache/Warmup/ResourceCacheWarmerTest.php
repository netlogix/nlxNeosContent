<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Cache\Warmup;

use nlxNeosContent\Cache\Warmup\ResourceCacheWarmer;
use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use nlxNeosContent\Twig\ResourceHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ResourceCacheWarmerTest extends TestCase
{
    public function testWarmUpRunsOnceUntilReset(): void
    {
        $resourceHelper = $this->createMock(ResourceHelper::class);
        $resourceHelper->expects($this->exactly(2))->method('getStyleUrls')->willReturn([]);
        $warmer = new ResourceCacheWarmer($resourceHelper, $this->createMock(MessageBusInterface::class));
        $context = $this->context();

        $warmer->warmUp($context);
        $warmer->warmUp($context);
        $warmer->reset();
        $warmer->warmUp($context);
    }

    public function testScheduledWarmUpIsNotSkippedWithSyncTransport(): void
    {
        $resourceHelper = $this->createMock(ResourceHelper::class);
        $resourceHelper->expects($this->once())->method('getStyleUrls')->willReturn([]);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $warmer = new ResourceCacheWarmer($resourceHelper, $messageBus);
        $context = $this->context();
        $messageBus->expects($this->once())->method('dispatch')
            ->willReturnCallback(function (ScheduleCacheWarmupMessage $message) use ($warmer, $context): Envelope {
                $warmer->warmUp($context);

                return new Envelope($message);
            });

        $warmer->scheduleWarmUp($context);
        $warmer->scheduleWarmUp($context);
    }

    private function context(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel');
        $context->method('getLanguageId')->willReturn('language');
        $context->method('getDomainId')->willReturn('domain');

        return $context;
    }
}
