<?php

declare(strict_types=1);

namespace nlxNeosContent\Subscriber;

use nlxNeosContent\Service\ContentExchangeService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

#[AsEventListener]
class CmsPageLoadedSubscriber
{
    public function __construct(
        private readonly ContentExchangeService $contentExchangeService,
        private readonly SalesChannelProductDefinition $productDefinition,
        #[Autowire(service: 'sales_channel.product.repository', lazy: true)]
        private readonly SalesChannelRepository $productRepository,
        private readonly CategoryDefinition $categoryDefinition,
        #[Autowire(service: 'sales_channel.category.repository', lazy: true)]
        private readonly SalesChannelRepository $categoryRepository,
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
        }
    }

    private function exchangeProductListingPage(
        string $categoryId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): void {
        $this->contentExchangeService->exchangeCmsSectionContent(
            $cmsSectionCollection,
            $nodeIdentifier,
            $this->getCategoryResolverContext($categoryId, $salesChannelContext, $request),
            'en'
        );
    }

    private function exchangeProductDetailPage(
        string $productId,
        SalesChannelContext $salesChannelContext,
        Request $request,
        CmsSectionCollection $cmsSectionCollection,
        string $nodeIdentifier
    ): void {
        // Create a resolver context for the product to resolve the product data
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('media');
        $criteria->addAssociation('manufacturer.media');
        $product = $this->productRepository->search($criteria, $salesChannelContext)->first();

        $resolverContext = new EntityResolverContext(
            $salesChannelContext,
            $request,
            $this->productDefinition,
            clone $product
        );

        $this->contentExchangeService->exchangeCmsSectionContent(
            $cmsSectionCollection,
            $nodeIdentifier,
            $resolverContext,
            'en'
        );
    }

    private function getCategoryResolverContext(
        string $categoryId,
        SalesChannelContext $context,
        Request $request
    ): ResolverContext {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');
        $criteria->addAssociation('media');
        $category = $this->categoryRepository
            ->search($criteria, $context)
            ->get($categoryId);

        if (!$category instanceof CategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        return new EntityResolverContext($context, $request, $this->categoryDefinition, $category);
    }

    private function neosNodeHasNodeIdentifier(ArrayEntity $neosNode): bool
    {
        $vars = $neosNode->getVars();
        $nodeIdentifier = $vars['nodeIdentifier'];

        if (!$nodeIdentifier) {
            return false;
        }

        return true;
    }
}
