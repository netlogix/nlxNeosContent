<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

final readonly class ParsedHeadTag
{
    /**
     * @param array<string, string> $attributes Attribute names are lower-cased.
     */
    public function __construct(
        public string $tagName,
        public array $attributes,
        public string $textContent,
    ) {
    }
}
