<?php

declare(strict_types=1);

namespace nlxNeosContent\Tests\Unit\Core\Content\Cms\SalesChannel;

use nlxNeosContent\Core\Content\Cms\SalesChannel\ResolverContextAwareCmsPageLoader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ResolverContextAwareCmsPageLoaderTest extends TestCase
{
    public function testTheResolverContextIsOnTheRequestWhileThePageLoads(): void
    {
        $request = new Request();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $resolverContext = new ResolverContext($salesChannelContext, $request);
        $result = new EntitySearchResult(CmsPageDefinition::ENTITY_NAME, 0, new CmsPageCollection(), null, new Criteria(), Context::createDefaultContext());

        $decorated = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $decorated->expects(static::once())
            ->method('load')
            ->willReturnCallback(static function (Request $request) use ($resolverContext, $result): EntitySearchResult {
                static::assertSame($resolverContext, $request->attributes->get(ResolverContextAwareCmsPageLoader::REQUEST_ATTRIBUTE));

                return $result;
            });

        $loaded = (new ResolverContextAwareCmsPageLoader($decorated))
            ->load($request, new Criteria(), $salesChannelContext, null, $resolverContext);

        static::assertSame($result, $loaded);
        static::assertNull($request->attributes->get(ResolverContextAwareCmsPageLoader::REQUEST_ATTRIBUTE));
    }

    public function testTheOuterResolverContextIsRestoredAfterANestedLoad(): void
    {
        $request = new Request();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $outer = new ResolverContext($salesChannelContext, $request);
        $request->attributes->set(ResolverContextAwareCmsPageLoader::REQUEST_ATTRIBUTE, $outer);

        $decorated = $this->createMock(SalesChannelCmsPageLoaderInterface::class);
        $decorated->method('load')->willThrowException(new \RuntimeException('Failed'));

        try {
            (new ResolverContextAwareCmsPageLoader($decorated))
                ->load($request, new Criteria(), $salesChannelContext, null, new ResolverContext($salesChannelContext, $request));
            static::fail('The exception of the decorated loader must pass through.');
        } catch (\RuntimeException) {
        }

        static::assertSame($outer, $request->attributes->get(ResolverContextAwareCmsPageLoader::REQUEST_ATTRIBUTE));
    }
}
