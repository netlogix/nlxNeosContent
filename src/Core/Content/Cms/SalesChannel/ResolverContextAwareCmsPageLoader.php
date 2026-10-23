<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps the resolver context Shopware loads a CMS page with on the request while the page loads, so the
 * CmsPageLoadedListener resolves the Neos content with the same entity. On a product detail page that is the
 * variant Shopware shows, e.g. the best variant for the URL of a parent product, while the request's "productId"
 * is still the parent's. The CmsPageLoadedEvent itself doesn't carry the resolver context.
 */
#[AsDecorator(SalesChannelCmsPageLoader::class)]
class ResolverContextAwareCmsPageLoader implements SalesChannelCmsPageLoaderInterface
{
    public const REQUEST_ATTRIBUTE = 'nlxNeosContentResolverContext';

    public function __construct(
        #[AutowireDecorated]
        private readonly SalesChannelCmsPageLoaderInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed>|null $config
     *
     * @return EntitySearchResult<CmsPageCollection>
     */
    public function load(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?array $config = null,
        ?ResolverContext $resolverContext = null
    ): EntitySearchResult {
        $previous = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $resolverContext);

        try {
            return $this->decorated->load($request, $criteria, $context, $config, $resolverContext);
        } finally {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $previous);
        }
    }
}
