<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;

readonly class NeosPageDTO
{
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
