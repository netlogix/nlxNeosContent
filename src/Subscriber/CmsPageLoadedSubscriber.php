<?php

declare(strict_types=1);

namespace netlogixNeosContent\Subscriber;

use netlogixNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use netlogixNeosContent\Service\ContentExchangeService;
use netlogixNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

#[AsEventListener]
class CmsPageLoadedSubscriber
{
    public function __construct(
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
    ) {
    }

    public function __invoke(CmsPageLoadedEvent $cmsPageLoadedEvent): void
    {
        $array = $cmsPageLoadedEvent->getResult()->getElements();
        $cmsPageEntity = array_pop($array);

        if (!$cmsPageEntity) {
            //FIXME Add exception class for this case and throw it here
            return;
        }

        if (!$cmsPageEntity->hasExtension('nlxNeosNode')) {
            return;
        }
        $neosNode = $cmsPageEntity->getExtension('nlxNeosNode');

        $hasNeosNodeIdentifier = $this->neosNodeHasNodeIdentifier($neosNode);

        if (!$hasNeosNodeIdentifier) {
            //Nothing to do continue with default shopware logic
            return;
        }

        if ($cmsPageEntity->getType() === 'product_list') {
            $this->exchangeProductListingPage(
                $cmsPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getNavigationCategoryId(),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsPageEntity->getSections(),
                $neosNode->getVars()['nodeIdentifier']
            );
        } elseif ($cmsPageEntity->getType() === 'product_detail') {
            $this->exchangeProductDetailPage(
                $cmsPageLoadedEvent->getRequest()->attributes->get('productId'),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsPageEntity->getSections(),
                $neosNode->getVars()['nodeIdentifier']
            );
        } elseif ($cmsPageEntity->getType() === 'landingpage') {
            $this->exchangeLandingPage(
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsPageEntity->getSections(),
                $neosNode->getVars()['nodeIdentifier']
            );
        } else {
            $this->exchangeShopPage(
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsPageEntity->getSections(),
                $neosNode->getVars()['nodeIdentifier']
            );
        }
    }

    private function exchangeProductListingPage(
        string $categoryId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): void {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityName(
            CategoryDefinition::ENTITY_NAME,
            $categoryId,
            $salesChannelContext,
            $request
        );

        $this->contentExchangeService->exchangeCmsSectionContent(
            $cmsSectionCollection,
            $nodeIdentifier,
            $resolverContext,
            $salesChannelContext->getLanguageInfo()->localeCode
        );
    }

    private function exchangeProductDetailPage(
        string $productId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): void {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityName(
            ProductDefinition::ENTITY_NAME,
            $productId,
            $salesChannelContext,
            $request
        );

        $this->contentExchangeService->exchangeCmsSectionContent(
            $cmsSectionCollection,
            $nodeIdentifier,
            $resolverContext,
            $salesChannelContext->getLanguageInfo()->localeCode
        );
    }

    private function exchangeLandingPage(
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): void {
        $resolverContext = new ResolverContext($salesChannelContext, $request);

        $this->contentExchangeService->exchangeCmsSectionContent(
            $cmsSectionCollection,
            $nodeIdentifier,
            $resolverContext,
            $salesChannelContext->getLanguageInfo()->localeCode
        );
    }

    private function exchangeShopPage(
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): void
    {
        $resolverContext = new ResolverContext($salesChannelContext, $request);

        $this->contentExchangeService->exchangeCmsSectionContent(
            $cmsSectionCollection,
            $nodeIdentifier,
            $resolverContext,
            $salesChannelContext->getLanguageInfo()->localeCode
        );
    }

    private function neosNodeHasNodeIdentifier(NeosNodeEntity $neosNode): bool
    {
        $nodeIdentifier = $neosNode->getNodeIdentifier();

        if (!$nodeIdentifier) {
            return false;
        }

        return true;
    }
}
