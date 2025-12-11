<?php

declare(strict_types=1);

namespace nlxNeosContent\Error;

use Exception;
use Throwable;

class FaultyNeosSectionDataException extends Exception implements NeosExceptionInterface
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null, mixed $sectionData = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
