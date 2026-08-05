<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\Sitemap;

use nlxNeosContent\Error\NeosExceptionInterface;

class PageTreeCouldNotBeLoaddedException extends \Exception implements NeosExceptionInterface
{
    public function __construct(
        ?\Throwable $previous = null,
    ) {
        parent::__construct('Neos sitemap fetch failed', 1738078146, $previous);
    }
}