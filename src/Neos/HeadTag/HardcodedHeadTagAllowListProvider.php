<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Default, hardcoded allow-list.
 *
 * The page title, meta description, canonical link and robots directive never
 * reach this allow-list at all - NeosHeadDataFactory extracts them from their
 * respective tags before filtering runs, and they're set on
 * $page->getMetaInformation() instead. Deliberately excludes everything else
 * Shopware's own storefront/layout/meta.html.twig already renders from that
 * same MetaInformation object - meta keywords, OpenGraph and Twitter Card
 * tags, hreflang links - since allowing those through as well would duplicate
 * them in the rendered <head>. og:locale and msapplication-* are exceptions:
 * Shopware's core template has no fields for them at all, so they're
 * allow-listed to pass through raw. Only tags Shopware has no story for today
 * are allowed through by default.
 *
 * Bound to HeadTagAllowListProviderInterface via services.xml; swap that
 * binding (e.g. to a System-Config-backed implementation) to change the
 * rule source later without touching NeosHeadDataFactory or its callers.
 */
final class HardcodedHeadTagAllowListProvider implements HeadTagAllowListProviderInterface
{
    public function getRules(): array
    {
        return [
            new HeadTagRule('meta', 'property', 'fb:', attributeValueIsPrefix: true),
            new HeadTagRule('meta', 'name', 'google-site-verification'),
            new HeadTagRule('meta', 'property', 'og:locale'),
            new HeadTagRule('meta', 'name', 'msapplication-', attributeValueIsPrefix: true),
            new HeadTagRule('script', 'type', 'application/ld+json'),
        ];
    }
}
