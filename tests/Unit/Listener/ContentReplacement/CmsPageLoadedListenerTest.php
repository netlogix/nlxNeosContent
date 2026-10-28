<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Listener\ContentReplacement;

use nlxNeosContent\Core\Content\Cms\SalesChannel\ResolverContextAwareCmsPageLoader;
use nlxNeosContent\Core\Content\NeosNode\NeosNodeEntity;
use nlxNeosContent\Listener\ContentReplacement\CmsPageLoadedListener;
use nlxNeosContent\Service\ContentExchangeService;
use nlxNeosContent\Service\ResolverContextService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CmsPageLoadedListenerTest extends TestCase
{
    private const PARENT_ID = '0190000000000000000000000000000a';

    private const VARIANT_ID = '0190000000000000000000000000000b';

    public function testDetailPageUsesTheVariantShopwareLoadsThePageWith(): void
    {
        $request = $this->detailPageRequest();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $variant = new SalesChannelProductEntity();
        $variant->setId(self::VARIANT_ID);
        $variant->setParentId(self::PARENT_ID);
        $resolverContext = new EntityResolverContext($salesChannelContext, $request, new ProductDefinition(), $variant);
        $request->attributes->set(ResolverContextAwareCmsPageLoader::REQUEST_ATTRIBUTE, $resolverContext);

        $resolverContextService = $this->createMock(ResolverContextService::class);
        $resolverContextService->expects(static::never())->method('getResolverContextForEntityNameAndId');

        $this->listener($resolverContextService, $resolverContext)(
            new CmsPageLoadedEvent($request, new CmsPageCollection([$this->neosCmsPage()]), $salesChannelContext)
        );
    }

    public function testDetailPageFallsBackToTheRequestsProduct(): void
    {
        $request = $this->detailPageRequest();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $product = new SalesChannelProductEntity();
        $product->setId(self::PARENT_ID);
        $resolverContext = new EntityResolverContext($salesChannelContext, $request, new ProductDefinition(), $product);

        $resolverContextService = $this->createMock(ResolverContextService::class);
        $resolverContextService->expects(static::once())
            ->method('getResolverContextForEntityNameAndId')
            ->with(ProductDefinition::ENTITY_NAME, self::PARENT_ID)
            ->willReturn($resolverContext);

        $this->listener($resolverContextService, $resolverContext)(
            new CmsPageLoadedEvent($request, new CmsPageCollection([$this->neosCmsPage()]), $salesChannelContext)
        );
    }

    private function listener(ResolverContextService $resolverContextService, EntityResolverContext $expectedResolverContext): CmsPageLoadedListener
    {
        $contentExchangeService = $this->createMock(ContentExchangeService::class);
        $contentExchangeService->method('getAlternativeCmsSectionsFromNeos')->willReturn(new CmsSectionCollection());
        $contentExchangeService->expects(static::once())
            ->method('loadSlotData')
            ->with(static::anything(), static::identicalTo($expectedResolverContext));

        return new CmsPageLoadedListener($contentExchangeService, $resolverContextService);
    }

    private function detailPageRequest(): Request
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.detail.page');
        $request->attributes->set('productId', self::PARENT_ID);

        return $request;
    }

    private function neosCmsPage(): CmsPageEntity
    {
        $neosNode = new NeosNodeEntity();
        $neosNode->setNeosConnection(true);

        $cmsPage = new CmsPageEntity();
        $cmsPage->setId('0190000000000000000000000000000c');
        $cmsPage->addExtension('nlxNeosNode', $neosNode);

        return $cmsPage;
    }
}
