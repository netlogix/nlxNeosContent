<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO\NeosResults;

use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;

readonly class NeosContentResult implements NeosResult
{
    public function __construct(
        protected CmsSectionCollection $sections,
    ) {
    }

    public function getSections(): CmsSectionCollection
    {
        return $this->sections;
    }
}
