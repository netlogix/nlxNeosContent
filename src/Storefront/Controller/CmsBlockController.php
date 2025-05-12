<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use nlxNeosContent\Service\ContentExchangeService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CmsBlockController extends StorefrontController
{
    function __construct(
        #[Autowire(service: 'sales_channel.product.repository', lazy: true)]
        private readonly SalesChannelRepository $productRepository,
        #[Autowire(service: 'sales_channel.category.repository', lazy: true)]
        private readonly SalesChannelRepository $categoryRepository,
        private readonly ContentExchangeService $contentExchangeService,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    #[Route(path: '/html-content/cms-block', name: 'frontend.html-content.cms-block', methods: ['POST'])]
    function cmsBlock(
        Request $request,
        SalesChannelContext $context
    ): Response {
        $entityName = $request->get('sw-entity-name');
        $entityId = $request->get('sw-entity-id', null);
        $blockData = json_decode($request->getContent(), true);

        $definition = $this->definitionRegistry->getByEntityName($entityName);
        $repository = $this->getRepository($entityName);

        $criteria = $entityId ? new Criteria([$entityId]) : new Criteria();
        if ($entityName === ProductDefinition::ENTITY_NAME) {
            $criteria->addAssociation('media');
            $criteria->addAssociation('manufacturer.media');
        } else if ($entityName === CategoryDefinition::ENTITY_NAME) {
            // TODO
        }

        $entityResponse = $repository->search(
            $criteria,
            $context
        );
        if ($entityResponse->count() === 0) {
            // TODO: what do we do if no products exist?
        }

        /**
         * FIXME: If the found entity is a Main-Product and has Variants, we might need to load the first variant instead
         * This depends a little bit on how the Variants display is configured in SW6.
         * There are different options how Variants are treated in Storefront and apparently this controller can not handle it yet.
         * */

        $entity = $entityResponse->first();

        /** @var CmsBlockEntity $block */
        $block = $this->contentExchangeService->createCmsBlockFromNeosElement(
            $blockData,
            1,
            'foo-bar'
        );

        $resolverContext = new EntityResolverContext(
            $context, $request,
            $definition,
            $entity
        );

        $collection = new CmsBlockCollection([$block]);
        $this->contentExchangeService->loadSlotData($collection, $resolverContext);

        return $this->render(
            sprintf('@Storefront/storefront/block/cms-block-%s.html.twig', $block->getType()),
            [
                'block' => $block,
                'context' => $context,
            ]
        );
    }

    private function getRepository(string $entityName): SalesChannelRepository
    {
        return match ($entityName) {
            ProductDefinition::ENTITY_NAME => $this->productRepository,
            CategoryDefinition::ENTITY_NAME => $this->categoryRepository,
            default => throw new \InvalidArgumentException(
                sprintf('No repository found for entity name "%s"', $entityName)
            ),
        };
    }
}


