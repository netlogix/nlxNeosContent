<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Something that can decide whether a parsed head tag matches it - implemented
 * both by the generic, configurable HeadTagRule (used for the allow-list) and
 * by named rules for specific tags (e.g. TitleTagRule, DescriptionTagRule,
 * CanonicalTagRule), so NeosHeadDataFactory can treat all of them uniformly.
 */
interface HeadTagRuleInterface
{
    public function matches(ParsedHeadTag $tag): bool;
}
