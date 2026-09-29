<?php

declare(strict_types=1);

namespace nlxNeosContent\Service;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Neos shortcuts may point to "swCategory://<id>" or "swProduct://<id>". Neos can't build
 * a URL for those, so its redirect carries the raw link (lowercased by URI normalization).
 */
class ShopwareLinkRedirectResolver
{
    private const ROUTES = [
        'swcategory' => ['frontend.navigation.page', 'navigationId'],
        'swproduct' => ['frontend.detail.page', 'productId'],
    ];

    public function __construct(
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
    ) {
    }

    public function resolve(string $location, string $host, SalesChannelContext $salesChannelContext): ?string
    {
        if (preg_match('/^(swcategory|swproduct):\/\/([a-z0-9\-]+)\/?(#.*)?$/i', $location, $matches) !== 1) {
            return null;
        }

        [$routeName, $parameterName] = self::ROUTES[strtolower($matches[1])];
        $placeholder = $this->seoUrlPlaceholderHandler->generate($routeName, [$parameterName => $matches[2]]);

        return $this->seoUrlPlaceholderHandler->replace($placeholder, $host, $salesChannelContext) . ($matches[3] ?? '');
    }
}
