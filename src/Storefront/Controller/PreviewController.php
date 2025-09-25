<?php

declare(strict_types=1);

namespace netlogixNeosContent\Storefront\Controller;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Events\CmsPageLoaderCriteriaEvent;
use Shopware\Core\Content\Cms\SalesChannel\AbstractCmsRoute;
use Shopware\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\Event\SalesChannelProcessCriteriaEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Cms\CmsPageLoadedHook;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedHook;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Page\Product\ProductPageLoadedHook;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Autoconfigure(public: true)]
#[Route(defaults: ['_routeScope' => ['storefront']])]
class PreviewController extends StorefrontController
{
    /**
     * This controller is responsible for handling preview requests.
     *
     * It should render a Storefront view of a given page in the given SalesChannel and language.
     *
     * Further if the request comes from Neos it should render the page in the context of the workspace the user is currently working in.
     * If the request comes from the Shopware-Admin it can just use the live-workspace.
     */

    public function __construct(
        #[Autowire(service: CmsRoute::class)]
        private readonly AbstractCmsRoute $cmsRoute,
        private readonly NavigationPageLoader $navigationPageLoader,
        private readonly ProductPageLoader $productPageLoader,
        private readonly EntityRepository $cmsPageRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route(
        path: '/preview',
        name: 'nlx.preview',
        requirements: [
            'cmsPageId' => Requirement::ASCII_SLUG,
            'entityId' => Requirement::ASCII_SLUG
        ],
        methods: ['GET']
    )]
    public function loadPreview(
        #[MapQueryParameter('cmsPageId')]
        string $cmsPageId,
        #[MapQueryParameter('entityId')]
        string $entityId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        /**
         * @deprecated will be removed once ^6.6 support ends
         */
        if (!Feature::has('cache_rework')) {
            Feature::setActive('cache_rework', true);
        }
        $cmsPage = $this->getPage($cmsPageId, $salesChannelContext->getContext());
        $pageType = $cmsPage->getType();

        $this->eventDispatcher->addListener(
            CmsPageLoaderCriteriaEvent::class,
            function (CmsPageLoaderCriteriaEvent $event) use ($cmsPageId) {
                $criteria = $event->getCriteria();
                $criteria->setIds([$cmsPageId]);
            }
        );

        switch ($pageType) {
            case 'product_detail':
                $request->attributes->set('productId', $entityId);
                return $this->loadProductDetailPage(
                    $request,
                    $salesChannelContext
                );
            case 'product_list':
                $request->attributes->set('navigationId', $entityId);
                return $this->loadCategoryPage(
                    $request,
                    $salesChannelContext
                );
            default:
                $page = $this->cmsRoute->load($cmsPageId, $request, $salesChannelContext)->getCmsPage();
                $this->hook(new CmsPageLoadedHook($page, $salesChannelContext));
                return $this->renderStorefront(
                    '@Storefront/storefront/page/content/index.html.twig',
                    ['page' => ['cmsPage' => $page]]
                );
        }
    }

    private function getPage(string $cmsPageId, Context $context): CmsPageEntity
    {
        $criteria = new Criteria([$cmsPageId]);
        $criteria->addAssociation('sections.blocks');
        return $this->cmsPageRepository->search(
            ($criteria),
            $context
        )->first();
    }

    private function loadProductDetailPage(
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        $this->eventDispatcher->addListener(
            SalesChannelProcessCriteriaEvent::class,
            function (SalesChannelProcessCriteriaEvent $event) use ($salesChannelContext) {
                $criteria = $event->getCriteria();
                $filters = $criteria->getFilters();
                $criteria->resetFilters();
                foreach ($filters as $filter) {
                    if (!$filter instanceof ProductAvailableFilter) {
                        $criteria->addFilter($filter);
                    }
                }

            }
        );

        $page = $this->productPageLoader->load($request, $salesChannelContext);
        $this->hook(new ProductPageLoadedHook($page, $salesChannelContext));

        return $this->renderStorefront('@Storefront/storefront/page/content/product-detail.html.twig', ['page' => $page]
        );
    }

    private function loadCategoryPage(
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        $this->eventDispatcher->addListener(
            SalesChannelProcessCriteriaEvent::class,
            function (SalesChannelProcessCriteriaEvent $event) use ($salesChannelContext) {
                $criteria = $event->getCriteria();
                $filters = $criteria->getFilters();
                $criteria->resetFilters();
                foreach ($filters as $filter) {
                    if (!$filter instanceof ProductAvailableFilter) {
                        $criteria->addFilter($filter);
                    }
                }

            }
        );
        $page = $this->navigationPageLoader->load($request, $salesChannelContext);
        $this->hook(new NavigationPageLoadedHook($page, $salesChannelContext));

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }
}
