<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

readonly class NeosPageDTO
{
    function __construct(
        public string $identifier,
        public string $label,
        public string $path,
        public NeosPageCollection $children
    ) {
    }
}
