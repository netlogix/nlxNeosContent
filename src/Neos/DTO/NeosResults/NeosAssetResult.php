<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\DTO\NeosResults;

readonly final class NeosAssetResult
{
    public function __construct(
        protected string $content,
        protected int $statusCode,
        protected string $contentType,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
