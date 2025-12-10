<?php

declare(strict_types=1);

namespace nlxNeosContent\Factory;


use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;

class NeosPageTreeItemFactory
{
    function create(NeosPageCollection $pages): iterable
    {
        foreach ($pages as $page) {
            try {
                yield $page->identifier => new TreeItem(
                    $this->createCategoryEntity($page),
                    empty($page->children) ? [] : iterator_to_array($this->create($page->children))
                );
            } catch (\Throwable $exception) {
                dd($page, $exception);
            }
        }
    }

    private function createCategoryEntity(NeosPageDTO $page): CategoryEntity
    {
        /**
         * Data needed
         * $categoryName
         * translations for the name and possible other fields
         */

        //TODO figure out child count and visible child count and level

        $category = new SalesChannelCategoryEntity();
        $category->setId($page->identifier);
        $category->setName($page->label);
        $category->setType('neos-entrypoint');
        $category->setSeoUrl(
            sprintf(
                '%s/%s#',
                SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER,
                trim($page->path, '/')
            )
        );
        $category->setTranslated([
                "breadcrumb" => [],
                "name" => $category->getName(),
                "customFields" => [],
                "slotConfig" => [],
                "linkType" => 'link',
                "internalLink" => null,
                "externalLink" => null,
                "linkNewTab" => true,
                "description" => null,
                "metaTitle" => null,
                "metaDescription" => null,
                "keywords" => null,
            ]
        );

        return $category;
    }
}
