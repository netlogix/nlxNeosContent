<?php

declare(strict_types=1);

namespace nlxNeosContent\Storefront\Controller;

use Exception;
use nlxNeosContent\Core\Content\Cms\Aggregate\CmsSection\NeosCmsSectionEntity;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\ResolverContextService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 *
 * This controller is used to render a CMS section based on the provided sectionData, entity name and ID.
 * The rendered html's sole purpose is to be used in the Neos CMS for advanced editing preview.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class CmsSectionController extends StorefrontController
{
    function __construct(
        #[Autowire(service: 'sales_channel.product.repository', lazy: true)]
        private readonly SalesChannelRepository $productRepository,
        #[Autowire(service: 'sales_channel.category.repository', lazy: true)]
        private readonly SalesChannelRepository $categoryRepository,
        private readonly ContentExchangeService $contentExchangeService,
        private readonly ResolverContextService $resolverContextService,
        private readonly EntityRepository $cmsPageRepository,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route(path: '/html-content/cms-section', name: 'frontend.html-content.cms-section', methods: ['POST'])]
    public function cmsSection(
        Request $request,
        SalesChannelContext $context
    ): Response {
        $entityName = $request->get('sw-entity-name');
        $entityId = $request->get('sw-entity-id');
        $sectionData = json_decode($request->getContent(), true);

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

        if (empty($entityId)) {
            $entityId = $entityResponse->first()->getId();
        }

        $entity = $entityResponse->first();
        if (!assert($entity instanceof Entity)) {
            throw new \RuntimeException(
                sprintf(
                    'Search Result is not an instance of Entity for entity name "%s" and id %s',
                    $entityName,
                    $entityId
                )
            );
        }

        $resolverContext = $this->resolverContextService->getResolverContextForEntity(
            $entity,
            $context,
            $request,
            true
        );

        $sectionData['id'] = 'neos-preview-section';

        /** @var CmsSectionEntity $section */
        $section = $this->serializer->denormalize($sectionData, NeosCmsSectionEntity::class, 'json');
        $section->setPosition(1);

        foreach ($section->getBlocks() as $block) {
            $block->setSection($section);
            foreach ($block->getSlots() as $slot) {
                $slot->setBlock($block);
            }
            if ($entityName === ProductDefinition::ENTITY_NAME && $block->getType() === 'cross-selling') {
                $fieldConfig = $block->getSlots()->first()?->getFieldConfig();

                if (!$fieldConfig) {
                    $fieldConfig = new FieldConfigCollection();
                }
                $fieldConfig->add(new FieldConfig(ProductDefinition::ENTITY_NAME, 'static', $entityId));
                $block->getSlots()->first()->setFieldConfig(
                    $fieldConfig
                );
            }
        }


        $this->contentExchangeService->loadSlotData($section->getBlocks(), $resolverContext);

        $parameters = [
            'section' => $section,
            'context' => $context,
            'neosRequest' => true,
        ];

        $cmsPages = $this->cmsPageRepository->search(
            (new Criteria([$entityResponse->first()->getCmsPageId()]))
                ->addAssociation('sections'),
            $context->getContext()
        );

        /** @var CmsPageEntity $cmsPage */
        $cmsPage = $cmsPages->first();
        $cmsPage->setSections(new CmsSectionCollection([$section]));

        $parameters['cmsPage'] = $cmsPage;

        return $this->render(
            '@Storefront/storefront/page/content/detail.html.twig',
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


