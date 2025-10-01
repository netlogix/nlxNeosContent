<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use Exception;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 *
 * This controller is used to render a CMS block based on the provided entity name and ID.
 * The rendered html's sole purpose is to be used in the Neos CMS for advanced editing preview.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class CmsBlockController extends StorefrontController
{
    function __construct(
        #[Autowire(service: 'sales_channel.product.repository', lazy: true)]
        private readonly SalesChannelRepository $productRepository,
        #[Autowire(service: 'sales_channel.category.repository', lazy: true)]
        private readonly SalesChannelRepository $categoryRepository,
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
        private readonly EntityRepository $cmsPageRepository,
    ) {
    }

    #[Route(path: '/html-content/cms-block', name: 'frontend.html-content.cms-block', methods: ['POST'])]
    function cmsBlock(
        Request $request,
        SalesChannelContext $context
    ): Response {
        $entityName = $request->get('sw-entity-name');
        $entityId = $request->get('sw-entity-id');
        $blockData = json_decode($request->getContent(), true);

        $repository = $this->getRepository($entityName);

        $criteria = $this->createCriteria($entityName, $entityId);

        try {
            $entityResponse = $repository->search(
                $criteria,
                $context
            );
        } catch (Exception $exception) {
            try {
                $criteria = $this->createCriteria($entityName, null);
                $entityResponse = $repository->search(
                    $criteria,
                    $context
                );
            } catch (Exception $e) {
                throw new \RuntimeException(
                    sprintf(
                        'Failed to load entity for entity name "%s" and id "%s": %s',
                        $entityName,
                        $entityId,
                        $e->getMessage()
                    ),
                    0,
                    $exception
                );
            }
        }

        if ($entityResponse->count() === 0) {
            throw new \RuntimeException(
                sprintf('No entity found for entity name "%s" and id "%s"', $entityName, $entityId)
            );
        }

        /**
         * FIXME: If the found entity is a Main-Product and has Variants, we might need to load the first variant instead
         * This depends a little bit on how the Variants display is configured in SW6.
         * There are different options how Variants are treated in Storefront and apparently this controller can not handle it yet.
         * */

        /** @var CmsBlockEntity $block */
        $block = $this->contentExchangeService->createCmsBlockFromNeosElement(
            $blockData,
            1,
            'section'
        );

        if (empty($entityId)) {
            $entityId = $entityResponse->first()->getId();
        }

        $resolverContext = $this->resolverContextService->getResolverContextForEntity(
            $entityResponse->first(),
            $context,
            $request,
            true
        );

        $cmsBlockCollection = new CmsBlockCollection([$block]);

        if($entityName === ProductDefinition::ENTITY_NAME && $blockData['type'] === 'cross-selling') {

            $fieldConfig = $cmsBlockCollection->getSlots()->first()?->getFieldConfig();

            if(!$fieldConfig) {
                $fieldConfig = new FieldConfigCollection();
            }
            $fieldConfig->add(new FieldConfig(ProductDefinition::ENTITY_NAME, 'static', $entityId));
            $cmsBlockCollection->getSlots()->first()->setFieldConfig(
                $fieldConfig
            );
        }

        $this->contentExchangeService->loadSlotData($cmsBlockCollection, $resolverContext);

        $parameters = [
            'block' => $block,
            'context' => $context,
            'neosRequest' => true,
        ];

        if ($entityName === CategoryDefinition::ENTITY_NAME) {
            $cmsPages = $this->cmsPageRepository->search(
                (new Criteria([$entityResponse->first()->getCmsPageId()]))
                    ->addAssociation('sections'),
                $context->getContext()
            );

            /** @var CmsPageEntity $cmsPage */
            $cmsPage = $cmsPages->first();
            $cmsPage->getSections()->first()->setBlocks($cmsBlockCollection);

            $parameters['cmsPage'] = $cmsPage;
        }

        return $this->render(
            sprintf('@Storefront/storefront/block/cms-block-%s.html.twig', $block->getType()),
            $parameters
        );
    }

    private function createCriteria(string $entityName, ?string $entityId): Criteria
    {
        $criteria = $entityId ? new Criteria([$entityId]) : new Criteria();
        $criteria->setTitle('cms-block-criteria-' . $entityName);
        // Big Shops may have so many products that we exceed the memory limit, thus we set a limit of 1
        $criteria->setLimit(1);
        if (!$entityId) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        if ($entityName === CategoryDefinition::ENTITY_NAME && !$entityId) {
            $criteria->addFilter(new EqualsFilter('type', 'page'));
            $criteria->addFilter(new EqualsFilter('productAssignmentType', 'product'));
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('products.id', null),
                    ]
                )
            );
        }

        if ($entityName === ProductDefinition::ENTITY_NAME) {
            $criteria->addAssociation('media.media');
            $criteria->addAssociation('manufacturer.media');
        }
        return $criteria;
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


