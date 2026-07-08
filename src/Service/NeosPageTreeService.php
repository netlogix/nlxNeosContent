<?php


declare(strict_types=1);

namespace nlxNeosContent\Service;

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

    public function findNodeIdentifierForRequestAndContext(Request $request, SalesChannelContext $salesChannelContext): ?NeosPageDTO
    {
        $neosPageTree = $this->neosPageTreeLoader->load($salesChannelContext);
        $pathInfo = $request->getPathInfo();

        return $this->findByPathInfoInTree($pathInfo, $neosPageTree);
    }

    public function findByPathInfoInTree(string $pathInfo, NeosPageCollection $tree): ?NeosPageDTO
    {
        foreach ($tree as $treeItem) {
            if (trim($pathInfo, '/') === trim($treeItem->path, '/')) {
                return $treeItem;
            }

            $foundTreeItem = $this->findByPathInfoInTree($pathInfo, $treeItem->children);

            if ($foundTreeItem !== null) {
                return $foundTreeItem;
            }
        }

        return null;
    }

    public function findPathInfoForIdentifierAndContext($nodeIdentifier, SalesChannelContext $salesChannelContext): string
    {
        $neosPageTree = $this->neosPageTreeLoader->load($salesChannelContext);

        return $this->findPathInfoByNodeIdentifier($nodeIdentifier, $neosPageTree);
    }

    public function findPathInfoByNodeIdentifier(string $nodeIdentifier, NeosPageCollection $tree): string
    {
        foreach ($tree as $treeItem) {
            if ($nodeIdentifier === str_replace('-', '', $treeItem->identifier)) {
                return $treeItem->path;
            }

            $pathInfo = $this->findPathInfoByNodeIdentifier($nodeIdentifier, $treeItem->children);

            if ($pathInfo !== '') {
                return $pathInfo;
            }
        }

        return '';
    }
}
