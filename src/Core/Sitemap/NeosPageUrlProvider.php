<?php

declare(strict_types=1);

namespace nlxNeosContent\Core\Sitemap;

use nlxNeosContent\Error\Sitemap\OffsetPagingNotSupportedException;
use nlxNeosContent\Error\Sitemap\PageTreeCouldNotBeLoaddedException;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.sitemap_url_provider')]
class NeosPageUrlProvider extends AbstractUrlProvider
{
    public function __construct(
        private readonly AbstractNeosPageTreeLoader $loader,
    ) {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'neos';
    }

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        if ($offset !== null) {
            throw new OffsetPagingNotSupportedException(providerName: $this->getName(), offset: $offset, code: 1738074123);
        }

        try {
            $tree = $this->loader->load($context);
        } catch (\Throwable $e) {
            throw new PageTreeCouldNotBeLoaddedException(code: 1738078146, previous: $e);
        }

        $urls = array_map(
            fn (NeosPageDTO $page) => $this->convert($page),
            iterator_to_array($this->flatten($tree), false)
        );

        return new UrlResult($urls, null);
    }

    private function flatten(NeosPageCollection $pages): \Generator
    {
        foreach ($pages as $page) {
            if (!$page->hiddenInIndex) {
                yield $page;
            }
            yield from $this->flatten($page->children);
        }
    }

    private function convert(NeosPageDTO $page): Url
    {
        $url = new Url();
        $url->setLoc(trim($page->path, '/'));
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('daily');
        $url->setResource('neos_page');
        $url->setIdentifier(str_replace('-', '', $page->identifier));

        return $url;
    }
}
