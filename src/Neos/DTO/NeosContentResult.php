<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;

readonly class NeosContentResult
{
    public function __construct(
        public ?CmsSectionCollection $sections = null,
        public ?string $redirectPathInfo = null,
    ) {
    }

    public function isRedirect(): bool
    {
        return $this->redirectPathInfo !== null;
    }
}
