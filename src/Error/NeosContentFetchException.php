<?php

declare(strict_types=1);

namespace nlxNeosContent\Error;

class NeosContentFetchException extends \Exception
{
    public function __construct(string $message = 'Neos Content could not be fetched.', int $code = 1752651981, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
