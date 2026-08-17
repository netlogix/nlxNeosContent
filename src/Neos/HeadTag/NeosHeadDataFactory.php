<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Builds a NeosHeadData DTO from the raw `head` tag strings Neos provides.
 *
 * The page title, meta description, canonical link and robots directive are
 * matched via their respective named rules (TitleTagRule/DescriptionTagRule/
 * CanonicalTagRule/RobotsTagRule) and extracted rather than passed through as
 * raw tags, since Shopware needs them as plain strings for
 * $page->getMetaInformation(). Alternate-language links (AlternateLanguageLinkRule)
 * are extracted into HreflangLink entries with Neos's own domain and
 * language-prefix segment already stripped from the href - resolving the
 * matching Shopware sales channel domain and combining it with that path
 * happens in the caller, since it needs a SalesChannelContext this factory
 * doesn't have. Everything else is matched against the configured allow-list
 * (see HeadTagAllowListProviderInterface) and kept as raw tag strings for
 * verbatim injection into the storefront <head>. Deny-by-default: a tag that
 * doesn't match any allow-rule is dropped entirely.
 */
final readonly class NeosHeadDataFactory
{
    public function __construct(
        private HeadTagAllowListProviderInterface $allowListProvider,
        private HeadTagParser $parser,
        private TitleTagRule $titleTagRule,
        private DescriptionTagRule $descriptionTagRule,
        private CanonicalTagRule $canonicalTagRule,
        private RobotsTagRule $robotsTagRule,
        private AlternateLanguageLinkRule $alternateLanguageLinkRule,
    ) {
    }

    /**
     * @param array<string> $rawHeadTags
     */
    public function createHeadData(array $rawHeadTags): NeosHeadData
    {
        $rules = $this->allowListProvider->getRules();

        $title = null;
        $description = null;
        $canonical = null;
        $robots = null;
        $hreflangLinks = [];
        $remainingHeadData = [];

        foreach ($rawHeadTags as $rawHeadTag) {
            $parsedTag = $this->parser->parse($rawHeadTag);
            if ($parsedTag === null) {
                continue;
            }

            if ($title === null && $this->titleTagRule->matches($parsedTag)) {
                $title = trim($parsedTag->textContent);
                continue;
            }

            if ($description === null && $this->descriptionTagRule->matches($parsedTag)) {
                $description = $parsedTag->attributes['content'] ?? null;
                continue;
            }

            if ($canonical === null && $this->canonicalTagRule->matches($parsedTag)) {
                $canonical = $parsedTag->attributes['href'] ?? null;
                continue;
            }

            if ($robots === null && $this->robotsTagRule->matches($parsedTag)) {
                $robots = $parsedTag->attributes['content'] ?? null;
                continue;
            }

            if ($this->alternateLanguageLinkRule->matches($parsedTag)) {
                $hreflangCode = $parsedTag->attributes['hreflang'] ?? null;
                $href = $parsedTag->attributes['href'] ?? null;
                if ($hreflangCode !== null && $href !== null) {
                    $hreflangLinks[] = new HreflangLink($hreflangCode, $this->extractContentPath($href));
                }
                continue;
            }

            if ($this->matchesAnyRule($parsedTag, $rules)) {
                $remainingHeadData[] = $rawHeadTag;
            }
        }

        return new NeosHeadData($title, $description, $canonical, $robots, $hreflangLinks, $remainingHeadData);
    }

    /**
     * Strips Neos's own domain and leading language-prefix path segment
     * (e.g. "en"/"de") from an absolute href, leaving the language-agnostic
     * content path - the same shape NeosPageController already uses for
     * content-by-path lookups.
     */
    private function extractContentPath(string $href): string
    {
        $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== ''
        ));

        array_shift($segments);

        return implode('/', $segments);
    }

    /**
     * @param HeadTagRuleInterface[] $rules
     */
    private function matchesAnyRule(ParsedHeadTag $tag, array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule->matches($tag)) {
                return true;
            }
        }

        return false;
    }
}
