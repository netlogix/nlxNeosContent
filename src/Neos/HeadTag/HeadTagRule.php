<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Generic, configurable allow-rule: matches by tag name and, optionally, a key
 * attribute. Without an attribute, every tag with the given name matches. With
 * an attribute but no value, every tag carrying that attribute (regardless of
 * its value) matches. With a value, either an exact match or - if
 * $attributeValueIsPrefix is set - a prefix match is required (e.g. matching
 * every `property="og:*"` meta tag).
 */
final readonly class HeadTagRule implements HeadTagRuleInterface
{
    public function __construct(
        private string $tagName,
        private ?string $attributeName = null,
        private ?string $attributeValue = null,
        private bool $attributeValueIsPrefix = false,
    ) {
    }

    public function matches(ParsedHeadTag $tag): bool
    {
        if (strcasecmp($tag->tagName, $this->tagName) !== 0) {
            return false;
        }

        if ($this->attributeName === null) {
            return true;
        }

        $value = $tag->attributes[strtolower($this->attributeName)] ?? null;
        if ($value === null) {
            return false;
        }

        if ($this->attributeValue === null) {
            return true;
        }

        return $this->attributeValueIsPrefix
            ? str_starts_with($value, $this->attributeValue)
            : $value === $this->attributeValue;
    }
}
