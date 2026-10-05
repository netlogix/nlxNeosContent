<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO\NeosResults;

readonly final class NeosRedirectResult
{
    public function __construct(
        protected string $redirectPathInfo,
        protected int $statusCode,
    ) {
    }

    public function getRedirectPathInfo(): string
    {
        return $this->redirectPathInfo;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
