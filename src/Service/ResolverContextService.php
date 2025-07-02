<?php

namespace nlxNeosContent\Service;


use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageException;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class ResolverContextService
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        #[Autowire(service: 'sales_channel.product.repository', lazy: true)]
        private readonly SalesChannelRepository $productRepository,
        #[Autowire(service: 'sales_channel.category.repository', lazy: true)]
        private readonly SalesChannelRepository $categoryRepository,
        #[Autowire(service: 'sales_channel.landing_page.repository', lazy: true)]
        private readonly SalesChannelRepository $landingPageRepository,
    ) {
    }

    public function getResolverContextForEntityName(
        string $entityName,
        $entityId,
        $context,
        $request,
        $useEntityIdAsNavigationId = false
    ): ResolverContext {
        return match ($entityName) {
            ProductDefinition::ENTITY_NAME => $this->getProductResolverContext($entityId, $context, $request),
            CategoryDefinition::ENTITY_NAME => $this->getCategoryResolverContext(
                $entityId,
                $context,
                $request,
                $useEntityIdAsNavigationId
            ),
            LandingPageDefinition::ENTITY_NAME => $this->getLandingPageResolverContext(
                $entityId,
                $context,
                $request,
                $useEntityIdAsNavigationId
            ),
            default => throw new \InvalidArgumentException(
                sprintf('No resolver context found for entity name "%s"', $entityName)
            ),
        };
    }

    private function getProductResolverContext($productId, $context, $request): ResolverContext
    {
        $productDefinition = $this->definitionRegistry->getByEntityName(ProductDefinition::ENTITY_NAME);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('media');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addFilter(new EqualsFilter('active', true));
        $product = $this->productRepository->search($criteria, $context)->first();

        if (!$product instanceof ProductEntity) {
            throw new \RuntimeException(
                sprintf('No product found for id "%s"', $productId)
            );
        }

        return new EntityResolverContext(
            $context,
            $request,
            $productDefinition,
            clone $product
        );
    }

    private function getCategoryResolverContext(
        string $categoryId,
        SalesChannelContext $context,
        Request $request,
        $useEntityIdAsNavigationId
    ): ResolverContext {
        $categoryDefinition = $this->definitionRegistry->getByEntityName(CategoryDefinition::ENTITY_NAME);

        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');
        $criteria->addAssociation('media');
        $category = $this->categoryRepository
            ->search($criteria, $context)
            ->get($categoryId);

        if (!$category instanceof CategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        if ($useEntityIdAsNavigationId) {
            $request->attributes->set('navigationId', $categoryId);
        }

        return new EntityResolverContext(
            $context,
            $request,
            $categoryDefinition,
            clone $category
        );
    }

    private function getLandingPageResolverContext(
        string $landingPageId,
        SalesChannelContext $context,
        Request $request,
        $useEntityIdAsNavigationId
    ): ResolverContext {
        $landingPageDefinition = $this->definitionRegistry->getByEntityName(LandingPageDefinition::ENTITY_NAME);

        $criteria = new Criteria([$landingPageId]);
        $criteria->setTitle('landing-page::data');

        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()));

        $landingPage = $this->landingPageRepository
            ->search($criteria, $context)
            ->get($landingPageId);

        if (!$landingPage instanceof LandingPageEntity) {
            throw LandingPageException::notFound($landingPageId);
        }

        if ($useEntityIdAsNavigationId) {
            $request->attributes->set('navigationId', $landingPageId);
        }

        return new EntityResolverContext(
            $context,
            $request,
            $landingPageDefinition,
            $landingPage
        );
    }
}

