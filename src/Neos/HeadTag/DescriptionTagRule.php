<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Matches <meta name="description"> tags.
 */
final readonly class DescriptionTagRule implements HeadTagRuleInterface
{
    public function matches(ParsedHeadTag $tag): bool
    {
        return strcasecmp($tag->tagName, 'meta') === 0
            && strcasecmp($tag->attributes['name'] ?? '', 'description') === 0;
    }
}
