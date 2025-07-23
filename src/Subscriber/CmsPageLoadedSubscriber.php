<?php

declare(strict_types=1);

namespace netlogixNeosContent\Subscriber;

use netlogixNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use netlogixNeosContent\Error\MissingCmsPageEntityException;
use netlogixNeosContent\Service\ContentExchangeService;
use netlogixNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
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
            //this should never happen, but if it does we throw an exception
            throw new MissingCmsPageEntityException('CmsPageLoadedEvent did not return a CmsPageEntity', 1752652068);
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

        $cmsSectionCollection = $cmsPageEntity->getSections();

        match ($cmsPageEntity->getType()) {
            'product_list' => $newCmsBlocks = $this->getNewListingPageBlocks(
                $cmsPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getNavigationCategoryId(),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsSectionCollection,
                $neosNode->getVars()['nodeIdentifier']
            ),
            'product_detail' => $newCmsBlocks = $this->getNewDetailPageBlocks(
                $cmsPageLoadedEvent->getRequest()->attributes->get('productId'),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsSectionCollection,
                $neosNode->getVars()['nodeIdentifier']
            ),
            'landingpage' => $newCmsBlocks = $this->getNewLandingPageBlocks(
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsSectionCollection,
                $neosNode->getVars()['nodeIdentifier']
            ),
            'page' => $newCmsBlocks = $this->getNewShopPageBlocks(
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsSectionCollection,
                $neosNode->getVars()['nodeIdentifier']
            )
        };

        $this->exchangeCmsBlocks($cmsSectionCollection, $newCmsBlocks);
    }

    private function getNewListingPageBlocks(
        string $categoryId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): CmsBlockCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityName(
            CategoryDefinition::ENTITY_NAME,
            $categoryId,
            $salesChannelContext,
            $request
        );

        $alternativeCmsBlocksFromNeos = $this->contentExchangeService->getAlternativeCmsBlocksFromNeos(
            $cmsSectionCollection->first()->getId(),
            $nodeIdentifier,
            $salesChannelContext->getLanguageInfo()->localeCode
        );

        $this->contentExchangeService->loadSlotData($alternativeCmsBlocksFromNeos, $resolverContext);
        return $alternativeCmsBlocksFromNeos;
    }

    private function getNewDetailPageBlocks(
        string $productId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): CmsBlockCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityName(
            ProductDefinition::ENTITY_NAME,
            $productId,
            $salesChannelContext,
            $request
        );

        $alternativeCmsBlocksFromNeos = $this->contentExchangeService->getAlternativeCmsBlocksFromNeos(
            $cmsSectionCollection->first()->getId(),
            $nodeIdentifier,
            $salesChannelContext->getLanguageInfo()->localeCode
        );

        /** @var CmsBlockEntity $cmsBlock */
        foreach ($alternativeCmsBlocksFromNeos->getIterator() as $cmsBlock) {
            if ($cmsBlock->getType() !== 'cross-selling') {
                continue;
            }

            $fieldConfig = $cmsBlock->getSlots()->first()?->getFieldConfig();

            if (!$fieldConfig) {
                $fieldConfig = new FieldConfigCollection();
            }
            $fieldConfig->add(new FieldConfig(ProductDefinition::ENTITY_NAME, 'static', $productId));
            $cmsBlock->getSlots()->first()->setFieldConfig(
                $fieldConfig
            );
        }

        $this->contentExchangeService->loadSlotData($alternativeCmsBlocksFromNeos, $resolverContext);
        return $alternativeCmsBlocksFromNeos;
    }

    private function getNewLandingPageBlocks(
        SalesChannelContext $salesChannelContext,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): CmsBlockCollection {
        return $this->contentExchangeService->getAlternativeCmsBlocksFromNeos(
            $cmsSectionCollection->first()->getId(),
            $nodeIdentifier,
            $salesChannelContext->getLanguageInfo()->localeCode
        );
    }

    private function getNewShopPageBlocks(
        SalesChannelContext $salesChannelContext,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): CmsBlockCollection {
        return $this->contentExchangeService->getAlternativeCmsBlocksFromNeos(
            $cmsSectionCollection->first()->getId(),
            $nodeIdentifier,
            $salesChannelContext->getLanguageInfo()->localeCode
        );
    }

    private function exchangeCmsBlocks(
        $cmsSectionCollection,
        $newCmsBlocks,
    ): void {
        $cmsSectionCollection->first()->setBlocks($newCmsBlocks);
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
