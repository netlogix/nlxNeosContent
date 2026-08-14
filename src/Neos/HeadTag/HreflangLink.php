<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * One alternate-language link as extracted from Neos's `head` array: the
 * hreflang code as Neos provided it, and the language-agnostic content path
 * (Neos's own domain and language-prefix segment already stripped from the
 * href). The corresponding Shopware sales channel domain still needs to be
 * resolved and combined with this path - see
 * ContentExchangeService::findDomainForHreflangCode().
 */
final readonly class HreflangLink
{
    public function __construct(
        public string $hreflangCode,
        public string $contentPath,
    ) {
    }
}
