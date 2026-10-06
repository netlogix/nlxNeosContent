<?php


declare(strict_types=1);

namespace nlxNeosContent\Service;

use nlxNeosContent\Error\PageTree\NoTreeItemFoundException;
use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use nlxNeosContent\Neos\Endpoint\AbstractNeosPageTreeLoader;
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
        return $this->neosPageTreeLoader->load(
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getLanguageId(),
        );
    }

    /**
     * Matches the page's public SEO path first and only then its raw Neos path, so a page
     * whose templated URL happens to equal another page's Neos path still wins.
     * Compare the result's seoPath with $pathInfo to tell which of the two matched.
     */
    public function findByPathInfoInTree(string $pathInfo, NeosPageCollection $tree): NeosPageDTO
    {
        $pathInfo = trim($pathInfo, '/');

        return $this->findInTree($tree, static fn (NeosPageDTO $page) => trim($page->seoPath, '/') === $pathInfo)
            ?? $this->findInTree($tree, static fn (NeosPageDTO $page) => trim($page->path, '/') === $pathInfo)
            ?? throw new NoTreeItemFoundException($pathInfo);
    }

    /**
     * Public SEO path of the page at the given raw Neos path in that language's tree,
     * or the Neos path itself when the tree doesn't contain it.
     */
    public function findSeoPathForNeosPath(string $neosPath, string $salesChannelId, string $languageId): string
    {
        $tree = $this->neosPageTreeLoader->load($salesChannelId, $languageId);
        $neosPath = trim($neosPath, '/');

        return $this->findInTree($tree, static fn (NeosPageDTO $page) => trim($page->path, '/') === $neosPath)->seoPath
            ?? $neosPath;
    }

    /**
     * @param callable(NeosPageDTO): bool $matches
     */
    private function findInTree(NeosPageCollection $tree, callable $matches): ?NeosPageDTO
    {
        foreach ($tree as $treeItem) {
            if ($matches($treeItem)) {
                return $treeItem;
            }

            $found = $this->findInTree($treeItem->children, $matches);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param iterable<array{string, string}> $candidates salesChannelId, languageId tuples
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

    public function findPageForIdentifierAndContext(string $nodeIdentifier, SalesChannelContext $salesChannelContext): ?NeosPageDTO
    {
        $neosPageTree = $this->loadTreeForContext($salesChannelContext);

        return $this->findInTree(
            $neosPageTree,
            static fn (NeosPageDTO $page) => $nodeIdentifier === str_replace('-', '', $page->identifier)
        );
    }
}
