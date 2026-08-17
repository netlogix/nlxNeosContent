<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Matches <script type="application/ld+json"> tags.
 */
final readonly class JsonLdScriptRule implements HeadTagRuleInterface
{
    public function matches(ParsedHeadTag $tag): bool
    {
        return strcasecmp($tag->tagName, 'script') === 0
            && strcasecmp($tag->attributes['type'] ?? '', 'application/ld+json') === 0;
    }
}
