<?php


declare(strict_types=1);

namespace nlxNeosContent\Service;

use nlxNeosContent\Error\PageTree\NoTreeItemFoundException;
use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;

#[Autoconfigure(public: true)]
class NeosPageTreeService
{
    function __construct(
        private readonly AbstractNeosPageTreeLoader $neosPageTreeLoader,
    ) {
    }

    public function findNodeIdentifierForRequestAndContext(Request $request, SalesChannelContext $salesChannelContext): NeosPageDTO
    {
        return $this->findNodeIdentifierForPathAndContext($request->getPathInfo(), $salesChannelContext);
    }

    public function findNodeIdentifierForPathAndContext(string $pathInfo, SalesChannelContext $salesChannelContext): NeosPageDTO
    {
        $neosPageTree = $this->loadTreeForContext($salesChannelContext);

        return $this->findByPathInfoInTree($pathInfo, $neosPageTree);
    }

    public function loadTreeForContext(SalesChannelContext $salesChannelContext): NeosPageCollection
    {
        $domains = $salesChannelContext->getSalesChannel()->getDomains();

        // Prefer the exact domain the context actually points at; only fall back to
        // matching by language (ambiguous when a sales channel has several domains
        // sharing one language) when there's no domain id to go on at all.
        $domain = null;
        if ($salesChannelContext->getDomainId() !== null) {
            $domain = $domains
                ->filter(fn (SalesChannelDomainEntity $domain) => $domain->getId() === $salesChannelContext->getDomainId())
                ->first();
        }
        $domain ??= $domains
            ->filter(fn (SalesChannelDomainEntity $domain) => $domain->getLanguageId() === $salesChannelContext->getLanguageId())
            ->first();

        if (!$domain instanceof SalesChannelDomainEntity) {
            throw new \InvalidArgumentException("The salesChannelContext doesn't contain the relevant domain.", code: 1789645629);
        }

        return $this->neosPageTreeLoader->load(
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
            $domain->getUrl()
        );
    }

    public function findByPathInfoInTree(string $pathInfo, NeosPageCollection $tree): NeosPageDTO
    {
        foreach ($tree as $treeItem) {
            if (trim($pathInfo, '/') === trim($treeItem->path, '/')) {
                return $treeItem;
            }

            try {
                return $this->findByPathInfoInTree($pathInfo, $treeItem->children);
            } catch (NoTreeItemFoundException $noTreeItemFoundException) {
                continue;
            }
        }

        throw new NoTreeItemFoundException($pathInfo);
    }

    /**
     * @param iterable<array{string, string, string}> $candidates salesChannelId, languageId, domainUrl triples
     * @throws NoTreeItemFoundException if no candidate's tree contains the path
     */
    public function searchForPathInPageTrees(string $pathInfo, iterable $candidates): NeosPageDTO
    {
        foreach ($this->neosPageTreeLoader->loadMany(iterator_to_array($candidates, false)) as $result) {
            try {
                return $this->findByPathInfoInTree($pathInfo, $result->tree);
            } catch (NoTreeItemFoundException) {
                continue;
            }
        }

        throw new NoTreeItemFoundException($pathInfo);
    }

    public function findAncestorChainForPathAndContext(string $pathInfo, SalesChannelContext $salesChannelContext): NeosPageCollection
    {
        $neosPageTree = $this->loadTreeForContext($salesChannelContext);
        $chain = $this->findAncestorChainInTree($pathInfo, $neosPageTree);

        if ($chain === null) {
            throw new NoTreeItemFoundException($pathInfo);
        }

        return new NeosPageCollection(...$chain);
    }

    /**
     * @return NeosPageDTO[]|null
     */
    private function findAncestorChainInTree(string $pathInfo, NeosPageCollection $tree): ?array
    {
        foreach ($tree as $treeItem) {
            if (trim($pathInfo, '/') === trim($treeItem->path, '/')) {
                return [$treeItem];
            }

            $childChain = $this->findAncestorChainInTree($pathInfo, $treeItem->children);
            if ($childChain !== null) {
                return [$treeItem, ...$childChain];
            }
        }

        return null;
    }

    public function findPathInfoForIdentifierAndContext($nodeIdentifier, SalesChannelContext $salesChannelContext): ?string
    {
        $neosPageTree = $this->loadTreeForContext($salesChannelContext);

        return $this->findPathInfoByNodeIdentifier($nodeIdentifier, $neosPageTree);
    }

    public function findPathInfoByNodeIdentifier(string $nodeIdentifier, NeosPageCollection $tree): ?string
    {
        foreach ($tree as $treeItem) {
            if ($nodeIdentifier === str_replace('-', '', $treeItem->identifier)) {
                return $treeItem->path;
            }

            $pathInfo = $this->findPathInfoByNodeIdentifier($nodeIdentifier, $treeItem->children);

            if ($pathInfo !== null) {
                return $pathInfo;
            }
        }

        return null;
    }
}
