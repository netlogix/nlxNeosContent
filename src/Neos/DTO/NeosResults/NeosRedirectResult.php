<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO\NeosResults;

class NeosRedirectResult implements NeosResult
{
    public function __construct(
        protected string $redirectPathInfo,
    ) {
    }

    public function getRedirectPathInfo(): string
    {
        return $this->redirectPathInfo;
    }
}
