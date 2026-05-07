<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\ContentReplacement;

use nlxNeosContent\Error\NeosExceptionInterface;

class MissingEntityIdForResolverContextException extends \Exception implements NeosExceptionInterface
{
    public function __construct(string $message = 'No entityId found in request.', int $code = 1778140996, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
