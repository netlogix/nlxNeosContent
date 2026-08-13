<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\Routing;

use nlxNeosContent\Error\NeosExceptionInterface;

class UnknownNeosPathException extends \Exception implements NeosExceptionInterface
{
    public function __construct(
        string $message = 'Unknown Neos Path via Identifier.',
        int $code = 1786545009,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
