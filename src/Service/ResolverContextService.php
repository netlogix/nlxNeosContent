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
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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
        private readonly SalesChannelProductDefinition $productDefinition,
    ) {
    }

    public function getResolverContextForEntityNameAndId(
        string $entityName,
        $entityId,
        $context,
        $request,
        $useEntityIdAsNavigationId = false
    ): ResolverContext {
        return match ($entityName) {
            ProductDefinition::ENTITY_NAME => $this->getProductResolverContextFromProductId(
                $entityId,
                $context,
                $request
            ),
            CategoryDefinition::ENTITY_NAME => $this->getCategoryResolverContextFromCategoryId(
                $entityId,
                $context,
                $request,
                $useEntityIdAsNavigationId
            ),
            LandingPageDefinition::ENTITY_NAME => $this->getLandingPageResolverContextFromEntityId(
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

    public function getResolverContextForEntity(
        Entity $entity,
        $context,
        $request,
        $useEntityIdAsNavigationId = false
    ): ResolverContext {
        if ($entity instanceof SalesChannelProductEntity) {
            return $this->getProductResolverContextFromEntity(
                $entity,
                $context,
                $request
            );
        }

        if ($entity instanceof CategoryEntity) {
            if ($useEntityIdAsNavigationId) {
                $request->attributes->set('navigationId', $entity->getId());
            }
            return $this->getCategoryResolverContextFromEntity(
                $context,
                $request,
                $entity
            );
        }

        return $this->getLandingPageResolverContextFromEntityId(
            $entity->getId(),
            $context,
            $request,
            false
        );
    }

    private function getProductResolverContextFromProductId($productId, $context, $request): ResolverContext
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('media.media');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addFilter(new EqualsFilter('active', true));
        $product = $this->productRepository->search($criteria, $context)->first();

        if (!$product instanceof ProductEntity) {
            throw new \RuntimeException(
                sprintf('No product found for id "%s"', $productId)
            );
        }

        return $this->getProductResolverContextFromEntity(
            $product,
            $context,
            $request,
        );
    }

    private function getProductResolverContextFromEntity(ProductEntity $entity, $context, $request): ResolverContext
    {
        return new EntityResolverContext(
            $context,
            $request,
            $this->productDefinition,
            clone $entity
        );
    }

    private function getCategoryResolverContextFromCategoryId(
        string $categoryId,
        SalesChannelContext $context,
        Request $request,
        $useEntityIdAsNavigationId
    ): ResolverContext {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');
        $criteria->addAssociation('media.media');
        $category = $this->categoryRepository
            ->search($criteria, $context)
            ->get($categoryId);

        if (!$category instanceof CategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        if ($useEntityIdAsNavigationId) {
            $request->attributes->set('navigationId', $categoryId);
        }

        return $this->getCategoryResolverContextFromEntity(
            $context,
            $request,
            $category
        );
    }

    private function getCategoryResolverContextFromEntity(
        SalesChannelContext $context,
        Request $request,
        CategoryEntity $category,
    ): ResolverContext {
        return new EntityResolverContext(
            $context,
            $request,
            $this->definitionRegistry->getByEntityName(CategoryDefinition::ENTITY_NAME),
            clone $category
        );
    }

    private function getLandingPageResolverContextFromEntityId(
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

