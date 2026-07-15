<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\PageTree;

use nlxNeosContent\Error\NeosExceptionInterface;

class NoTreeItemFoundException extends \Exception implements NeosExceptionInterface
{
    public function __construct(
        public readonly string $pathInfo,
        int $code = 1784106045,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('No page tree item found for path "%s".', $pathInfo), $code, $previous);
    }
}
