<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;

readonly class NeosPageDTO
{
    /**
     * Public storefront path of the page, rendered from the Neos page SEO URL template.
     * Equals $path when no template is configured.
     */
    public string $seoPath;

    /**
     * @param string $path Content path of the page, or an absolute URL for shortcuts to external targets
     * @param array<string, mixed> $customFields Installation specific data of the page, added on the Neos side
     */
    function __construct(
        public string $identifier,
        public string $label,
        public string $path,
        public NeosPageCollection $children,
        public bool $hiddenInIndex = false,
        public array $customFields = [],
        ?string $seoPath = null,
    ) {
        $this->seoPath = $seoPath ?? $path;
    }

    public function getUrl(): string
    {
        if (self::isExternalUrl($this->path)) {
            return $this->path;
        }

        return sprintf('%s/%s#', SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER, ltrim($this->seoPath, '/'));
    }

    public static function isExternalUrl(string $path): bool
    {
        $scheme = parse_url($path, PHP_URL_SCHEME);

        return \is_string($scheme) && !\in_array(strtolower($scheme), ['javascript', 'vbscript', 'data'], true);
    }
}
