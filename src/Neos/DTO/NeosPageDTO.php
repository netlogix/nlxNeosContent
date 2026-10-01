<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;

readonly class NeosPageDTO
{
    /**
     * @param string $path Content path of the page, or an absolute URL for shortcuts to external targets
     * @param ?string $flyoutText Plain text shown in the navigation flyout of this page
     * @param ?string $flyoutImage Absolute URL of the image shown in the navigation flyout of this page
     */
    function __construct(
        public string $identifier,
        public string $label,
        public string $path,
        public NeosPageCollection $children,
        public bool $hiddenInIndex = false,
        public ?string $flyoutText = null,
        public ?string $flyoutImage = null,
    ) {
    }

    public function getUrl(): string
    {
        if (parse_url($this->path, PHP_URL_SCHEME) !== null) {
            return $this->path;
        }

        return sprintf('%s/%s#', SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER, trim($this->path, '/'));
    }
}
