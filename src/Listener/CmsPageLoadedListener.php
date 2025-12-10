<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener;

use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use nlxNeosContent\Error\MissingCmsPageEntityException;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
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
class CmsPageLoadedListener
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

        $newCmsSections = match ($cmsPageEntity->getType()) {
            'product_list' => $this->getNewListingPageSections(
                $cmsPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getNavigationCategoryId(),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $neosNode->getVars()['nodeIdentifier'],
            ),
            'product_detail' => $this->getNewDetailPageBlocks(
                $cmsPageLoadedEvent->getRequest()->attributes->get('productId'),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $neosNode->getVars()['nodeIdentifier'],
            ),
            'landingpage' => $this->getNewLandingPageBlocks(
                $neosNode->getVars()['nodeIdentifier'],
                $cmsPageLoadedEvent->getSalesChannelContext()
            ),
            'page' => $this->getNewShopPageBlocks(
                $neosNode->getVars()['nodeIdentifier'],
                $cmsPageLoadedEvent->getSalesChannelContext()
            )
        };

        $cmsPageEntity->setSections($newCmsSections);
    }

    private function getNewListingPageSections(
        string $categoryId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        string $nodeIdentifier,
    ): CmsSectionCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            CategoryDefinition::ENTITY_NAME,
            $categoryId,
            $salesChannelContext,
            $request
        );

        $alternativeCmsSectionsFromNeos = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $salesChannelContext->getLanguageId(),
            $salesChannelContext->getSalesChannelId()
        );

        $this->contentExchangeService->loadSlotData($alternativeCmsSectionsFromNeos->getBlocks(), $resolverContext);
        return $alternativeCmsSectionsFromNeos;
    }

    private function getNewDetailPageBlocks(
        string $productId,
        SalesChannelContext $context,
        Request $request,
        string $nodeIdentifier,
    ): CmsSectionCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            ProductDefinition::ENTITY_NAME,
            $productId,
            $context,
            $request
        );

        $alternativeCmsBlocksFromNeos = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $context->getLanguageId(),
            $context->getSalesChannelId()
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
        SalesChannelContext $context
    ): CmsSectionCollection {
        return $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $context->getLanguageId(),
            $context->getSalesChannelId()
        );
    }

    private function getNewShopPageBlocks(
        string $nodeIdentifier,
        SalesChannelContext $context
    ): CmsSectionCollection {
        return $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $nodeIdentifier,
            $context->getLanguageId(),
            $context->getSalesChannelId()
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
