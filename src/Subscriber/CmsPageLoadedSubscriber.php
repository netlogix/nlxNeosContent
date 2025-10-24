<?php

declare(strict_types=1);

namespace nlxNeosContent\Subscriber;

use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use nlxNeosContent\Error\MissingCmsPageEntityException;
use nlxNeosContent\Resolver\NeosDimensionResolver;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

#[AsEventListener]
class CmsPageLoadedSubscriber
{
    public function __construct(
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
        private readonly NeosDimensionResolver $neosDimensionResolver,
    ) {
    }

    public function __invoke(CmsPageLoadedEvent $cmsPageLoadedEvent): void
    {
        $array = $cmsPageLoadedEvent->getResult()->getElements();
        $cmsPageEntity = array_pop($array);

        if (!$cmsPageEntity) {
            //this should never happen but if it does, we throw an exception
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

         $dimensions = $this->neosDimensionResolver->resolveDimensions(
            $cmsPageLoadedEvent->getSalesChannelContext(),
            $cmsPageLoadedEvent->getRequest()
        );

        match ($cmsPageEntity->getType()) {
            'product_list' => $newCmsSections = $this->getNewListingPageSections(
                $cmsPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getNavigationCategoryId(),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $neosNode->getVars()['nodeIdentifier'],
                $dimensions
            ),
            'product_detail' => $newCmsSections = $this->getNewDetailPageBlocks(
                $cmsPageLoadedEvent->getRequest()->attributes->get('productId'),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $neosNode->getVars()['nodeIdentifier'],
                $dimensions
            ),
            'landingpage' => $newCmsSections = $this->getNewLandingPageBlocks(
                $neosNode->getVars()['nodeIdentifier'],
                $dimensions
            ),
            'page' => $newCmsSections = $this->getNewShopPageBlocks(
                $neosNode->getVars()['nodeIdentifier'],
                $dimensions
            )
        };

        $this->replaceCmsSections($cmsPageEntity, $newCmsSections);
    }

    private function getNewListingPageSections(
        string $categoryId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        string $nodeIdentifier,
        Struct $dimensions
    ): CmsSectionCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            CategoryDefinition::ENTITY_NAME,
            $categoryId,
            $salesChannelContext,
            $request
        );

        $alternativeCmsSectionsFromNeos = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $dimensions
        );

        $this->contentExchangeService->loadSlotData($alternativeCmsSectionsFromNeos->getBlocks(), $resolverContext);
        return $alternativeCmsSectionsFromNeos;
    }

    private function getNewDetailPageBlocks(
        string $productId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        string $nodeIdentifier,
        Struct $dimensions
    ): CmsSectionCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            ProductDefinition::ENTITY_NAME,
            $productId,
            $salesChannelContext,
            $request
        );

        $alternativeCmsBlocksFromNeos = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $dimensions
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

        $this->contentExchangeService->loadSlotData($alternativeCmsBlocksFromNeos->getBlocks(), $resolverContext);
        return $alternativeCmsBlocksFromNeos;
    }

    private function getNewLandingPageBlocks(
        string $nodeIdentifier,
        Struct $dimensions
    ): CmsSectionCollection {
        return $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $dimensions
        );
    }

    private function getNewShopPageBlocks(
        string $nodeIdentifier,
        Struct $dimensions
    ): CmsSectionCollection {
        return $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $dimensions
        );
    }

    private function replaceCmsSections(
        CmsPageEntity $cmsPageEntity,
        CmsSectionCollection $newCmsSections,
    ): void {
        $cmsPageEntity->setSections($newCmsSections);
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
