<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\Sitemap;

use nlxNeosContent\Error\NeosExceptionInterface;

class OffsetPagingNotSupportedException extends \Exception implements NeosExceptionInterface
{
    public function __construct(
        public readonly string $providerName,
        public readonly int $offset,
        int $code = 1738074123,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Provider "%s" delivers all URLs in a single batch and does not support offset paging; got offset %d.',
                $providerName,
                $offset
            ),
            $code,
            $previous
        );
    }
}