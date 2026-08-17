<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

/**
 * Structured result of parsing Neos's raw `head` tag array (built by
 * NeosHeadDataFactory): fields Shopware has a dedicated place for - the page
 * title, meta description, canonical link and robots directive - are
 * extracted into their own getters. Alternate-language links are extracted
 * too, but only partially resolved (see HreflangLink) since turning them into
 * proper Shopware URLs needs a SalesChannelContext, which isn't available
 * here - that final step happens in the caller. Everything else that passed
 * the allow-list stays available raw via getRemainingHeadData() for verbatim
 * injection into the storefront <head>.
 */
readonly final class NeosHeadData
{
    /**
     * @param HreflangLink[] $hreflangLinks
     * @param array<string> $remainingHeadData Raw HTML tag strings, already allow-list filtered.
     */
    public function __construct(
        private ?string $title,
        private ?string $description,
        private ?string $canonical,
        private ?string $robots,
        private array $hreflangLinks,
        private array $remainingHeadData,
    ) {
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCanonical(): ?string
    {
        return $this->canonical;
    }

    public function getRobots(): ?string
    {
        return $this->robots;
    }

    /**
     * @return HreflangLink[]
     */
    public function getHreflangLinks(): array
    {
        return $this->hreflangLinks;
    }

    /**
     * @return array<string>
     */
    public function getRemainingHeadData(): array
    {
        return $this->remainingHeadData;
    }
}
