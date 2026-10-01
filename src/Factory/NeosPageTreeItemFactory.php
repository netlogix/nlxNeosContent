<?php

declare(strict_types=1);

namespace nlxNeosContent\Factory;


use nlxNeosContent\Neos\DTO\NeosPageCollection;
use nlxNeosContent\Neos\DTO\NeosPageDTO;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Tree\TreeItem;

class NeosPageTreeItemFactory
{
    public const FLYOUT_IMAGE_CUSTOM_FIELD = 'nlxNeosFlyoutImage';

    public function __construct(
        private readonly LoggerInterface $logger
    )
    {
    }

    function create(NeosPageCollection $pages): iterable
    {
        foreach ($pages as $page) {
            if ($page->hiddenInIndex) {
                continue;
            }

            try {
                yield $page->identifier => new TreeItem(
                    $this->createCategoryEntity($page),
                    count($page->children) === 0 ? [] : iterator_to_array($this->create($page->children))
                );
            } catch (\Throwable $exception) {
                $this->logger->error("Error while creating Navigation Items from received CMS-Data", [
                    'exception' => $exception,
                    'page' => $page,
                ]);
                continue;
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
        $category->setId(str_replace('-', '', $page->identifier));
        $category->setName($page->label);
        $category->setType('neos-entrypoint');
        $category->setSeoUrl($page->getUrl());
        $customFields = $page->flyoutImage === null ? [] : [self::FLYOUT_IMAGE_CUSTOM_FIELD => $page->flyoutImage];
        $category->setCustomFields($customFields);
        $category->setTranslated([
                "breadcrumb" => [],
                "name" => $category->getName(),
                "customFields" => $customFields,
                "slotConfig" => [],
                "linkType" => 'link',
                "internalLink" => null,
                "externalLink" => null,
                "linkNewTab" => true,
                "description" => $page->flyoutText === null ? null : "<p>" . nl2br(htmlspecialchars($page->flyoutText), false) . "</p>",
                "metaTitle" => null,
                "metaDescription" => null,
                "keywords" => null,
            ]
        );

        return $category;
    }
}
