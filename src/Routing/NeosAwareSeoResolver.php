<?php

declare(strict_types=1);

namespace nlxNeosContent\Routing;

use nlxNeosContent\Error\PageTree\NoTreeItemFoundException;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use nlxNeosContent\Service\ConfigService;
use nlxNeosContent\Service\NeosPageTreeService;
use nlxNeosContent\Storefront\Controller\NeosPageController;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Content\Seo\ResolvedSeoUrl;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Content\Seo\SeoUrlRequestContext;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * A category/product rename leaves its old seo_url row in place as a non-canonical
 * redirect to the new one. If a Neos page has since taken over that same "old" path,
 * Shopware would otherwise redirect straight past it (via CanonicalRedirectService)
 * without ever giving Neos a chance - so once we've confirmed one exists, this hands
 * the path directly to NeosPageController's dedicated route instead, letting normal
 * routing dispatch there without ever going through Router::matchNeosPath()'s
 * exception-based fallback (and its own, separate Neos-tree check) at all.
 * With no Neos page at that path, the redirect proceeds exactly as before.
 *
 * Canonical (currently live) matches are left untouched: a live Shopware entity
 * always wins there. Nothing here can safely tell an intentional, actively used
 * path apart from an accidental collision, so overriding it would be as arbitrary
 * as the bug we're fixing - that needs preventing at creation time, not resolved here.
 */
#[AsDecorator(SeoResolver::class)]
class NeosAwareSeoResolver extends AbstractSeoResolver
{
    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractSeoResolver $inner,
        private readonly ConfigService $configService,
        private readonly AbstractNeosPageTreeLoader $neosPageTreeLoader,
        private readonly NeosPageTreeService $neosPageTreeService,
    ) {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        return $this->inner;
    }

    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        return $this->inner->resolve($languageId, $salesChannelId, $pathInfo);
    }

    public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl
    {
        $resolved = $this->inner->resolveUrl($context);

        // Only a stale, superseded alias (a redirect target already computed) is a candidate -
        // a canonical match or a plain no-match is left entirely alone.
        if ($resolved->isCanonical || $resolved->canonicalPathInfo === null) {
            return $resolved;
        }

        if (
            !$this->configService->isEnabled($context->salesChannelId)
            || !$this->configService->isNavigationExtensionEnabled($context->salesChannelId)
        ) {
            return $resolved;
        }

        try {
            $tree = $this->neosPageTreeLoader->load($context->salesChannelId, $context->languageId);
            $this->neosPageTreeService->findByPathInfoInTree($context->pathInfo, $tree);
        } catch (NoTreeItemFoundException) {
            return $resolved;
        }

        return new ResolvedSeoUrl(
            pathInfo: NeosPageController::CONTENT_BY_PATH_ROUTE_PREFIX . ltrim($context->pathInfo, '/'),
            isCanonical: true,
        );
    }
}
