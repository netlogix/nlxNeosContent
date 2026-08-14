<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Matches the page's <title> tag.
 */
final readonly class TitleTagRule implements HeadTagRuleInterface
{
    public function matches(ParsedHeadTag $tag): bool
    {
        return strcasecmp($tag->tagName, 'title') === 0;
    }
}
