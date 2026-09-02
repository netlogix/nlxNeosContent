<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use nlxNeosContent\Message\WarmCmsPageContentMessage;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\NeosLayoutPageService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;

#[AutoconfigureTag('nlx_neos_content.cache_warmer')]
readonly class NeosContentCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private NeosLayoutPageService $neosLayoutPageService,
        private ContentExchangeService $contentExchangeService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext): void
    {
        if ($salesChannelContext->getDomainId() === null) {
            return;
        }

        foreach ($this->getNeosConnectedNodes() as $neosNode) {
            try {
                $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
                    $neosNode->getCmsPage(),
                    $salesChannelContext
                );
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'Could not warm up content cache for CMS page %s: %s',
                    $neosNode->getCmsPageId(),
                    $e->getMessage()
                ));
            }
        }
    }

    public function scheduleWarmUp(SalesChannelContext $salesChannelContext): void
    {
        $domainId = $salesChannelContext->getDomainId();
        if ($domainId === null) {
            return;
        }

        foreach ($this->getNeosConnectedNodes() as $neosNode) {
            try {
                $this->messageBus->dispatch(new WarmCmsPageContentMessage(
                    $neosNode->getCmsPageId(),
                    $salesChannelContext->getSalesChannelId(),
                    $salesChannelContext->getLanguageId(),
                    $domainId,
                ));
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'Could not schedule content cache warmup for CMS page %s: %s',
                    $neosNode->getCmsPageId(),
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * @return NeosNodeEntity[]
     */
    private function getNeosConnectedNodes(): iterable
    {
        return $this->neosLayoutPageService->getNeosNodeEntitiesWithConnectedCmsPage(Context::createCLIContext());
    }
}
