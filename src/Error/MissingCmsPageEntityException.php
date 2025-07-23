<?php

declare(strict_types=1);

namespace netlogixNeosContent\Error;

class MissingCmsPageEntityException extends \Exception
{
    public function __construct(string $message = 'No cmsPage entity was found in CmsPageLoadedEvent.', int $code = 1752652152, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
