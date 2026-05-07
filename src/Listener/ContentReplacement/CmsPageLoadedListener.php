<?php

declare(strict_types=1);

namespace nlxNeosContent\Listener\ContentReplacement;

use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use nlxNeosContent\Error\ContentReplacement\MissingCmsPageEntityException;
use nlxNeosContent\Error\ContentReplacement\MissingEntityIdForResolverContextException;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\ResolverContextService;
use PageTypeEnum;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
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
        /** @var NeosNodeEntity $neosNode */
        $neosNode = $cmsPageEntity->getExtension('nlxNeosNode');

        if (!$neosNode->getNeosConnection()) {
            return;
        }

        $pageType = $this->resolvePageTypeFromRoute($cmsPageLoadedEvent->getRequest()->attributes->get('_route'));
        $entityIdForResolverContext = $this->resolveEntityIdFromEvent($cmsPageLoadedEvent);

        if ($entityIdForResolverContext === null && $pageType !== PageTypeEnum::SHOP_PAGE) {
            //In few cases it is ok to have no id for a entity for the resolverContext
            throw new MissingEntityIdForResolverContextException(
                $cmsPageLoadedEvent->getRequest(),
                1778141070
            );
        }

        $newCmsSections = match ($pageType) {
            PageTypeEnum::PRODUCT_LISTING => $this->getNewListingPageSections(
                $entityIdForResolverContext,
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsPageEntity,
            ),
            PageTypeEnum::PRODUCT_DETAIL => $this->getNewDetailPageBlocks(
                $cmsPageLoadedEvent->getRequest()->attributes->get('productId'),
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest(),
                $cmsPageEntity,
            ),
            PageTypeEnum::SHOP_PAGE => $this->getNewShopPageBlocks(
                $cmsPageEntity,
                $entityIdForResolverContext,
                $cmsPageLoadedEvent->getSalesChannelContext(),
                $cmsPageLoadedEvent->getRequest()
            )
        };

        $cmsPageEntity->setSections($newCmsSections);
    }

    private function resolvePageTypeFromRoute(string $route): PageTypeEnum
    {
        return match ($route) {
            'frontend.home.page', 'frontend.navigation.page' => PageTypeEnum::PRODUCT_LISTING,
            'frontend.detail.page' => PageTypeEnum::PRODUCT_DETAIL,
            default => PageTypeEnum::SHOP_PAGE
        };
    }

    private function resolveEntityIdFromEvent(CmsPageLoadedEvent $cmsPageLoadedEvent): ?string
    {
        return match ($cmsPageLoadedEvent->getRequest()->attributes->get('_route')) {
            'frontend.detail.page' => $cmsPageLoadedEvent->getRequest()->attributes->get('productId'),
            'frontend.navigation.page' => $cmsPageLoadedEvent->getRequest()->attributes->get('navigationId'),
            'frontend.home.page' => $cmsPageLoadedEvent->getSalesChannelContext()
                ->getSalesChannel()->getNavigationCategoryId(),
            default => null
        };
    }

    private function getNewListingPageSections(
        string $categoryId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsPageEntity $cmsPage,
    ): CmsSectionCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            CategoryDefinition::ENTITY_NAME,
            $categoryId,
            $salesChannelContext,
            $request
        );

        $alternativeCmsSectionsFromNeos = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $cmsPage,
            $salesChannelContext,
        );

        $this->contentExchangeService->loadSlotData($alternativeCmsSectionsFromNeos->getBlocks(), $resolverContext);
        return $alternativeCmsSectionsFromNeos;
    }

    private function getNewDetailPageBlocks(
        string $productId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsPageEntity $cmsPage,
    ): CmsSectionCollection {
        $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
            ProductDefinition::ENTITY_NAME,
            $productId,
            $salesChannelContext,
            $request
        );

        $alternativeCmsBlocksFromNeos = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $cmsPage,
            $salesChannelContext,
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

    private function getNewShopPageBlocks(
        CmsPageEntity $cmsPageEntity,
        ?string $entityIdForResolverContext,
        SalesChannelContext $salesChannelContext,
        Request $request
    ): CmsSectionCollection {
        if ($entityIdForResolverContext === null) {
            $resolverContext = new ResolverContext($salesChannelContext, $request);
        } else {
            $resolverContext = $this->resolverContextService->getResolverContextForEntityNameAndId(
                CategoryDefinition::ENTITY_NAME,
                $entityIdForResolverContext,
                $salesChannelContext,
                $request
            );
        }

        $alternativeSections = $this->contentExchangeService->getAlternativeCmsSectionsFromNeos(
            $cmsPageEntity,
            $salesChannelContext,
        );
        $this->contentExchangeService->loadSlotData($alternativeSections->getBlocks(), $resolverContext);
        return $alternativeSections;
    }
}
