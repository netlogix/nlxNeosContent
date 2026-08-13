<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\RequestError;

use nlxNeosContent\Error\NeosExceptionInterface;

class NoSalesChannelContextException extends \Exception implements NeosExceptionInterface
{
    public function __construct(
        string $message = 'No Sales Channel Context available.',
        int $code = 1786543029,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
