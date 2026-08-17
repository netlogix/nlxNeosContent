<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Matches <link rel="canonical"> tags.
 */
final readonly class CanonicalTagRule implements HeadTagRuleInterface
{
    public function matches(ParsedHeadTag $tag): bool
    {
        return strcasecmp($tag->tagName, 'link') === 0
            && strcasecmp($tag->attributes['rel'] ?? '', 'canonical') === 0;
    }
}
