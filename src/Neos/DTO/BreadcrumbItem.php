<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO;

readonly class BreadcrumbItem
{
    public function __construct(
        public string $label,
        public string $url,
    ) {
    }
}
