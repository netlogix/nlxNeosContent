<?php

namespace nlxNeosContent\Core\Content\Sitemap;

use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use nlxNeosContent\Neos\DTO\NeosPageCollection;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
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
        if ($offset) {
            return new UrlResult([], null);
        }

        try {
            if ($context->getDomainId() === null) {
                $domain = $context->getSalesChannel()->getDomains()
                    ->filter(fn ($d) => $d->getLanguageId() === $context->getLanguageId())
                    ->first()
                    ?? $context->getSalesChannel()->getDomains()->first();

                if ($domain !== null) {
                    $context->setDomainId($domain->getId());
                }
            }

            $tree = $this->loader->load($context);
        } catch (\Throwable $e) {
            $this->logger->error('Neos sitemap fetch failed', ['exception' => $e]);
            return new UrlResult([], null);
        }

        $urls = [];
        $this->collect($tree, $urls);

        return new UrlResult($urls, null);
    }

    private function collect(NeosPageCollection $pages, array &$urls): void
    {
        foreach ($pages as $page) {
            if (!$page->hiddenInIndex) {
                $url = new Url();
                $url->setLoc(trim($page->path, '/'));
                $url->setLastmod(new \DateTime());
                $url->setChangefreq('daily');
                $url->setResource('neos_page');
                $url->setIdentifier(str_replace('-', '', $page->identifier));
                $urls[] = $url;
            }
            $this->collect($page->children, $urls);
        }
    }
}
