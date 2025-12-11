<?php

declare(strict_types=1);

namespace nlxNeosContent\Error;

class NeosUrlNotConfiguredException extends \Exception implements NeosExceptionInterface
{
    public function __construct(string $message = 'Neos URL is not configured.', int $code = 1752646642, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
