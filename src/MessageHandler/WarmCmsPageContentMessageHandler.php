<?php

declare(strict_types=1);

namespace nlxNeosContent\MessageHandler;

use nlxNeosContent\Message\WarmCmsPageContentMessage;
use nlxNeosContent\Service\ContentExchangeService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WarmCmsPageContentMessageHandler
{
    /**
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     */
    public function __construct(
        private readonly EntityRepository $cmsPageRepository,
        #[Autowire(service: SalesChannelContextFactory::class)]
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly ContentExchangeService $contentExchangeService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WarmCmsPageContentMessage $message): void
    {
        $cmsPage = $this->cmsPageRepository->search(
            new Criteria([$message->getCmsPageId()]),
            Context::createCLIContext()
        )->getEntities()->first();

        if (!$cmsPage instanceof CmsPageEntity) {
            $this->logger->warning(sprintf(
                'Could not warm up CMS page content cache: CMS page %s no longer exists.',
                $message->getCmsPageId()
            ));

            return;
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(
            '',
            $message->getSalesChannelId(),
            [
                SalesChannelContextService::LANGUAGE_ID => $message->getLanguageId(),
                SalesChannelContextService::DOMAIN_ID => $message->getDomainId(),
            ]
        );

        $this->contentExchangeService->getAlternativeCmsSectionsFromNeos($cmsPage, $salesChannelContext);
    }
}
