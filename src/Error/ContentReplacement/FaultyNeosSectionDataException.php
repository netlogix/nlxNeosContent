<?php

declare(strict_types=1);

namespace nlxNeosContent\Error\ContentReplacement;

use Exception;
use nlxNeosContent\Error\NeosExceptionInterface;
use Throwable;

class FaultyNeosSectionDataException extends Exception implements NeosExceptionInterface
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
