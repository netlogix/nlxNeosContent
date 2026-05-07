<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\ContentReplacement;

use nlxNeosContent\Error\NeosExceptionInterface;
use Symfony\Component\HttpFoundation\Request;

class MissingEntityIdForResolverContextException extends \Exception implements NeosExceptionInterface
{
    public function __construct(
        public Request $request,
        int $code = 0,
        ?\Throwable $previous = null,
    )
    {
        parent::__construct('Could not find entityId for resolverContext in request.', $code, $previous);
    }
}
