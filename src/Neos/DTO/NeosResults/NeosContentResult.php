<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO\NeosResults;

use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;

readonly final class NeosContentResult
{
    /**
     * @param array<string> $head Raw HTML strings for <head> tags provided by Neos
     *                             (e.g. <meta ...>, <title>...</title>, <script type="application/ld+json">...</script>).
     * @param bool $cacheable False if Neos answered with "Cache-Control: no-store", e.g. for personalized content.
     */
    public function __construct(
        protected CmsSectionCollection $sections,
        protected array $head = [],
        protected bool $cacheable = true,
    ) {
    }

    public function isCacheable(): bool
    {
        return $this->cacheable;
    }

    public function getSections(): CmsSectionCollection
    {
        return $this->sections;
    }

    /**
     * @return array<string>
     */
    public function getHead(): array
    {
        return $this->head;
    }
}
