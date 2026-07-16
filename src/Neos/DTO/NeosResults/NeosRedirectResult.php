<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO\NeosResults;

readonly final class NeosRedirectResult
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
