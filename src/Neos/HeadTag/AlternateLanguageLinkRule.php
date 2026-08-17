<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Matches <link rel="alternate" hreflang="..."> tags.
 */
final readonly class AlternateLanguageLinkRule implements HeadTagRuleInterface
{
    public function matches(ParsedHeadTag $tag): bool
    {
        return strcasecmp($tag->tagName, 'link') === 0
            && strcasecmp($tag->attributes['rel'] ?? '', 'alternate') === 0
            && isset($tag->attributes['hreflang']);
    }
}
