<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

readonly class NeosPageDTO
{
    /**
     * @param ?string $url Absolute URL for this page. Not part of the Neos page tree data
     *                itself (never populated when denormalized from the pagetree API) - only
     *                set where it's actually needed, e.g. per breadcrumb item, to avoid
     *                resolving it for every node in the tree.
     */
    function __construct(
        public string $identifier,
        public string $label,
        public string $path,
        public NeosPageCollection $children,
        public bool $hiddenInIndex = false,
        public ?string $url = null,
    ) {
    }
}
