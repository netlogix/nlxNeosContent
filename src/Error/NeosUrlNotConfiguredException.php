<?php

declare(strict_types=1);

namespace netlogixNeosContent\Error;

class NeosUrlNotConfiguredException extends \Exception
{
    public function __construct(string $message = 'Neos URL is not configured.', int $code = 1752646642, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
