<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Cache\Warmup;

use nlxNeosContent\Cache\Warmup\PageTreeCacheWarmer;
use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use nlxNeosContent\Service\NeosPageTreeService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PageTreeCacheWarmerTest extends TestCase
{
    public function testWarmUpRunsOncePerSalesChannelLanguageUntilReset(): void
    {
        $pageTreeService = $this->createMock(NeosPageTreeService::class);
        $pageTreeService->expects($this->exactly(2))->method('loadTreeForContext');
        $warmer = new PageTreeCacheWarmer($pageTreeService, $this->createMock(MessageBusInterface::class));
        $context = $this->context();

        $warmer->warmUp($context);
        $warmer->warmUp($context);
        $warmer->reset();
        $warmer->warmUp($context);
    }

    public function testScheduledWarmUpIsNotSkippedWithSyncTransport(): void
    {
        $pageTreeService = $this->createMock(NeosPageTreeService::class);
        $pageTreeService->expects($this->once())->method('loadTreeForContext');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $warmer = new PageTreeCacheWarmer($pageTreeService, $messageBus);
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
