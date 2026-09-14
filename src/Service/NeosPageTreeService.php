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
        $neosPageTree = $this->neosPageTreeLoader->load($salesChannelContext);

        return $this->findByPathInfoInTree($pathInfo, $neosPageTree);
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

    public function findAncestorChainForPathAndContext(string $pathInfo, SalesChannelContext $salesChannelContext): NeosPageCollection
    {
        $neosPageTree = $this->neosPageTreeLoader->load($salesChannelContext);
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
        $neosPageTree = $this->neosPageTreeLoader->load($salesChannelContext);

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
