<?php

declare(strict_types=1);

namespace nlxNeosContent\Cache\Warmup;

use nlxNeosContent\Cache\Warmup\DTO\AdditionalDataInterface;
use nlxNeosContent\Cache\Warmup\DTO\AdditionalDataPageDTO;
use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use nlxNeosContent\Message\ScheduleCacheWarmupMessage;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\NeosLayoutPageService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
        private EntityRepository $cmsPageRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function warmUp(SalesChannelContext $salesChannelContext, ?array $additionalData = null): void
    {
        if ($salesChannelContext->getDomainId() === null) {
            return;
        }

        if (isset($additionalData['pageId']) && is_string($additionalData['pageId'])) {
            $this->warmUpSinglePage($salesChannelContext, $additionalData['pageId']);

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
                $this->messageBus->dispatch(new ScheduleCacheWarmupMessage(
                    self::class,
                    $salesChannelContext->getSalesChannelId(),
                    $salesChannelContext->getLanguageId(),
                    $domainId,
                    ['pageId' => $neosNode->getCmsPageId()]
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

    private function warmUpSinglePage(SalesChannelContext $salesChannelContext, string $pageId): void
    {
        $cmsPage = $this->cmsPageRepository->search(
            new Criteria([$pageId]),
            Context::createCLIContext()
        )->getEntities()->first();

        if (!$cmsPage instanceof CmsPageEntity) {
            $this->logger->warning(sprintf(
                'Could not warm up CMS page content cache: CMS page %s no longer exists.',
                $additionalData->pageId
            ));

            return;
        }

        $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $cmsPage,
            $salesChannelContext
        );
    }
}
